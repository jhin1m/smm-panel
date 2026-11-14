#!/usr/bin/env bash
# Backup Postgres mỗi giờ (cron trên host). Giữ 48 bản hourly + 14 bản daily (bản 03:00). Lỗi → báo Telegram.
#   crontab: 5 * * * * /opt/smm-panel/docker/backup/backup-db.sh >> /var/log/smm-backup.log 2>&1
# BACKUP_DIR: biến môi trường > dòng BACKUP_DIR trong .env của APP_DIR > /srv/backups/smm-panel.
# Off-site: đặt OFFSITE_CMD, vd OFFSITE_CMD='rclone copy /srv/backups/smm-panel remote:smm-panel'
# Bản trước khi cập nhật (updater gọi): BACKUP_SUBDIR=pre-update BACKUP_LABEL=1.2.0-to-1.3.0 backup-db.sh
#   → $BACKUP_DIR/pre-update/smm-<nhãn>-<thời điểm>.dump, giữ SUBDIR_KEEP (mặc định 5) bản mới nhất,
#   tách khỏi hourly/daily để không bị luân phiên 48 bản đẩy mất.
set -Eeuo pipefail
umask 077   # dump chứa dữ liệu khách — chỉ chủ sở hữu đọc được

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# APP_DIR = thư mục chứa compose.yml gần nhất: cạnh script (bản tarball phẳng), rồi ../.. (docker/backup/ trong repo/bundle).
find_app_dir() {
    local candidate
    for candidate in "$SCRIPT_DIR" "$SCRIPT_DIR/../.."; do
        if [ -f "$candidate/compose.yml" ]; then
            (cd "$candidate" && pwd)
            return 0
        fi
    done
    return 1
}
APP_DIR="${APP_DIR:-$(find_app_dir)}" || { echo "Không tìm thấy compose.yml; đặt APP_DIR=<thư mục cài đặt>" >&2; exit 1; }

# Đọc một khoá từ .env mà không `source` (giá trị có thể chứa ký tự đặc biệt). In giá trị đã bỏ nháy, hoặc rỗng.
env_value() {
    local line
    [ -f "$APP_DIR/.env" ] || return 0
    line="$(grep -E "^$1=" "$APP_DIR/.env" | tail -n 1 || true)"
    line="${line#*=}"
    line="${line%\"}"
    line="${line#\"}"
    printf '%s' "$line"
}

BACKUP_DIR="${BACKUP_DIR:-$(env_value BACKUP_DIR)}"
BACKUP_DIR="${BACKUP_DIR:-/srv/backups/smm-panel}"
HOURLY_KEEP="${HOURLY_KEEP:-48}"
DAILY_KEEP="${DAILY_KEEP:-14}"
BACKUP_SUBDIR="${BACKUP_SUBDIR:-}"
BACKUP_LABEL="${BACKUP_LABEL:-manual}"
SUBDIR_KEEP="${SUBDIR_KEEP:-5}"

# Tên thư mục/nhãn đi vào đường dẫn file: chỉ cho phép ký tự an toàn.
if [ -n "$BACKUP_SUBDIR" ]; then
    case "$BACKUP_SUBDIR$BACKUP_LABEL" in
        *[!A-Za-z0-9._-]*) echo "BACKUP_SUBDIR/BACKUP_LABEL chỉ gồm chữ, số, '.', '_', '-'" >&2; exit 1 ;;
    esac
    case "$BACKUP_SUBDIR" in
        hourly | daily | . | ..) echo "BACKUP_SUBDIR không được trùng hourly/daily" >&2; exit 1 ;;
    esac
fi

cd "$APP_DIR"
notify_failure() {
    docker compose exec -T app php artisan app:notify-admin "Backup DB lỗi trên $(hostname) lúc $(date '+%F %T'): dòng $1" || true
}
trap 'notify_failure $LINENO' ERR

STAMP="$(date +%Y%m%d-%H%M)"
if [ -n "$BACKUP_SUBDIR" ]; then
    mkdir -p "$BACKUP_DIR/$BACKUP_SUBDIR"
    FILE="$BACKUP_DIR/$BACKUP_SUBDIR/smm-$BACKUP_LABEL-$(date +%Y%m%d-%H%M%S).dump"
else
    mkdir -p "$BACKUP_DIR/hourly" "$BACKUP_DIR/daily"
    FILE="$BACKUP_DIR/hourly/smm-$STAMP.dump"
fi

# shellcheck disable=SC2016
docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$FILE.tmp"
[ -s "$FILE.tmp" ] || { echo "dump rỗng"; false; }
mv "$FILE.tmp" "$FILE"

if [ -n "$BACKUP_SUBDIR" ]; then
    # shellcheck disable=SC2012  # tên file do script này sinh, nhãn đã được lọc ký tự
    { ls -1t "$BACKUP_DIR/$BACKUP_SUBDIR"/*.dump 2>/dev/null || true; } | tail -n +"$((SUBDIR_KEEP + 1))" | xargs -r rm -f
    echo "$(date '+%F %T') OK $FILE ($(du -h "$FILE" | cut -f1))"
    exit 0
fi

if [ "$(date +%H)" = "03" ]; then
    cp "$FILE" "$BACKUP_DIR/daily/"
fi

# shellcheck disable=SC2012  # tên file do script này sinh (smm-<timestamp>.dump), không có ký tự lạ
ls -1t "$BACKUP_DIR"/hourly/*.dump | tail -n +"$((HOURLY_KEEP + 1))" | xargs -r rm -f
# Chưa có bản daily nào (trước 03:00 ngày đầu): ls lỗi không được làm pipeline thất bại (pipefail).
# shellcheck disable=SC2012
{ ls -1t "$BACKUP_DIR"/daily/*.dump 2>/dev/null || true; } | tail -n +"$((DAILY_KEEP + 1))" | xargs -r rm -f

if [ -n "${OFFSITE_CMD:-}" ]; then
    eval "$OFFSITE_CMD"
fi

echo "$(date '+%F %T') OK $FILE ($(du -h "$FILE" | cut -f1))"
