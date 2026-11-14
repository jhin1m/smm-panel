#!/usr/bin/env bash
# Cài SMM Panel lên VPS Ubuntu 22.04+/Debian 12+ bằng Docker. Chạy bằng root:
#   curl -fsSL https://raw.githubusercontent.com/jhin1m/smm-panel/main/deploy/install.sh | sudo bash -s -- --domain panel.example.com
#
# Việc script làm: cài Docker (nếu thiếu) -> tải bundle release + kiểm checksum -> sinh .env (secret ngẫu nhiên)
# -> docker compose pull && up -d -> chờ app healthy -> in URL wizard /install?token=...
# Chạy lại an toàn: giữ nguyên .env (APP_KEY, DB_PASSWORD), không ghi đè bundle đang chạy.
#
# Tham số:
#   --dir <đường dẫn>   thư mục cài (mặc định /opt/smm-panel)
#   --version <tag>     phiên bản (vd v1.0.0); bỏ trống/latest = bản stable mới nhất
#   --domain <tên miền> tên miền đã trỏ A record về VPS
#   --yes               trả lời "có" cho mọi câu hỏi (cần --domain nếu cài mới)
#   --dry-run           chỉ in các bước sẽ làm, không cài/ghi gì ra ngoài thư mục tạm
set -euo pipefail

# Repo phát hành; đổi RELEASE_REPO để cài từ fork hoặc để kiểm thử với máy chủ giả.
RELEASE_REPO="${RELEASE_REPO:-jhin1m/smm-panel}"
RELEASE_API_URL="${RELEASE_API_URL:-https://api.github.com/repos/${RELEASE_REPO}/releases/latest}"
RELEASE_DOWNLOAD_BASE="${RELEASE_DOWNLOAD_BASE:-https://github.com/${RELEASE_REPO}/releases/download}"

DEFAULT_DIR="/opt/smm-panel"
DEFAULT_BACKUP_DIR="/srv/backups/smm-panel"
DB_VOLUME="smm-panel_pgdata"   # khớp `name: smm-panel` + volume pgdata trong compose.yml
HEALTH_TIMEOUT=180

INSTALL_DIR="$DEFAULT_DIR"
REQUESTED_VERSION=""
DOMAIN=""
ASSUME_YES=0
DRY_RUN=0
TMP_DIR=""
APP_IMAGE=""
UPDATER_IMAGE=""
BUNDLE_TARBALL=""

# ---------------------------------------------------------------- tiện ích

