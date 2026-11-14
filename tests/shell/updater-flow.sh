#!/usr/bin/env bash
# Kiểm luồng cập nhật của docker/updater/{smm-update,smm-updater-loop} và deploy/update.sh mà không cần máy chủ docker thật:
# `docker` là shim ghi lại lệnh được gọi (health/smoke/bảo trì điều khiển bằng biến môi trường), bản phát hành là
# bundle dựng bằng deploy/build-bundle.sh và tải qua file://, INSTALL_DIR/UPDATES_DIR/BACKUP_DIR là thư mục tạm.
# Cần bash, jq, curl, tar, flock, sha256sum (Linux).
#
# KHÔNG kiểm được ở đây (cần VM thật): docker compose thật recreate container, healthcheck thật, mount docker.sock,
# kéo image từ GHCR, Caddy reload thật, hành vi khi `docker kill` giữa chừng.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
ok() { printf 'ok: %s\n' "$*"; }

for tool in jq curl tar flock sha256sum; do
    command -v "$tool" > /dev/null || fail "thiếu công cụ $tool"
done

digest() { # 64 ký tự hex lặp từ một ký tự
    local out="" i
    for ((i = 0; i < 64; i++)); do out+="$1"; done
    printf '%s' "$out"
}
OLD_APP_IMAGE="ghcr.io/jhin1m/smm-panel@sha256:$(digest 0)"
NEW_APP_IMAGE="ghcr.io/jhin1m/smm-panel@sha256:$(digest a)"
BROKEN_APP_IMAGE="ghcr.io/jhin1m/smm-broken@sha256:$(digest c)"
UPDATER_IMAGE="ghcr.io/jhin1m/smm-panel-updater@sha256:$(digest b)"
OLD_UPDATER_IMAGE="ghcr.io/jhin1m/smm-panel-updater@sha256:$(digest 1)"

# ---------------------------------------------------------------- bản phát hành giả

SERVE="$WORK/serve"
build_release() { # tag app_image
    "$ROOT/deploy/build-bundle.sh" "$1" "$2" "$UPDATER_IMAGE" "$SERVE/$1" > /dev/null
}
build_release v1.3.0 "$NEW_APP_IMAGE"
build_release v1.3.1 "$NEW_APP_IMAGE"
build_release v1.3.2 "$BROKEN_APP_IMAGE"
build_release v1.3.3 "$NEW_APP_IMAGE"
build_release v1.4.0-rc.1 "$NEW_APP_IMAGE"
build_release v1.2.0 "$NEW_APP_IMAGE"
build_release v1.1.0 "$NEW_APP_IMAGE"

# v1.3.1: tarball bị sửa sau khi tính checksum.
printf 'tampered' >> "$SERVE/v1.3.1/smm-panel-deploy-v1.3.1.tar.gz"

# ---------------------------------------------------------------- docker giả

mkdir -p "$WORK/bin"
cat > "$WORK/bin/docker" << 'SHIM'
#!/usr/bin/env bash
# Ghi lại mọi lệnh; health đọc APP_IMAGE trong .env của thư mục hiện tại (chứa "broken" = unhealthy).
set -u
echo "docker $*" >> "$SHIM_LOG"
if [[ "$1" == inspect ]]; then
    if [[ "${SHIM_HEALTH:-}" != "" ]]; then
        echo "$SHIM_HEALTH"
    elif grep -q '^APP_IMAGE=.*broken' "$INSTALL_DIR/.env"; then
        echo "running unhealthy"
    else
        echo "running healthy"
    fi
    exit 0
fi
[[ "$1" == compose ]] || exit 0
shift
sub="$1"
shift
case "$sub" in
    ps) echo "cid-app" ;;
    pull) [[ -z "${SHIM_HANG_PULL:-}" ]] || sleep 60 ;;
    config) echo "${SHIM_SERVICES:-postgres app queue scheduler caddy updater}" | tr ' ' '\n' ;;
    up)
        echo "  [up] APP_IMAGE=$(grep '^APP_IMAGE=' .env | cut -d= -f2-) compose=$(head -n 1 compose.yml)" >> "$SHIM_LOG"
        ;;
    run) exit "${SHIM_RUN_RC:-0}" ;;
    exec)
        [[ "$1" == -T ]] && shift
        svc="$1"
        shift
        case "$svc $*" in
            "updater "*) # đọc /updates của updater: ánh xạ sang thư mục tạm của kịch bản
                args=("${@//\/updates/$UPDATES_DIR}")
                "${args[@]}"
                exit $?
                ;;
            "app php artisan app:customer-maintenance on") touch "$SHIM_STATE/maint" ;;
            "app php artisan app:customer-maintenance off")
                [[ -f "$SHIM_STATE/off-fails" ]] && exit 1
                rm -f "$SHIM_STATE/maint"
                ;;
            "app curl"*) printf '%s' "${SHIM_SMOKE_CODE:-200}" ;;
            "postgres sh"*) echo "PGDUMP" ;;
            *) ;;
        esac
        ;;
    *) ;;
