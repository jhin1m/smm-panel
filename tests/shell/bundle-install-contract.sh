#!/usr/bin/env bash
# Kiểm hợp đồng giữa deploy/build-bundle.sh (phía release) và deploy/install.sh (phía người cài):
# bundle dựng ra phải qua được tải + kiểm checksum + đọc release.json + giải nén của install.sh (qua file://),
# và bundle bị sửa phải bị từ chối. Cần sha256sum (Linux).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

TAG="v9.9.9-rc.1"
DIGEST="sha256:$(printf 'a%.0s' {1..64})"
IMAGE="ghcr.io/jhin1m/smm-panel@${DIGEST}"
SERVE="$WORK/serve/$TAG"

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
ok() { printf 'ok: %s\n' "$*"; }

"$ROOT/deploy/build-bundle.sh" "$TAG" "$IMAGE" "$SERVE" > /dev/null

for asset in "smm-panel-deploy-${TAG}.tar.gz" release.json install.sh checksums.txt; do
    [[ -s "$SERVE/$asset" ]] || fail "thiếu asset $asset"
done
ok "đủ 4 asset"

for asset in "smm-panel-deploy-${TAG}.tar.gz" release.json install.sh; do
    grep -Eq "^[0-9a-f]{64}  ${asset//./\\.}\$" "$SERVE/checksums.txt" || fail "checksums.txt thiếu dòng $asset"
done
(cd "$SERVE" && sha256sum -c checksums.txt > /dev/null) || fail "sha256sum -c checksums.txt"
ok "checksums.txt phủ tarball, release.json, install.sh và khớp"

# Chạy phía người cài: nạp hàm từ install.sh (không chạy main).
run_installer_side() {
    (
        # shellcheck disable=SC1091
        source "$ROOT/deploy/install.sh"
        RELEASE_DOWNLOAD_BASE="file://$WORK/serve"
        TMP_DIR="$WORK/tmp-$1"
        INSTALL_DIR="$WORK/install-$1"
        mkdir -p "$TMP_DIR"
        download_bundle "$TAG"
        [[ "$APP_IMAGE" == "$IMAGE" ]] || { echo "app_image đọc sai: '$APP_IMAGE'" >&2; exit 1; }
        extract_bundle
    )
}

run_installer_side good || fail "install.sh từ chối bundle hợp lệ"
for f in compose.yml .env.template docker/caddy/Caddyfile docker/backup/backup-db.sh custom/README.md; do
    [[ -f "$WORK/install-good/$f" ]] || fail "bundle giải nén thiếu $f"
done
ok "install.sh tải, kiểm checksum, đọc app_image, giải nén bundle"

# release.json sai bị chặn: sửa file nhưng giữ nguyên checksums.txt.
cp -r "$WORK/serve" "$WORK/serve-bad"
sed -i.bak 's/"version": "[^"]*"/"version": "0.0.0"/' "$WORK/serve-bad/$TAG/release.json"
rm -f "$WORK/serve-bad/$TAG/release.json.bak"
if (
    # shellcheck disable=SC1091
    source "$ROOT/deploy/install.sh"
    RELEASE_DOWNLOAD_BASE="file://$WORK/serve-bad"
    TMP_DIR="$WORK/tmp-bad"
    INSTALL_DIR="$WORK/install-bad"
    mkdir -p "$TMP_DIR"
    download_bundle "$TAG"
) > /dev/null 2>&1; then
    fail "install.sh chấp nhận release.json bị sửa"
fi
[[ ! -d "$WORK/install-bad" ]] || fail "bundle bị sửa vẫn tạo thư mục cài"
ok "release.json bị sửa bị từ chối"