msg() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33mCẢNH BÁO:\033[0m %s\n' "$*" >&2; }
die() { printf '\033[1;31mLỖI:\033[0m %s\n' "$*" >&2; exit 1; }

cleanup() {
    if [[ -n "$TMP_DIR" && -d "$TMP_DIR" ]]; then
        rm -rf "$TMP_DIR"
    fi
}

usage() {
    # Chạy qua `curl ... | bash` thì không có file nguồn để đọc phần đầu: in tóm tắt tham số.
    local source="${BASH_SOURCE[0]:-}"
    if [[ -n "$source" && -r "$source" ]]; then
        sed -n '2,/^set -euo/{/^set -euo/!p;}' "$source" | sed 's/^# \{0,1\}//'
    else
        printf 'Tham số: --dir <đường dẫn> --version <tag> --domain <tên miền> --yes --dry-run\n'
    fi
}

# Chạy lệnh có tác dụng phụ; --dry-run chỉ in ra.
run() {
    if [[ "$DRY_RUN" == 1 ]]; then
        printf '[dry-run] %s\n' "$*"
        return 0
    fi
    "$@"
}

# Hỏi có/không (mặc định không). --yes tự trả lời có. Không có terminal và không --yes -> dừng.
confirm() {
    local question="$1" answer
    if [[ "$ASSUME_YES" == 1 ]]; then
        return 0
    fi
    if [[ ! -r /dev/tty ]]; then
        die "Cần xác nhận: \"$question\" nhưng không có terminal. Chạy lại với --yes nếu đồng ý."
    fi
    read -r -p "$question [y/N] " answer < /dev/tty
    [[ "$answer" =~ ^[yY]$ ]]
}

# Hỏi giá trị; --yes hoặc không có terminal -> trả lỗi để nơi gọi báo thiếu tham số.
ask_value() {
    local question="$1" answer
    if [[ "$ASSUME_YES" == 1 || ! -r /dev/tty ]]; then
        return 1
    fi
    read -r -p "$question " answer < /dev/tty
    printf '%s' "$answer"
}

# env_get <file> <KEY>: in giá trị (bỏ nháy) của dòng KEY=... cuối cùng; rỗng nếu không có.
env_get() {
    local file="$1" key="$2" line
    [[ -f "$file" ]] || return 0
    line="$(grep -E "^${key}=" "$file" | tail -n 1 || true)"
    line="${line#*=}"
    line="${line%\"}"
    line="${line#\"}"
    printf '%s' "$line"
}

# env_set <file> <KEY> <value>: thay dòng KEY=... (hoặc thêm cuối file). Dùng ENVIRON để awk không diễn giải ký tự '\'.
env_set() {
    local file="$1" key="$2" value="$3" tmp
    tmp="$(mktemp "${file}.XXXXXX")"
    ENV_K="$key" ENV_V="$value" awk '
        BEGIN { k = ENVIRON["ENV_K"]; v = ENVIRON["ENV_V"]; done = 0 }
        index($0, k "=") == 1 { print k "=" v; done = 1; next }
        { print }
        END { if (!done) print k "=" v }
    ' "$file" > "$tmp"
    chmod 600 "$tmp"
    mv "$tmp" "$file"
}

# ---------------------------------------------------------------- kiểm tra môi trường

check_root() {
    if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
        if [[ "$DRY_RUN" == 1 ]]; then
            warn "Không chạy bằng root (bình thường với --dry-run). Cài thật cần: sudo bash install.sh ..."
            return 0
        fi
        die "Cần chạy bằng root: sudo bash install.sh ..."
    fi
}

check_os() {
    local id="" version="" major="" supported=0
    if [[ -r /etc/os-release ]]; then
        # shellcheck disable=SC1091
        id="$(. /etc/os-release && printf '%s' "${ID:-}")"
        # shellcheck disable=SC1091
        version="$(. /etc/os-release && printf '%s' "${VERSION_ID:-}")"
    fi
    major="${version%%.*}"

    case "$id" in
        ubuntu) [[ "$major" =~ ^[0-9]+$ && "$major" -ge 22 ]] && supported=1 ;;
        debian) [[ "$major" =~ ^[0-9]+$ && "$major" -ge 12 ]] && supported=1 ;;
    esac

    if [[ "$supported" == 1 ]]; then
        msg "Hệ điều hành: $id $version"
        return 0
    fi
    warn "Hệ điều hành '${id:-không rõ} ${version}' chưa được kiểm thử (hỗ trợ: Ubuntu 22.04+, Debian 12+)."
    confirm "Vẫn tiếp tục?" || die "Đã dừng."
}

check_memory() {
    local kb=""
    if [[ -r /proc/meminfo ]]; then
        kb="$(awk '/^MemTotal:/ {print $2}' /proc/meminfo)"
    fi
    if [[ "$kb" =~ ^[0-9]+$ && "$kb" -lt 900000 ]]; then
        warn "RAM $((kb / 1024)) MB < 1 GB: dễ bị OOM. Nên dùng VPS tối thiểu 1 GB (khuyến nghị 2 GB)."
    fi
}

port_in_use() {
    local port="$1"
    command -v ss > /dev/null 2>&1 || return 1
    ss -ltn 2> /dev/null | awk 'NR > 1 {print $4}' | grep -Eq "[:.]${port}\$"
}

check_ports() {
    local port busy=()
    for port in 80 443; do
        if port_in_use "$port"; then
            busy+=("$port")
        fi
    done
    if [[ "${#busy[@]}" -gt 0 ]]; then
        die "Cổng ${busy[*]} đang bị chương trình khác dùng (nginx/apache?). Caddy cần 80 và 443 để lấy chứng chỉ HTTPS; hãy tắt chương trình đó trước."
    fi
}

ensure_tools() {
    local missing=() tool
    for tool in curl tar openssl sha256sum awk; do
        command -v "$tool" > /dev/null 2>&1 || missing+=("$tool")
    done
    [[ "${#missing[@]}" -eq 0 ]] && return 0

    msg "Thiếu công cụ: ${missing[*]}"
    if command -v apt-get > /dev/null 2>&1; then
        run apt-get update -qq
        run apt-get install -y -qq curl ca-certificates openssl tar coreutils gawk
    elif [[ "$DRY_RUN" != 1 ]]; then
        die "Hãy cài thủ công: ${missing[*]}"
    fi
}