esac
exit 0
SHIM
chmod +x "$WORK/bin/docker"

# ---------------------------------------------------------------- dựng một kịch bản

S=""        # thư mục kịch bản hiện tại
LOG=""
new_scenario() {
    S="$WORK/s-$1"
    rm -rf "$S"
    mkdir -p "$S/install/docker/caddy" "$S/install/docker/backup" "$S/install/custom" "$S/updates/inbox" "$S/backups" "$S/state"
    LOG="$S/shim.log"
    : > "$LOG"
    printf '# OLD COMPOSE\n' > "$S/install/compose.yml"
    printf 'old caddy\n' > "$S/install/docker/caddy/Caddyfile"
    cp "$ROOT/docker/backup/backup-db.sh" "$S/install/docker/backup/backup-db.sh"
    printf 'khach tuy bien\n' > "$S/install/custom/mine.blade.php"
    printf '#!/bin/sh\n# OLD UPDATE\n' > "$S/install/update.sh"
    cat > "$S/install/.env" << ENV
APP_KEY=base64:secretkey
DB_PASSWORD=s3cret-pass
APP_VERSION=v1.2.0
APP_IMAGE=$OLD_APP_IMAGE
UPDATER_IMAGE=$OLD_UPDATER_IMAGE
ENV
    chmod 600 "$S/install/.env"
    cp "$S/install/.env" "$S/env.orig"
    cp "$S/install/compose.yml" "$S/compose.orig"
}

# run_in_scenario <lệnh...>: chạy với môi trường của kịch bản; trả mã thoát, không làm test dừng.
run_in_scenario() {
    env PATH="$WORK/bin:$PATH" \
        INSTALL_DIR="$S/install" UPDATES_DIR="$S/updates" BACKUP_DIR="$S/backups" \
        RELEASE_REPO="jhin1m/smm-panel" RELEASE_DOWNLOAD_BASE="file://$SERVE" \
        HEALTH_TIMEOUT=3 HEALTH_INTERVAL=1 RETRY_SLEEP=0 \
        SHIM_LOG="$LOG" SHIM_STATE="$S/state" \
        "$@" > "$S/out.txt" 2>&1
}

status_get() { jq -r ".$1" "$S/updates/status.json"; }
log_line() { grep -n -m1 -F -- "$1" "$LOG" | cut -d: -f1; }
log_count() { grep -c -F -- "$1" "$LOG" || true; }
log_has() { grep -q -F -- "$1" "$LOG"; }

