#!/usr/bin/env bash
# Cập nhật SMM Panel từ máy chủ (thay cho nút "Cập nhật" trong admin): chạy trong thư mục cài, bằng root hoặc user dùng được docker.
#   ./update.sh            lên bản ổn định mới nhất
#   ./update.sh 1.3.0      lên đúng phiên bản (cũng nhận v1.3.0; bản -rc.N cần UPDATER_ALLOW_PRERELEASE=true trong .env)
#
# Script này chỉ làm phần mà updater trong web KHÔNG tự làm được: đổi chính image updater (UPDATER_IMAGE theo release.json),
# rồi chạy CÙNG logic cập nhật (smm-update trong image updater mới): kiểm checksum, sao lưu DB, đổi bundle, kiểm sức khoẻ,
# tự quay về bản cũ nếu lỗi. Không có logic cập nhật nào khác ở đây.
set -euo pipefail

# Repo phát hành; đổi RELEASE_REPO để dùng fork hoặc kiểm thử với máy chủ giả.
RELEASE_REPO="${RELEASE_REPO:-jhin1m/smm-panel}"
RELEASE_API_URL="${RELEASE_API_URL:-https://api.github.com/repos/${RELEASE_REPO}/releases/latest}"
RELEASE_DOWNLOAD_BASE="${RELEASE_DOWNLOAD_BASE:-https://github.com/${RELEASE_REPO}/releases/download}"

SEMVER_RE='^[0-9]{1,6}\.[0-9]{1,6}\.[0-9]{1,6}(-rc\.[0-9]{1,6})?$'
IMAGE_RE='^[a-z0-9][a-z0-9./_-]*(:[A-Za-z0-9._-]+)?@sha256:[0-9a-f]{64}$'

msg() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
die() { printf '\033[1;31mLỖI:\033[0m %s\n' "$*" >&2; exit 1; }

INSTALL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$INSTALL_DIR/.env"
TMP_DIR=""
cleanup() { [[ -z "$TMP_DIR" ]] || rm -rf "$TMP_DIR"; }
trap cleanup EXIT

env_get() {
    local line
    [[ -f "$ENV_FILE" ]] || return 0
    line="$(grep -E "^$1=" "$ENV_FILE" | tail -n 1 || true)"
    line="${line#*=}"
    line="${line%\"}"
    line="${line#\"}"
    printf '%s' "$line"
}

# env_set <KEY> <value>: thay dòng KEY=... (hoặc thêm cuối), ghi nguyên tử, giữ quyền 600.
env_set() {
    local tmp
    tmp="$(mktemp "$ENV_FILE.XXXXXX")"
    ENV_K="$1" ENV_V="$2" awk '
        BEGIN { k = ENVIRON["ENV_K"]; v = ENVIRON["ENV_V"]; done = 0 }
        index($0, k "=") == 1 { print k "=" v; done = 1; next }
        { print }
        END { if (!done) print k "=" v }
    ' "$ENV_FILE" > "$tmp"
    chmod 600 "$tmp"
    mv -f "$tmp" "$ENV_FILE"
}

json_get() {
    sed -n "s/.*\"$2\"[[:space:]]*:[[:space:]]*\"\\([^\"]*\\)\".*/\\1/p" "$1" | head -n 1
}

# Updater trong web đang có lần cập nhật chạy hoặc có yêu cầu chờ nhận? Không được động vào: `up -d updater` cuối script sẽ
# recreate container loop giữa chừng và lần chạy đó bị giết rồi khôi phục vô cớ. Không đọc được (updater chưa chạy) = rảnh;
# khoá flock trong smm-update vẫn chặn hai lần chạy song song.
updater_busy() {
    local state beat now
    docker compose exec -T updater test -f /updates/inbox/request.json > /dev/null 2>&1 && return 0
    state="$(docker compose exec -T updater jq -r '.state // ""' /updates/status.json 2> /dev/null || true)"
    [[ "$state" == "running" ]] || return 1
    beat="$(docker compose exec -T updater cat /updates/heartbeat 2> /dev/null || true)"
    [[ "$beat" =~ ^[0-9]+$ ]] || return 1
    now="$(date +%s)"
    ((now - beat < 60)) # cùng ngưỡng 60 giây mà trang admin dùng để coi updater là đã chết
}