ensure_docker() {
    if command -v docker > /dev/null 2>&1; then
        msg "Docker: $(docker --version 2> /dev/null || echo 'đã cài')"
    else
        msg "Chưa có Docker, cài qua https://get.docker.com"
        if [[ "$DRY_RUN" == 1 ]]; then
            printf '[dry-run] curl -fsSL https://get.docker.com -o <tmp>/get-docker.sh && sh <tmp>/get-docker.sh\n'
            return 0
        fi
        curl -fsSL --retry 3 --connect-timeout 15 https://get.docker.com -o "$TMP_DIR/get-docker.sh"
        sh "$TMP_DIR/get-docker.sh"
    fi

    if [[ "$DRY_RUN" != 1 ]] && ! docker compose version > /dev/null 2>&1; then
        die "Thiếu Docker Compose plugin. Cài 'docker-compose-plugin' (apt) rồi chạy lại."
    fi
}

# ---------------------------------------------------------------- tên miền

validate_domain() {
    [[ "$1" =~ ^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$ ]]
}

resolve_domain() {
    local existing="$1"
    if [[ -n "$existing" ]]; then
        DOMAIN="$existing"
        msg "Tên miền (từ .env hiện có): $DOMAIN"
        return 0
    fi

    if [[ -z "$DOMAIN" ]]; then
        DOMAIN="$(ask_value 'Tên miền của panel (vd panel.example.com):')" || die "Thiếu --domain."
    fi
    DOMAIN="$(printf '%s' "$DOMAIN" | tr '[:upper:]' '[:lower:]')"
    validate_domain "$DOMAIN" || die "Tên miền không hợp lệ: '$DOMAIN' (chỉ hostname, không có http:// hay /)."
}

check_dns() {
    local domain="$1" resolved="" public_ip=""
    resolved="$(getent ahostsv4 "$domain" 2> /dev/null | awk 'NR == 1 {print $1}' || true)"
    public_ip="$(curl -fsS --max-time 5 https://api.ipify.org 2> /dev/null || true)"

    if [[ -z "$resolved" ]]; then
        warn "Tên miền $domain chưa phân giải được. Caddy sẽ không lấy được chứng chỉ HTTPS cho tới khi A record trỏ về VPS."
        confirm "Vẫn tiếp tục?" || die "Đã dừng. Trỏ DNS rồi chạy lại."
    elif [[ -z "$public_ip" ]]; then
        msg "DNS $domain -> $resolved (không kiểm được IP công khai của VPS, bỏ qua so sánh)."
    elif [[ "$resolved" != "$public_ip" ]]; then
        warn "DNS $domain trỏ tới $resolved nhưng IP công khai của VPS là $public_ip (nếu đang dùng Cloudflare proxy thì bình thường)."
        confirm "Vẫn tiếp tục?" || die "Đã dừng. Sửa DNS rồi chạy lại."
    else
        msg "DNS $domain -> $resolved (khớp IP VPS)."
    fi
}

# ---------------------------------------------------------------- phiên bản + bundle

validate_tag() {
    [[ "$1" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+([-.][0-9A-Za-z.]+)?$ ]]
}

# Phân giải --version (hoặc bản stable mới nhất qua Releases API) thành tag cụ thể; in ra tag. Lỗi mạng -> trả 1.
resolve_version() {
    local requested="$1" tag=""
    if [[ -n "$requested" && "$requested" != "latest" ]]; then
        printf '%s' "$requested"
        return 0
    fi

    tag="$(curl -fsSL --retry 2 --connect-timeout 15 -H 'Accept: application/vnd.github+json' "$RELEASE_API_URL" 2> /dev/null \
        | sed -n 's/.*"tag_name"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -n 1 || true)"
    [[ -n "$tag" ]] || return 1
    validate_tag "$tag" || return 1
    printf '%s' "$tag"
}

# verify_checksum <thư mục> <file...>: mỗi file PHẢI có dòng trong checksums.txt và khớp sha256.
verify_checksum() {
    local dir="$1" file
    shift
    [[ -s "$dir/checksums.txt" ]] || die "Thiếu checksums.txt."
    for file in "$@"; do
        grep -E "[[:space:]]\*?${file//./\\.}\$" "$dir/checksums.txt" > "$dir/.check-line" \
            || die "checksums.txt không có dòng cho $file."
        (cd "$dir" && sha256sum -c .check-line > /dev/null 2>&1) \
            || die "Checksum SAI cho $file. Bundle có thể bị hỏng hoặc bị can thiệp; không cài."
    done
    rm -f "$dir/.check-line"
}

