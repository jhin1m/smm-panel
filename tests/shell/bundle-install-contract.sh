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
UPDATER_DIGEST="sha256:$(printf 'b%.0s' {1..64})"
UPDATER_REF="ghcr.io/jhin1m/smm-panel-updater@${UPDATER_DIGEST}"
SERVE="$WORK/serve/$TAG"

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
ok() { printf 'ok: %s\n' "$*"; }

"$ROOT/deploy/build-bundle.sh" "$TAG" "$IMAGE" "$UPDATER_REF" "$SERVE" > /dev/null

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
        [[ "$UPDATER_IMAGE" == "$2" ]] || { echo "updater_image đọc sai: '$UPDATER_IMAGE'" >&2; exit 1; }
        extract_bundle
    )
}

run_installer_side good "$UPDATER_REF" || fail "install.sh từ chối bundle hợp lệ"
for f in compose.yml .env.template docker/caddy/Caddyfile docker/backup/backup-db.sh custom/README.md update.sh; do
    [[ -f "$WORK/install-good/$f" ]] || fail "bundle giải nén thiếu $f"
done
[[ -x "$WORK/install-good/update.sh" ]] || fail "update.sh trong bundle không có quyền thực thi"
ok "install.sh tải, kiểm checksum, đọc app_image + updater_image, giải nén bundle (có update.sh chạy được)"

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

# release.json thiếu updater_image: install.sh phải từ chối (checksum được dựng lại cho khớp để chỉ thử đúng điều kiện này).
mkdir -p "$WORK/serve-noupd"
cp -r "$SERVE" "$WORK/serve-noupd/$TAG"
NOUPD="$WORK/serve-noupd/$TAG"
grep -v '"updater_image"' "$NOUPD/release.json" | sed 's/"app_image": \(.*\),$/"app_image": \1/' > "$NOUPD/release.json.new"
mv "$NOUPD/release.json.new" "$NOUPD/release.json"
(cd "$NOUPD" && sha256sum "smm-panel-deploy-${TAG}.tar.gz" release.json install.sh > checksums.txt)
if (
    # shellcheck disable=SC1091
    source "$ROOT/deploy/install.sh"
    RELEASE_DOWNLOAD_BASE="file://$WORK/serve-noupd"
    TMP_DIR="$WORK/tmp-noupd"
    INSTALL_DIR="$WORK/install-noupd"
    mkdir -p "$TMP_DIR"
    download_bundle "$TAG"
) > /dev/null 2>&1; then
    fail "install.sh chấp nhận release.json không có updater_image"
fi
ok "release.json thiếu updater_image bị từ chối"
