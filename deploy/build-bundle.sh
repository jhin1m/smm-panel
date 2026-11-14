#!/usr/bin/env bash
# Dựng bộ file đính kèm Release: smm-panel-deploy-<tag>.tar.gz, release.json, install.sh, checksums.txt.
# Workflow release và bài kiểm tra hợp đồng với install.sh dùng CHUNG script này.
#
#   deploy/build-bundle.sh <tag> <app_image> <out_dir>
#     <tag>        tag git, vd v1.2.3 hoặc v1.2.3-rc.1 (tên file tải về của install.sh dựa trên đúng chuỗi này)
#     <app_image>  image ghim digest, vd ghcr.io/jhin1m/smm-panel@sha256:<64 hex>
#     <out_dir>    thư mục đích (được tạo nếu chưa có)
set -euo pipefail

die() { printf 'LỖI: %s\n' "$*" >&2; exit 1; }

[[ $# -eq 3 ]] || die "Dùng: $0 <tag> <app_image> <out_dir>"
TAG="$1"
APP_IMAGE="$2"
OUT_DIR="$3"

[[ "$TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.]+)?$ ]] || die "Tag không hợp lệ: '$TAG' (vd v1.2.3, v1.2.3-rc.1)."
[[ "$APP_IMAGE" =~ ^[a-z0-9][a-z0-9./_-]*(:[A-Za-z0-9._-]+)?@sha256:[0-9a-f]{64}$ ]] || die "app_image phải ghim digest: '$APP_IMAGE'."

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${TAG#v}"
TARBALL="smm-panel-deploy-${TAG}.tar.gz"

# Nội dung bundle, giữ nguyên cấu trúc thư mục. Thiếu file bắt buộc thì dừng ngay.
FILES=(
    compose.yml
    .env.template
    docker/caddy/Caddyfile
    docker/backup/backup-db.sh
    custom/README.md
)
for f in "${FILES[@]}"; do
    [[ -f "$ROOT/$f" ]] || die "Thiếu file bắt buộc của bundle: $f"
done

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

for f in "${FILES[@]}"; do
    mkdir -p "$STAGE/$(dirname "$f")"
    cp -p "$ROOT/$f" "$STAGE/$f"
done
# update.sh (cập nhật từ host) chỉ vào bundle khi đã có trong repo.
if [[ -f "$ROOT/deploy/update.sh" ]]; then
    cp -p "$ROOT/deploy/update.sh" "$STAGE/update.sh"
    FILES+=(update.sh)
fi

mkdir -p "$OUT_DIR"
OUT_DIR="$(cd "$OUT_DIR" && pwd)"

tar_opts=()
if tar --version 2> /dev/null | grep -q 'GNU tar'; then
    tar_opts=(--owner=0 --group=0 --numeric-owner --sort=name)
fi
COPYFILE_DISABLE=1 tar "${tar_opts[@]}" -czf "$OUT_DIR/$TARBALL" -C "$STAGE" "${FILES[@]}"

# Mỗi khoá một dòng riêng: install.sh đọc bằng sed, không cần jq.
# Chỗ dành cho khoá `updater_image` (image updater ghim digest) khi có tính năng cập nhật qua web:
#   thêm dòng "updater_image": "<repo>@sha256:..." ngay sau app_image.
cat > "$OUT_DIR/release.json" << JSON
{
  "version": "$VERSION",
  "app_image": "$APP_IMAGE"
}
JSON

cp -p "$ROOT/deploy/install.sh" "$OUT_DIR/install.sh"

# install.sh bắt buộc có dòng cho cả tarball lẫn release.json; install.sh được liệt kê để người dùng tự kiểm.
(cd "$OUT_DIR" && sha256sum "$TARBALL" release.json install.sh > checksums.txt)

printf 'Đã dựng bundle %s trong %s\n' "$TAG" "$OUT_DIR"