# release_json_get <file> <key>: lấy giá trị chuỗi của khoá trong release.json (không cần jq).
release_json_get() {
    sed -n "s/.*\"$2\"[[:space:]]*:[[:space:]]*\"\\([^\"]*\\)\".*/\\1/p" "$1" | head -n 1
}

# Image phải ghim theo digest: <repo>[:tag]@sha256:<64 hex>.
valid_image_ref() {
    [[ "$1" =~ ^[a-z0-9][a-z0-9./_-]*(:[A-Za-z0-9._-]+)?@sha256:[0-9a-f]{64}$ ]]
}

# Tarball không được có đường dẫn tuyệt đối hay '..'.
check_tar_paths() {
    if tar -tzf "$1" | grep -Eq '(^/|(^|/)\.\.(/|$))'; then
        die "Bundle chứa đường dẫn không an toàn; dừng."
    fi
}

# download_bundle <tag>: tải bundle + checksums.txt + release.json vào $TMP_DIR/bundle, kiểm checksum, xác thực image.
download_bundle() {
    local tag="$1" base="$RELEASE_DOWNLOAD_BASE/$1" out="$TMP_DIR/bundle" asset
    local tarball="smm-panel-deploy-${tag}.tar.gz"
    mkdir -p "$out"

    for asset in "$tarball" checksums.txt release.json; do
        msg "Tải $asset"
        curl -fsSL --retry 3 --connect-timeout 15 "$base/$asset" -o "$out/$asset" \
            || die "Không tải được $base/$asset"
    done

    verify_checksum "$out" "$tarball" release.json
    msg "Checksum bundle: OK"

    APP_IMAGE="$(release_json_get "$out/release.json" app_image)"
    UPDATER_IMAGE="$(release_json_get "$out/release.json" updater_image)"
    valid_image_ref "$APP_IMAGE" || die "release.json: app_image thiếu hoặc không ghim theo digest ('${APP_IMAGE}')."
    valid_image_ref "$UPDATER_IMAGE" || die "release.json: updater_image thiếu hoặc không ghim theo digest ('${UPDATER_IMAGE}')."

    check_tar_paths "$out/$tarball"
    BUNDLE_TARBALL="$out/$tarball"
}

extract_bundle() {
    mkdir -p "$INSTALL_DIR"
    tar -xzf "$BUNDLE_TARBALL" -C "$INSTALL_DIR" --no-same-owner
    [[ -f "$INSTALL_DIR/compose.yml" && -f "$INSTALL_DIR/.env.template" ]] \
        || die "Bundle thiếu compose.yml hoặc .env.template."
    mkdir -p "$INSTALL_DIR/custom"
}

# ---------------------------------------------------------------- .env

# create_env <tag> <backup_dir>: .env mới từ .env.template, secret sinh ngẫu nhiên, quyền 600.
create_env() {
    local tag="$1" backup_dir="$2" env_file="$INSTALL_DIR/.env"
    umask 077
    cp "$INSTALL_DIR/.env.template" "$env_file"
    chmod 600 "$env_file"

    env_set "$env_file" APP_URL "https://$DOMAIN"
    env_set "$env_file" APP_DOMAIN "$DOMAIN"
    env_set "$env_file" APP_KEY "base64:$(openssl rand -base64 32)"
    env_set "$env_file" DB_PASSWORD "$(openssl rand -hex 24)"
    env_set "$env_file" INSTALL_TOKEN "$(openssl rand -hex 24)"
    env_set "$env_file" APP_VERSION "$tag"
    env_set "$env_file" APP_IMAGE "$APP_IMAGE"
    env_set "$env_file" UPDATER_IMAGE "$UPDATER_IMAGE"
    env_set "$env_file" INSTALL_DIR "$INSTALL_DIR"
    env_set "$env_file" BACKUP_DIR "$backup_dir"
    chown root:root "$env_file" 2> /dev/null || true
}

# ---------------------------------------------------------------- chạy + chờ healthy

wait_healthy() {
    local cid="" status="" waited=0
    msg "Chờ dịch vụ app healthy (tối đa ${HEALTH_TIMEOUT}s)..."
    while ((waited < HEALTH_TIMEOUT)); do
        cid="$(cd "$INSTALL_DIR" && docker compose ps -q app 2> /dev/null || true)"
        if [[ -n "$cid" ]]; then
            status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$cid" 2> /dev/null || true)"
            [[ "$status" == "healthy" ]] && return 0
        fi
        sleep 5
        waited=$((waited + 5))
    done

    (cd "$INSTALL_DIR" && docker compose ps) || true
    (cd "$INSTALL_DIR" && docker compose logs --tail 40 app) || true
    die "app chưa healthy sau ${HEALTH_TIMEOUT}s. Xem log: cd $INSTALL_DIR && docker compose logs app"
}