assert_status() { # state [mẫu message]
    [[ "$(status_get state)" == "$1" ]] || fail "status.state='$(status_get state)', cần '$1' ($S)
$(cat "$S/out.txt")"
    if [[ $# -ge 2 ]]; then
        status_get message | grep -q -- "$2" || fail "status.message='$(status_get message)' không chứa '$2'"
    fi
}
assert_untouched() { # .env, compose.yml và custom/ y như ban đầu
    cmp -s "$S/env.orig" "$S/install/.env" || fail ".env bị đổi"
    cmp -s "$S/compose.orig" "$S/install/compose.yml" || fail "compose.yml bị đổi"
    [[ "$(cat "$S/install/custom/mine.blade.php")" == "khach tuy bien" ]] || fail "custom/ bị đổi"
    [[ ! -d "$S/install/.update-saved" ]] || fail "còn thư mục .update-saved"
}
assert_maintenance_off() { [[ ! -f "$S/state/maint" ]] || fail "bảo trì khách vẫn đang bật"; }

# ---------------------------------------------------------------- 1. thành công

new_scenario success
for i in 1 2 3 4 5 6; do # 6 bản cũ + 1 mới → giữ 5
    mkdir -p "$S/backups/pre-update"
    : > "$S/backups/pre-update/smm-old$i-20200101-0000$i.dump"
    touch -d "2020-01-0$i 00:00" "$S/backups/pre-update/smm-old$i-20200101-0000$i.dump"
done
printf 'hourly' > "$S/backups/keep-me"
run_in_scenario "$ROOT/docker/updater/smm-update" 1.3.0 || fail "cập nhật hợp lệ bị lỗi:
$(cat "$S/out.txt")"
assert_status success
assert_maintenance_off
grep -q '^APP_VERSION=v1.3.0$' "$S/install/.env" || fail "APP_VERSION không được đặt"
grep -q "^APP_IMAGE=$NEW_APP_IMAGE\$" "$S/install/.env" || fail "APP_IMAGE không đặt theo release.json"
grep -q "^UPDATER_IMAGE=$OLD_UPDATER_IMAGE\$" "$S/install/.env" || fail "UPDATER_IMAGE bị đổi bởi cập nhật web"
grep -q '^DB_PASSWORD=s3cret-pass$' "$S/install/.env" || fail ".env mất secret"
[[ "$(stat -c %a "$S/install/.env" 2> /dev/null || stat -f %Lp "$S/install/.env")" == 600 ]] || fail ".env không còn quyền 600"
cmp -s "$ROOT/compose.yml" "$S/install/compose.yml" || fail "compose.yml chưa là bản của release"
[[ "$(cat "$S/install/custom/mine.blade.php")" == "khach tuy bien" ]] || fail "custom/ bị đổi"
[[ ! -d "$S/install/.update-saved" ]] || fail "còn .update-saved sau khi thành công"
cmp -s "$ROOT/docker/caddy/Caddyfile" "$S/install/docker/caddy/Caddyfile" || fail "docker/ chưa được cập nhật"
dumps=("$S"/backups/pre-update/*.dump)
((${#dumps[@]} == 5)) || fail "pre-update phải giữ đúng 5 bản, có ${#dumps[@]}"
new_dump=("$S"/backups/pre-update/smm-1.2.0-to-1.3.0-*.dump)
[[ -s "${new_dump[0]}" ]] || fail "không có dump pre-update mới"
[[ -f "$S/backups/keep-me" && ! -d "$S/backups/hourly" ]] || fail "backup pre-update lẫn vào hourly/"
[[ "$(wc -l < "$S/updates/history.jsonl" | tr -d ' ')" == 1 ]] || fail "history.jsonl phải có 1 dòng"
[[ "$(stat -c %a "$S/updates/status.json" 2> /dev/null || stat -f %Lp "$S/updates/status.json")" == 644 ]] || fail "status.json phải 644 để app đọc được"

on="$(log_line 'app:customer-maintenance on')"
dump="$(log_line 'postgres sh')"
pull="$(log_line 'compose pull postgres app queue scheduler caddy')"
up="$(log_line 'compose up -d postgres app queue scheduler caddy')"
smoke="$(log_line 'app curl')"
queue="$(log_line 'queue:restart')"
[[ -n "$on" && -n "$dump" && -n "$pull" && -n "$up" && -n "$smoke" && -n "$queue" ]] || fail "thiếu lệnh trong chuỗi cập nhật:
$(cat "$LOG")"
((on < dump && dump < pull && pull < up && up < smoke && smoke < queue)) || fail "sai thứ tự: on=$on dump=$dump pull=$pull up=$up smoke=$smoke queue=$queue"
off_first="$(log_line 'app:customer-maintenance off')"
((up < off_first && off_first < smoke)) || fail "phải tắt bảo trì khách trước khi kiểm /login"
log_has 'artisan down' && fail "không được dùng artisan down"
log_has 'caddy reload' || fail "Caddyfile đổi nội dung nhưng không reload caddy"
ok "cập nhật thành công: thứ tự bước, .env/custom/ giữ nguyên, retention pre-update 5, bảo trì tắt"

# ---------------------------------------------------------------- 2. checksum sai

new_scenario checksum
run_in_scenario "$ROOT/docker/updater/smm-update" 1.3.1 && fail "bundle sai checksum vẫn được cập nhật"
assert_status failed "Checksum"
assert_untouched
log_has 'compose up' && fail "đã chạy docker compose up với bundle sai checksum"
log_has 'app:customer-maintenance on' && fail "đã bật bảo trì dù bundle chưa qua kiểm"
[[ ! -d "$S/backups/pre-update" ]] || fail "đã backup dù chưa qua kiểm"
ok "checksum sai: từ chối, status failed, không đụng tới hệ thống"

# ---------------------------------------------------------------- 3. phiên bản không tồn tại

new_scenario missing
run_in_scenario "$ROOT/docker/updater/smm-update" 9.9.9 && fail "phiên bản không tồn tại vẫn chạy"
assert_status failed "Không tải được"
assert_untouched
ok "phiên bản không tồn tại: failed, hệ thống nguyên vẹn"

# ---------------------------------------------------------------- 4. không mới hơn / prerelease / định dạng lạ

new_scenario notnewer
for v in 1.2.0 1.1.0; do
    run_in_scenario "$ROOT/docker/updater/smm-update" "$v" && fail "phiên bản $v không mới hơn mà vẫn chạy"
    assert_status failed "không mới hơn"
done
[[ ! -s "$LOG" ]] || fail "không được gọi docker khi từ chối phiên bản: $(cat "$LOG")"
assert_untouched
ok "bằng/thấp hơn bản đang chạy bị từ chối trước khi làm gì"

new_scenario prerelease
run_in_scenario "$ROOT/docker/updater/smm-update" 1.4.0-rc.1 && fail "rc được chạy khi chưa bật UPDATER_ALLOW_PRERELEASE"
assert_status failed "UPDATER_ALLOW_PRERELEASE"
[[ ! -s "$LOG" ]] || fail "gọi docker dù rc bị từ chối"
assert_untouched
ok "rc bị từ chối khi chưa bật cờ"

new_scenario invalid
for v in '1.3.0; touch PWNED' 'v1.3.0' '../1.3.0' '1.3' '' '1.3.0-beta.1' $'1.3.0\n9.9.9'; do
    run_in_scenario "$ROOT/docker/updater/smm-update" "$v" && fail "chuỗi lạ '$v' được chấp nhận"
    assert_status failed "không hợp lệ"
done
[[ ! -e "$S/PWNED" && ! -e "$PWD/PWNED" && ! -e "$S/install/PWNED" ]] || fail "chuỗi version bị thực thi"
[[ -z "$(jq -r '.to' "$S/updates/status.json")" ]] || fail "chuỗi lạ lọt vào status.to"
ok "chuỗi version lạ bị từ chối nghiêm ngặt (không thực thi, không lọt vào status)"

# ---------------------------------------------------------------- 5. rc khi bật cờ

new_scenario prerelease-on
run_in_scenario env UPDATER_ALLOW_PRERELEASE=true "$ROOT/docker/updater/smm-update" 1.4.0-rc.1 || fail "rc bị từ chối dù đã bật cờ:
$(cat "$S/out.txt")"
assert_status success
grep -q '^APP_VERSION=v1.4.0-rc.1$' "$S/install/.env" || fail "APP_VERSION rc sai"
ok "rc cập nhật được khi bật UPDATER_ALLOW_PRERELEASE=true"

# ---------------------------------------------------------------- 6. health lỗi → khôi phục

new_scenario health
run_in_scenario "$ROOT/docker/updater/smm-update" 1.3.2 && fail "bản không healthy mà vẫn báo thành công"
assert_status failed "quay về"
assert_untouched
assert_maintenance_off
cmp -s "$S/install/docker/caddy/Caddyfile" <(printf 'old caddy\n') || fail "docker/ chưa được khôi phục"
[[ "$(log_count 'compose up -d postgres app queue scheduler caddy')" == 2 ]] || fail "phải up 2 lần (bản mới rồi khôi phục): $(cat "$LOG")"
grep -F '[up]' "$LOG" | tail -n 1 | grep -q "compose=# OLD COMPOSE" || fail "lần up cuối không dùng compose.yml cũ"
grep -F '[up]' "$LOG" | tail -n 1 | grep -q "APP_IMAGE=$OLD_APP_IMAGE" || fail "lần up cuối không dùng APP_IMAGE cũ"
log_has 'app:notify-admin' || fail "không báo admin khi phải khôi phục"
kept_dump=("$S"/backups/pre-update/smm-1.2.0-to-1.3.2-*.dump)
[[ -s "${kept_dump[0]}" ]] || fail "mất dump pre-update"
ok "health lỗi: khôi phục compose.yml/.env/docker/, up lại bản cũ, báo admin, tắt bảo trì, giữ dump"

# ---------------------------------------------------------------- 7. smoke /login lỗi → khôi phục

new_scenario smoke
run_in_scenario env SHIM_SMOKE_CODE=500 "$ROOT/docker/updater/smm-update" 1.3.3 && fail "smoke lỗi mà vẫn báo thành công"
assert_status failed "/login"
assert_untouched
assert_maintenance_off
ok "GET /login khác 200: khôi phục bản cũ"

new_scenario smoke-redirect
run_in_scenario env SHIM_SMOKE_CODE="302 http://localhost/dashboard" "$ROOT/docker/updater/smm-update" 1.3.3 && fail "/login chuyển hướng lạ mà vẫn báo thành công"
assert_status failed "/login"
assert_untouched
ok "GET /login chuyển hướng không phải /install: khôi phục bản cũ"

new_scenario not-installed
run_in_scenario env SHIM_SMOKE_CODE="302 https://panel.example.com/install" "$ROOT/docker/updater/smm-update" 1.3.3 || fail "bản chưa chạy wizard (/login → /install) bị coi là lỗi"
assert_status success
ok "bản chưa chạy xong wizard: /login → /install vẫn tính là thành công"

# ---------------------------------------------------------------- 8. không tắt được bảo trì

new_scenario off-fails
touch "$S/state/off-fails"
run_in_scenario "$ROOT/docker/updater/smm-update" 1.3.3 && fail "không tắt được bảo trì mà vẫn báo thành công"
assert_status failed "bảo trì"
ok "không tắt được bảo trì: báo failed kèm hướng dẫn, không im lặng"

# ---------------------------------------------------------------- 9. khoá chạy đồng thời

new_scenario lock
(
    exec 8> "$S/updates/update.lock"
    flock -n 8
    sleep 3
) &
holder=$!
sleep 1
run_in_scenario "$ROOT/docker/updater/smm-update" 1.3.0 && fail "chạy song song được"
wait "$holder"
[[ ! -s "$LOG" && ! -f "$S/updates/status.json" ]] || fail "lần chạy bị khoá vẫn ghi trạng thái/gọi docker"
ok "đang có lần cập nhật khác: từ chối, không động chạm"

# ---------------------------------------------------------------- 10. khởi động: lần chạy dở (running)

new_scenario recover
mkdir -p "$S/install/.update-saved/docker/caddy"
printf '# OLD COMPOSE\n' > "$S/install/.update-saved/compose.yml"
cp "$S/env.orig" "$S/install/.update-saved/.env"
printf 'old caddy\n' > "$S/install/.update-saved/docker/caddy/Caddyfile"
: > "$S/install/.update-saved/.complete"
printf '# HALF-APPLIED NEW\n' > "$S/install/compose.yml"
printf 'APP_IMAGE=%s\nAPP_VERSION=v1.3.0\n' "$NEW_APP_IMAGE" > "$S/install/.env"
printf 'new caddy\n' > "$S/install/docker/caddy/Caddyfile"
touch "$S/state/maint"
jq -n '{state: "running", step: "pull", from: "1.2.0", to: "1.3.0", started_at: 1700000000}' > "$S/updates/status.json"
run_in_scenario "$ROOT/docker/updater/smm-update" --recover || fail "--recover lỗi:
$(cat "$S/out.txt")"
assert_status failed "interrupted"
assert_maintenance_off
cmp -s "$S/env.orig" "$S/install/.env" || fail "recover không khôi phục .env"
cmp -s "$S/compose.orig" "$S/install/compose.yml" || fail "recover không khôi phục compose.yml"
[[ "$(cat "$S/install/docker/caddy/Caddyfile")" == "old caddy" ]] || fail "recover không khôi phục docker/"
[[ ! -d "$S/install/.update-saved" ]] || fail "còn .update-saved sau khi phục hồi"
log_has 'compose up -d postgres app queue scheduler caddy' || fail "recover không up lại bản cũ"
[[ "$(status_get started_at)" == 1700000000 ]] || fail "recover làm mất started_at"
ok "running dở khi khởi động: khôi phục bản đã lưu, tắt bảo trì, failed (interrupted)"

new_scenario recover-nosave
touch "$S/state/maint"
jq -n '{state: "running", step: "backup", from: "1.2.0", to: "1.3.0"}' > "$S/updates/status.json"
run_in_scenario "$ROOT/docker/updater/smm-update" --recover || fail "--recover (chưa có bản lưu) lỗi"
assert_status failed "interrupted"
assert_maintenance_off
log_has 'compose up' && fail "không có bản lưu thì không được up"
ok "running dở trước khi swap: chỉ tắt bảo trì + failed (interrupted)"

new_scenario recover-idle
jq -n '{state: "success", step: "finalize", from: "1.2.0", to: "1.3.0"}' > "$S/updates/status.json"
cp "$S/updates/status.json" "$S/status.before"
run_in_scenario "$ROOT/docker/updater/smm-update" --recover || fail "--recover khi idle lỗi"
cmp -s "$S/status.before" "$S/updates/status.json" || fail "recover sửa status không ở trạng thái running"
[[ ! -s "$LOG" ]] || fail "recover khi không có gì dở vẫn gọi docker"
ok "không có lần chạy dở: recover không làm gì"

# ---------------------------------------------------------------- 11. vòng lặp updater

new_scenario loop-recover
touch "$S/state/maint"
jq -n '{state: "running", step: "up", from: "1.2.0", to: "1.3.0"}' > "$S/updates/status.json"
run_in_scenario env LOOP_INTERVAL=1 LOOP_MAX_ITERATIONS=1 SMM_UPDATE_BIN="$ROOT/docker/updater/smm-update" "$ROOT/docker/updater/smm-updater-loop" || fail "loop lỗi:
$(cat "$S/out.txt")"
assert_status failed "interrupted"
assert_maintenance_off
[[ "$(cat "$S/updates/heartbeat")" =~ ^[0-9]+$ ]] || fail "heartbeat không phải epoch"
(($(date +%s) - $(cat "$S/updates/heartbeat") < 30)) || fail "heartbeat cũ"
ok "loop khởi động: phục hồi running dở và ghi heartbeat"

new_scenario loop-request
jq -n '{version: "1.3.0", requested_by: 5, at: "2026-11-14T00:00:00+07:00"}' > "$S/updates/inbox/request.json"
run_in_scenario env LOOP_INTERVAL=1 LOOP_MAX_ITERATIONS=1 SMM_UPDATE_BIN="$ROOT/docker/updater/smm-update" "$ROOT/docker/updater/smm-updater-loop" || fail "loop lỗi:
$(cat "$S/out.txt")"
assert_status success
[[ ! -e "$S/updates/inbox/request.json" ]] || fail "request.json chưa được tiêu thụ"
grep -q '^APP_VERSION=v1.3.0$' "$S/install/.env" || fail "loop không cập nhật theo request"
ok "loop nhận request.json, chạy smm-update, xoá request"

new_scenario loop-badrequest
printf '{"version": "1.3.0; id"}' > "$S/updates/inbox/request.json"
run_in_scenario env LOOP_INTERVAL=1 LOOP_MAX_ITERATIONS=1 SMM_UPDATE_BIN="$ROOT/docker/updater/smm-update" "$ROOT/docker/updater/smm-updater-loop" || true
assert_status failed "không hợp lệ"
[[ ! -e "$S/updates/inbox/request.json" ]] || fail "request.json lạ không bị xoá"
assert_untouched
ok "request.json có version lạ: failed, không chạy gì"

new_scenario loop-option
jq -n '{version: "--recover"}' > "$S/updates/inbox/request.json"
jq -n '{state: "success", step: "finalize", from: "1.2.0", to: "1.3.0"}' > "$S/updates/status.json"
run_in_scenario env LOOP_INTERVAL=1 LOOP_MAX_ITERATIONS=1 SMM_UPDATE_BIN="$ROOT/docker/updater/smm-update" "$ROOT/docker/updater/smm-updater-loop" || true
assert_status failed "không hợp lệ"
ok "request.json có version dạng tuỳ chọn (--recover): không thành tham số của smm-update"

# App bị chiếm quyền (www-data) đặt symlink trong thư mục chung để updater (root) cắt/ghi đè file trên host.
new_scenario symlink
mkdir -p "$S/backups/hourly"
printf 'DUMP' > "$S/backups/hourly/smm-1.dump"
ln -s "$S/backups/hourly/smm-1.dump" "$S/updates/update.lock"
ln -s "$S/install/.env" "$S/updates/update.log"
ln -s "$S/install/compose.yml" "$S/updates/history.jsonl"
run_in_scenario "$ROOT/docker/updater/smm-update" x && fail "chuỗi lạ được chấp nhận"
assert_status failed "không hợp lệ"
[[ "$(cat "$S/backups/hourly/smm-1.dump")" == DUMP ]] || fail "updater ghi đè file backup qua symlink update.lock"
assert_untouched
[[ "$(stat -c %a "$S/install/.env" 2> /dev/null || stat -f %Lp "$S/install/.env")" == 600 ]] || fail ".env bị đổi quyền qua symlink update.log"
for f in update.lock update.log history.jsonl; do
    [[ ! -L "$S/updates/$f" ]] || fail "symlink $f trong thư mục chung không bị gỡ"
done
grep -q 'OLD COMPOSE' "$S/updates/history.jsonl" && fail "updater chép nội dung file trên host vào history.jsonl"
ok "symlink do app đặt trong thư mục chung bị gỡ, không cắt/đọc/ghi file trên host"

# Lưu bản cấu hình lỗi giữa chừng (vd đầy đĩa): không được đánh dấu .complete rồi tiếp tục đổi bundle.
new_scenario save-fails
cat > "$S/state/cp" << 'CP'
#!/usr/bin/env bash
for a in "$@"; do [[ "$a" == */.update-saved/docker ]] && exit 1; done
exec /bin/cp "$@"
CP
chmod +x "$S/state/cp"
run_in_scenario env PATH="$S/state:$WORK/bin:$PATH" "$ROOT/docker/updater/smm-update" 1.3.0 && fail "lưu bản cấu hình lỗi mà vẫn cập nhật"
assert_status failed "Không lưu được"
assert_maintenance_off
log_has 'compose pull' && fail "vẫn pull/up dù chưa lưu được bản cấu hình"
cmp -s "$S/env.orig" "$S/install/.env" || fail ".env bị đổi dù chưa lưu được bản cấu hình"
cmp -s "$S/compose.orig" "$S/install/compose.yml" || fail "compose.yml bị đổi dù chưa lưu được bản cấu hình"
ok "lưu bản cấu hình lỗi: dừng trước khi đổi bundle, không đánh dấu bản lưu hoàn chỉnh"

# ---------------------------------------------------------------- 11b. giới hạn thời gian và trạng thái không kẹt "running"

# smm-update giả: ghi "running" rồi treo hoặc bị SIGKILL (OOM); --recover chuyển cho bản thật.
cat > "$WORK/fake-update" << 'FAKE'
#!/usr/bin/env bash
if [[ "$1" == --recover ]]; then exec "$REAL_UPDATE" "$@"; fi
jq -n '{state: "running", step: "pull", from: "1.2.0", to: "1.3.0", started_at: 1700000000}' > "$UPDATES_DIR/status.json"
touch "$SHIM_STATE/maint"
if [[ "$FAKE_MODE" == kill ]]; then kill -9 $$; fi
exec sleep 60
FAKE
chmod +x "$WORK/fake-update"
run_loop_once() { # <biến môi trường...>: một vòng loop với request 1.3.0
    run_in_scenario env LOOP_INTERVAL=1 LOOP_MAX_ITERATIONS=1 REAL_UPDATE="$ROOT/docker/updater/smm-update" "$@" "$ROOT/docker/updater/smm-updater-loop" || true
}

new_scenario timeout-hang
jq -n '{version: "1.3.0"}' > "$S/updates/inbox/request.json"
begin="$(date +%s)"
run_loop_once FAKE_MODE=hang UPDATE_TIMEOUT=2 UPDATE_KILL_AFTER=2 SMM_UPDATE_BIN="$WORK/fake-update"
(($(date +%s) - begin < 30)) || fail "loop không cắt lần chạy treo"
assert_status failed "thời gian tối đa"
assert_maintenance_off
ok "lần chạy treo quá giờ: bị dừng, status failed nêu lý do quá thời gian, bảo trì tắt"

new_scenario timeout-killed
jq -n '{version: "1.3.0"}' > "$S/updates/inbox/request.json"
run_loop_once FAKE_MODE=kill UPDATE_TIMEOUT=60 SMM_UPDATE_BIN="$WORK/fake-update"
assert_status failed "interrupted"
assert_maintenance_off
[[ "$(status_get state)" != running ]] || fail "status kẹt ở running sau khi smm-update bị SIGKILL"
ok "smm-update bị SIGKILL: loop phục hồi ngay, status không kẹt running"

# Bị cắt giữa chừng sau khi đã đổi bundle: smm-update tự khôi phục bản cũ và nêu đúng lý do.
new_scenario timeout-rollback
jq -n '{version: "1.3.0"}' > "$S/updates/inbox/request.json"
run_loop_once SHIM_HANG_PULL=1 UPDATE_TIMEOUT=8 UPDATE_KILL_AFTER=60 SMM_UPDATE_BIN="$ROOT/docker/updater/smm-update"
assert_status failed "thời gian tối đa"
assert_status failed "quay về"
assert_untouched
assert_maintenance_off
ok "quá giờ giữa lúc pull: khôi phục bản cũ, status nêu lý do quá thời gian"

# ---------------------------------------------------------------- 11c. APP_VERSION thiếu/sai: từ chối (không coi là 0.0.0)

new_scenario no-version
grep -v '^APP_VERSION=' "$S/env.orig" > "$S/install/.env"
chmod 600 "$S/install/.env"
cp "$S/install/.env" "$S/env.orig"
run_in_scenario "$ROOT/docker/updater/smm-update" 1.3.0 && fail "thiếu APP_VERSION mà vẫn cập nhật"
assert_status failed "APP_VERSION"
[[ ! -s "$LOG" ]] || fail "gọi docker dù thiếu APP_VERSION: $(cat "$LOG")"
assert_untouched
ok "thiếu APP_VERSION: từ chối (failed + lý do), không đụng hệ thống"

for bad in latest v1.2 'v1.2.0; id' ''; do
    new_scenario bad-version
    sed -i.bak "s|^APP_VERSION=.*|APP_VERSION=$bad|" "$S/install/.env" && rm -f "$S/install/.env.bak"
    cp "$S/install/.env" "$S/env.orig"
    run_in_scenario "$ROOT/docker/updater/smm-update" 1.3.0 && fail "APP_VERSION='$bad' mà vẫn cập nhật"
    assert_status failed "APP_VERSION"
    [[ -z "$(status_get from)" ]] || fail "giá trị APP_VERSION lạ lọt vào status.from"
    [[ ! -s "$LOG" ]] || fail "gọi docker với APP_VERSION='$bad'"
    assert_untouched
done
ok "APP_VERSION sai định dạng: từ chối, không lọt vào status"

# ---------------------------------------------------------------- 11d. mọi service của release (trừ updater)

new_scenario services
run_in_scenario env SHIM_SERVICES="postgres app queue scheduler caddy redis updater" "$ROOT/docker/updater/smm-update" 1.3.0 || fail "cập nhật lỗi:
$(cat "$S/out.txt")"
assert_status success
grep -q 'compose pull postgres app queue scheduler caddy redis$' "$LOG" || fail "pull không gồm đủ service (redis), hoặc có updater:
$(cat "$LOG")"
grep -q 'compose up -d postgres app queue scheduler caddy redis$' "$LOG" || fail "up không gồm đủ service (redis), hoặc có updater"
grep -E 'compose (pull|up -d) ' "$LOG" | grep -q updater && fail "updater bị pull/up cùng release"
ok "up/pull dùng mọi service của compose.yml bản mới trừ updater"

new_scenario services-rollback
run_in_scenario env SHIM_SERVICES="app queue redis updater" "$ROOT/docker/updater/smm-update" 1.3.2 && fail "bản broken báo thành công"
[[ "$(log_count 'compose up -d app queue redis')" == 2 ]] || fail "khôi phục phải up lại đủ service:
$(cat "$LOG")"
ok "khôi phục cũng up đủ service (trừ updater)"

new_scenario services-noapp
run_in_scenario env SHIM_SERVICES="queue updater" "$ROOT/docker/updater/smm-update" 1.3.3 && fail "compose không có app mà vẫn cập nhật"
assert_status failed "service"
log_has 'compose up' && fail "đã up dù compose.yml mới không có service app"
ok "compose.yml mới không có service app: dừng, không up"

# ---------------------------------------------------------------- 12. update.sh (host)

new_scenario host
install -m 755 "$ROOT/deploy/update.sh" "$S/install/update.sh"
run_host() {
    env PATH="$WORK/bin:$PATH" INSTALL_DIR="$S/install" UPDATES_DIR="$S/updates" SHIM_LOG="$LOG" SHIM_STATE="$S/state" \
        RELEASE_REPO="jhin1m/smm-panel" RELEASE_DOWNLOAD_BASE="file://$SERVE" "$@" > "$S/out.txt" 2>&1
}
run_host "$S/install/update.sh" v1.3.0 || fail "update.sh lỗi:
$(cat "$S/out.txt")"
grep -q "^UPDATER_IMAGE=$UPDATER_IMAGE\$" "$S/install/.env" || fail "update.sh không đặt UPDATER_IMAGE theo release.json"
grep -q '^DB_PASSWORD=s3cret-pass$' "$S/install/.env" || fail "update.sh làm mất secret .env"
pull="$(log_line 'compose pull updater')"
run="$(log_line 'compose run --rm --no-deps updater smm-update 1.3.0')"
up="$(log_line 'compose up -d updater')"
[[ -n "$pull" && -n "$run" && -n "$up" ]] || fail "update.sh thiếu lệnh:
$(cat "$LOG")"
((pull < run && run < up)) || fail "update.sh sai thứ tự pull/run/up"
ok "update.sh: đổi UPDATER_IMAGE, pull updater, chạy smm-update trong image mới, up -d updater"

new_scenario host-fail
install -m 755 "$ROOT/deploy/update.sh" "$S/install/update.sh"
run_host env SHIM_RUN_RC=1 "$S/install/update.sh" 1.3.0 && fail "update.sh báo thành công dù smm-update lỗi"
log_has 'compose up -d updater' || fail "update.sh phải up -d updater kể cả khi cập nhật lỗi"
ok "update.sh: smm-update lỗi → mã thoát khác 0 nhưng vẫn đưa updater sang image mới"

# Updater trong web đang chạy một lần cập nhật (running + heartbeat tươi) hoặc có request chờ: update.sh không được đụng vào.
new_scenario host-busy-running
install -m 755 "$ROOT/deploy/update.sh" "$S/install/update.sh"
jq -n '{state: "running", step: "pull"}' > "$S/updates/status.json"
date +%s > "$S/updates/heartbeat"
run_host "$S/install/update.sh" 1.3.0 && fail "update.sh chạy khi updater đang cập nhật"
grep -q 'Updater đang có' "$S/out.txt" || fail "thiếu thông báo rõ ràng:
$(cat "$S/out.txt")"
cmp -s "$S/env.orig" "$S/install/.env" || fail "update.sh sửa .env khi updater bận"
{ log_has 'compose pull' || log_has 'compose run' || log_has 'compose up'; } && fail "update.sh động vào compose khi updater bận"
ok "update.sh: updater đang chạy (running + heartbeat tươi) thì từ chối, không đổi gì"

new_scenario host-busy-request
install -m 755 "$ROOT/deploy/update.sh" "$S/install/update.sh"
jq -n '{version: "1.3.0"}' > "$S/updates/inbox/request.json"
run_host "$S/install/update.sh" 1.3.0 && fail "update.sh chạy khi có request chờ"
{ log_has 'compose pull' || log_has 'compose run' || log_has 'compose up'; } && fail "update.sh động vào compose khi có request chờ"
ok "update.sh: có request chờ trong inbox thì từ chối"

# running nhưng heartbeat đã cũ (updater chết) và status success: không coi là bận.
new_scenario host-stale
install -m 755 "$ROOT/deploy/update.sh" "$S/install/update.sh"
jq -n '{state: "running", step: "pull"}' > "$S/updates/status.json"
echo $(($(date +%s) - 600)) > "$S/updates/heartbeat"
run_host "$S/install/update.sh" 1.3.0 || fail "update.sh từ chối dù heartbeat đã cũ:
$(cat "$S/out.txt")"
new_scenario host-idle
install -m 755 "$ROOT/deploy/update.sh" "$S/install/update.sh"
jq -n '{state: "success", step: "finalize"}' > "$S/updates/status.json"
date +%s > "$S/updates/heartbeat"
run_host "$S/install/update.sh" 1.3.0 || fail "update.sh từ chối dù updater rảnh:
$(cat "$S/out.txt")"
ok "update.sh: heartbeat cũ hoặc updater rảnh thì vẫn chạy"

new_scenario host-bad
install -m 755 "$ROOT/deploy/update.sh" "$S/install/update.sh"
mkdir -p "$WORK/serve-bad"
cp -r "$SERVE/v1.3.0" "$WORK/serve-bad/v1.3.0"
sed -i.bak 's/"app_image"/"app_imag"/' "$WORK/serve-bad/v1.3.0/release.json"
rm -f "$WORK/serve-bad/v1.3.0/release.json.bak"
run_host env RELEASE_DOWNLOAD_BASE="file://$WORK/serve-bad" "$S/install/update.sh" 1.3.0 && fail "update.sh chấp nhận release.json bị sửa"
cmp -s "$S/env.orig" "$S/install/.env" || fail "update.sh sửa .env dù release.json sai checksum"
{ log_has 'compose pull' || log_has 'compose run' || log_has 'compose up'; } && fail "update.sh chạy docker compose dù release.json sai checksum"
run_host "$S/install/update.sh" '1.3.0; id' && fail "update.sh nhận version lạ"
ok "update.sh: release.json sai checksum / version lạ bị từ chối, .env nguyên vẹn"

printf '\nTất cả kiểm tra luồng cập nhật đạt.\n'