[[ $# -le 1 ]] || die "Dùng: ./update.sh [X.Y.Z]"
[[ -f "$INSTALL_DIR/compose.yml" && -f "$ENV_FILE" ]] || die "Không thấy compose.yml/.env cạnh update.sh ($INSTALL_DIR)."
for tool in docker curl sha256sum awk; do
    command -v "$tool" > /dev/null || die "Thiếu lệnh '$tool'."
done
docker compose version > /dev/null 2>&1 || die "Thiếu docker compose plugin."

VERSION="${1:-}"
VERSION="${VERSION#v}"
if [[ -z "$VERSION" ]]; then
    VERSION="$(curl -fsSL --retry 2 --connect-timeout 15 -H 'Accept: application/vnd.github+json' "$RELEASE_API_URL" \
        | sed -n 's/.*"tag_name"[[:space:]]*:[[:space:]]*"v\{0,1\}\([^"]*\)".*/\1/p' | head -n 1)" \
        || die "Không lấy được bản mới nhất từ $RELEASE_API_URL; truyền phiên bản: ./update.sh 1.3.0"
fi
[[ "$VERSION" =~ $SEMVER_RE ]] || die "Phiên bản không hợp lệ: '$VERSION' (cần X.Y.Z hoặc X.Y.Z-rc.N)."

(cd "$INSTALL_DIR" && updater_busy) && die "Updater đang có một lần cập nhật chạy hoặc đang chờ nhận yêu cầu (xem trang admin 'Cập nhật'). Đợi nó kết thúc rồi chạy lại ./update.sh; chưa thay đổi gì."

# release.json của bản đích (kiểm checksum) cho biết image updater mới.
TMP_DIR="$(mktemp -d)"
msg "Tải release.json của v$VERSION"
for asset in release.json checksums.txt; do
    curl -fsSL --retry 3 --connect-timeout 15 "$RELEASE_DOWNLOAD_BASE/v$VERSION/$asset" -o "$TMP_DIR/$asset" \
        || die "Không tải được $asset của v$VERSION (phiên bản không tồn tại?)."
done
awk '{ n = $2; sub(/^\*/, "", n); if (n == "release.json") print }' "$TMP_DIR/checksums.txt" > "$TMP_DIR/check-line"
[[ -s "$TMP_DIR/check-line" ]] || die "checksums.txt không có dòng cho release.json."
(cd "$TMP_DIR" && sha256sum -c check-line > /dev/null 2>&1) || die "Checksum SAI cho release.json; không cập nhật."

[[ "$(json_get "$TMP_DIR/release.json" version)" == "$VERSION" ]] || die "release.json không khớp phiên bản $VERSION."
NEW_UPDATER_IMAGE="$(json_get "$TMP_DIR/release.json" updater_image)"
[[ "$NEW_UPDATER_IMAGE" =~ $IMAGE_RE ]] || die "release.json: updater_image thiếu hoặc không ghim theo digest."

cd "$INSTALL_DIR"
OLD_UPDATER_IMAGE="$(env_get UPDATER_IMAGE)"
env_set UPDATER_IMAGE "$NEW_UPDATER_IMAGE"

msg "Tải image updater mới"
if ! docker compose pull updater; then
    [[ -z "$OLD_UPDATER_IMAGE" ]] || env_set UPDATER_IMAGE "$OLD_UPDATER_IMAGE"
    die "Không tải được image updater $NEW_UPDATER_IMAGE."
fi

msg "Chạy cập nhật lên v$VERSION"
rc=0
docker compose run --rm --no-deps updater smm-update "$VERSION" || rc=$?

# Đưa container updater (vòng lặp web) sang image mới dù lần cập nhật thành công hay không.
msg "Khởi động lại updater với image mới"
docker compose up -d updater || printf 'CẢNH BÁO: không khởi động lại được updater; chạy: docker compose up -d updater\n' >&2

if ((rc != 0)); then
    die "Cập nhật không thành công (mã $rc). Xem lý do ở trên hoặc trong trang admin 'Cập nhật'; bản cũ đã được khôi phục nếu lỗi xảy ra sau khi đổi bundle."
fi
msg "Hoàn tất: đang chạy v$VERSION."