# Wizard còn mở khi /install trả 200; đã cài thì 404.
wizard_is_open() {
    local code
    code="$(cd "$INSTALL_DIR" && docker compose exec -T app curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8080/install 2> /dev/null || true)"
    [[ "$code" == "200" ]]
}

print_summary() {
    local token
    token="$(env_get "$INSTALL_DIR/.env" INSTALL_TOKEN)"

    printf '\n'
    if [[ -n "$token" ]] && wizard_is_open; then
        msg "Hoàn tất hạ tầng. Mở đường dẫn sau để tạo tài khoản quản trị (chỉ dùng một lần):"
        printf '\n    https://%s/install?token=%s\n\n' "$DOMAIN" "$token"
    else
        msg "Hệ thống đã được cài đặt trước đó (wizard /install đã khoá). Đăng nhập: https://$DOMAIN/admin"
    fi

    cat << EOF
Gợi ý sau khi cài:
  - Backup mỗi giờ (crontab -e):  5 * * * * $INSTALL_DIR/docker/backup/backup-db.sh >> /var/log/smm-backup.log 2>&1
  - Firewall chỉ mở 22, 80, 443 (tcp) và 443 (udp).
  - Giữ file $INSTALL_DIR/.env cẩn thận (chứa APP_KEY và mật khẩu DB); mất .env thì không giải mã được dữ liệu.
EOF
}

# ---------------------------------------------------------------- main

stop_orphan_volume() {
    local env_file="$1"
    cat >&2 << EOF

LỖI: Không có $env_file nhưng volume dữ liệu '$DB_VOLUME' đã tồn tại.
Sinh secret mới sẽ làm hỏng dữ liệu cũ (APP_KEY mới không giải mã được, mật khẩu DB mới không khớp), nên script dừng lại.
Chọn một trong hai:
  1. Khôi phục file .env cũ vào $env_file (từ backup hoặc bản sao bạn đã lưu) rồi chạy lại script này.
  2. Chấp nhận mất toàn bộ dữ liệu cũ:  docker volume rm $DB_VOLUME   (sau khi 'docker compose -p smm-panel down')
     rồi chạy lại script này. Nếu cần giữ dữ liệu, restore từ file dump trong backup (xem docs/deployment.md).
EOF
    exit 3
}

main() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --dir) [[ $# -ge 2 ]] || die "--dir cần giá trị."; INSTALL_DIR="$2"; shift 2 ;;
            --version) [[ $# -ge 2 ]] || die "--version cần giá trị."; REQUESTED_VERSION="$2"; shift 2 ;;
            --domain) [[ $# -ge 2 ]] || die "--domain cần giá trị."; DOMAIN="$2"; shift 2 ;;
            --yes | -y) ASSUME_YES=1; shift ;;
            --dry-run) DRY_RUN=1; shift ;;
            -h | --help) usage; exit 0 ;;
            *) die "Tham số lạ: $1 (xem --help)" ;;
        esac
    done

    [[ "$INSTALL_DIR" == /* ]] || die "--dir phải là đường dẫn tuyệt đối."
    if [[ -n "$REQUESTED_VERSION" && "$REQUESTED_VERSION" != "latest" ]]; then
        validate_tag "$REQUESTED_VERSION" || die "Phiên bản không hợp lệ: '$REQUESTED_VERSION' (vd v1.0.0)."
    fi

    TMP_DIR="$(mktemp -d)"
    trap cleanup EXIT

    if [[ "$DRY_RUN" == 1 ]]; then
        msg "CHẾ ĐỘ --dry-run: không cài đặt hay ghi gì ngoài thư mục tạm."
    fi

    check_root
    check_os
    check_memory
    ensure_tools
    ensure_docker

    local env_file="$INSTALL_DIR/.env" existing=0 existing_version="" existing_domain=""
    if [[ -f "$env_file" ]]; then
        existing=1
        existing_version="$(env_get "$env_file" APP_VERSION)"
        existing_domain="$(env_get "$env_file" APP_DOMAIN)"
        msg "Tìm thấy $env_file: giữ nguyên secret hiện có."
    elif command -v docker > /dev/null 2>&1 && docker volume inspect "$DB_VOLUME" > /dev/null 2>&1; then
        stop_orphan_volume "$env_file"
    fi

    resolve_domain "$existing_domain"
    if [[ "$existing" == 0 ]]; then
        check_dns "$DOMAIN"
        check_ports
    fi

    # --- bundle: chỉ tải khi cài mới hoặc thiếu compose.yml; không bao giờ ghi đè bundle đang chạy
    local tag="" need_bundle=1
    if [[ "$existing" == 1 && -f "$INSTALL_DIR/compose.yml" ]]; then
        need_bundle=0
        tag="$existing_version"
        if [[ -n "$REQUESTED_VERSION" && "$REQUESTED_VERSION" != "latest" && "$REQUESTED_VERSION" != "$existing_version" ]]; then
            warn "Đang chạy ${existing_version:-không rõ}, khác --version $REQUESTED_VERSION. Không ghi đè bundle đang chạy: hãy dùng nút Cập nhật trong admin hoặc update.sh."
        fi
        msg "Giữ bundle hiện có (${existing_version:-không rõ})."
    elif [[ "$existing" == 1 ]]; then
        tag="$existing_version"
        [[ -n "$tag" ]] || die "$env_file có nhưng thiếu APP_VERSION và thiếu compose.yml; không biết tải bản nào."
        msg "Thiếu compose.yml, tải lại bundle $tag."
    elif ! tag="$(resolve_version "$REQUESTED_VERSION")"; then
        if [[ "$DRY_RUN" == 1 ]]; then
            warn "Không phân giải được bản mới nhất từ $RELEASE_API_URL (dry-run: bỏ qua)."
            tag="<bản-stable-mới-nhất>"
        else
            die "Không lấy được bản stable mới nhất từ $RELEASE_API_URL. Kiểm tra mạng, hoặc truyền --version vX.Y.Z."
        fi
    fi
    [[ "$need_bundle" == 0 ]] || msg "Phiên bản sẽ cài: $tag"

    if [[ "$need_bundle" == 1 ]]; then
        if [[ "$DRY_RUN" == 1 ]]; then
            printf '[dry-run] tải %s/%s/smm-panel-deploy-%s.tar.gz + checksums.txt + release.json\n' "$RELEASE_DOWNLOAD_BASE" "$tag" "$tag"
            printf '[dry-run] sha256sum -c checksums.txt; đọc app_image/updater_image (digest) từ release.json\n'
            printf '[dry-run] giải nén vào %s\n' "$INSTALL_DIR"
        else
            download_bundle "$tag"
            extract_bundle
        fi
    fi

    # --- .env
    local backup_dir
    if [[ "$existing" == 0 ]]; then
        backup_dir="$DEFAULT_BACKUP_DIR"
        if [[ "$DRY_RUN" == 1 ]]; then
            printf '[dry-run] tạo %s từ .env.template (APP_KEY, DB_PASSWORD, INSTALL_TOKEN ngẫu nhiên; chmod 600)\n' "$env_file"
        else
            create_env "$tag" "$backup_dir"
        fi
    else
        [[ -n "$(env_get "$env_file" APP_IMAGE)" ]] \
            || die "APP_IMAGE trong $env_file đang trống. Đặt APP_IMAGE (image release hoặc smm-panel:local) rồi chạy lại."
        backup_dir="$(env_get "$env_file" BACKUP_DIR)"
        backup_dir="${backup_dir:-$DEFAULT_BACKUP_DIR}"
    fi

    # --- chạy
    run mkdir -p "$backup_dir"
    run chmod 700 "$backup_dir"
    if [[ "$DRY_RUN" == 1 ]]; then
        printf '[dry-run] cd %s && docker compose pull && docker compose up -d\n' "$INSTALL_DIR"
        printf '[dry-run] chờ app healthy (<= %ss), rồi in https://%s/install?token=<INSTALL_TOKEN>\n' "$HEALTH_TIMEOUT" "$DOMAIN"
        msg "Dry-run xong, không có gì bị thay đổi."
        return 0
    fi

    msg "Kéo image và khởi động..."
    (cd "$INSTALL_DIR" && docker compose pull && docker compose up -d)
    wait_healthy
    print_summary
}

# Cho phép `source` file này để kiểm thử từng hàm; chỉ chạy main khi được thực thi trực tiếp.
# Qua `curl ... | bash -s --` BASH_SOURCE rỗng (set -u sẽ dừng nếu đọc thẳng): coi như thực thi trực tiếp.
if [[ "${BASH_SOURCE[0]:-$0}" == "$0" ]]; then
    main "$@"
fi
