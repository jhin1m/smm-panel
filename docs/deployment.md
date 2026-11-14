# Runbook triển khai — SMM Panel

1 VPS, Docker Compose: `app` (nginx + php-fpm) · `queue` · `scheduler` · `postgres:17` · `caddy:2` (TLS tự động).
Nguyên tắc: **sửa tiến (forward-fix) là mặc định**, migration chỉ thêm (additive), không restore DB bừa.

```
Internet ─443─> caddy (Let's Encrypt) ─8080─> app ── postgres (nội bộ, không publish cổng)
SePay ─> https://{domain}/webhooks/sepay        queue (Telegram)   scheduler (đối soát SePay 10')
cron host mỗi giờ ─> docker/backup/backup-db.sh ─> /srv/backups/smm-panel ─> off-site; lỗi → Telegram
```

Có **một** `compose.yml` chạy image dựng sẵn (`APP_IMAGE` trong `.env`). Ai tự dựng image từ source thêm `compose.build.yml`:

| Cách chạy | Lệnh | `.env` cần |
|---|---|---|
| Cài bằng `install.sh` (image release) | `docker compose …` | `APP_IMAGE=<repo>@sha256:…` (install.sh ghi) |
| Dựng từ source (instance chạy từ `git clone`, dev) | `docker compose -f compose.yml -f compose.build.yml …` | `APP_IMAGE=smm-panel:local` |

> Mẹo: với instance dựng từ source, thêm `COMPOSE_FILE=compose.yml:compose.build.yml` vào `.env` để mọi lệnh `docker compose …` (và `backup-db.sh`) tự dùng cả hai file mà không cần gõ `-f`.
> Phần còn lại của tài liệu viết `docker compose …` ngắn gọn; với instance dựng từ source hiểu là có thêm `-f compose.yml -f compose.build.yml` (hoặc `COMPOSE_FILE` như trên).

## 1. Chuẩn bị VPS

- Docker Engine + compose plugin.
- Firewall chỉ mở 22, 80, 443 (tcp) + 443/udp.
- DNS A/AAAA của tên miền trỏ về VPS (trước khi `up`, để Caddy lấy chứng chỉ).

## 2. Cài lần đầu

### 2a. Cài tự động (khuyến nghị): `deploy/install.sh` + wizard `/install`

Trên VPS Ubuntu 22.04+/Debian 12+ trống, trỏ A record của tên miền về VPS, rồi chạy bằng root:

```bash
curl -fsSL https://raw.githubusercontent.com/jhin1m/smm-panel/main/deploy/install.sh | sudo bash -s -- --domain panel.example.com
# thử trước, không cài gì:  ... --dry-run --yes --domain panel.example.com --version v1.0.0
```

Bản phát hành do workflow tạo (xem `docs/release-process.md`). Tham số: `--dir` (mặc định `/opt/smm-panel`), `--version` (tag cụ thể, mặc định bản stable mới nhất), `--domain`, `--yes`, `--dry-run`.

Script lần lượt: kiểm root/OS/RAM/cổng 80,443 → cài Docker nếu thiếu → hỏi domain + so DNS với IP công khai (cảnh báo) → tải bundle release (`smm-panel-deploy-<tag>.tar.gz`, `checksums.txt`, `release.json`) và **kiểm `sha256sum -c`**; sai checksum thì dừng → sinh `.env` (`APP_KEY`, `DB_PASSWORD`, `INSTALL_TOKEN` ngẫu nhiên; `APP_IMAGE`/`UPDATER_IMAGE` theo digest từ `release.json`; chmod 600) → `docker compose pull && up -d` → chờ `app` healthy (≤ 3 phút) → in `https://<domain>/install?token=<INSTALL_TOKEN>`.

Mở URL đó: wizard 5 bước (token + ngôn ngữ → thông tin site → tài khoản admin → ngân hàng/SMTP, bỏ qua được → hoàn tất). Hoàn tất ghi settings, tạo admin và **khoá vĩnh viễn** `/install` (và mọi action Livewire của wizard) → 404. Sau đó đăng nhập `https://<domain>/admin`, **thiết lập MFA ngay** và cất recovery codes.

Chạy lại `install.sh` an toàn: giữ nguyên `.env` (không đổi `APP_KEY`/`DB_PASSWORD`), không ghi đè bundle đang chạy (muốn lên bản mới dùng nút Cập nhật/`update.sh`). Nếu **mất `.env` nhưng volume `smm-panel_pgdata` còn**, script dừng và in hướng dẫn (khôi phục `.env` cũ, hoặc chấp nhận xoá dữ liệu): không bao giờ sinh secret mới cho DB cũ.

`INSTALL_TOKEN` chỉ có tác dụng khi chưa cài; sau khi cài có thể xoá dòng này khỏi `.env`.

### 2b. Cài tay từ source (dựng image tại VPS)

```bash
git clone <repo> /srv/smm-panel && cd /srv/smm-panel
cp .env.production.example .env        # đã có APP_IMAGE=smm-panel:local
# Sinh APP_KEY (chạy 1 lần, dán vào .env):
docker run --rm -v "$PWD":/var/www/html -w /var/www/html serversideup/php:8.4-cli php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
# Điền: APP_URL, APP_DOMAIN, DB_PASSWORD, INSTALL_TOKEN (openssl rand -hex 24), MAIL_*, GOOGLE_*, SEPAY_WEBHOOK_SECRET
# SEPAY_API_TOKEN: CHỈ điền sau khi smoke test thật (mục 9) xác nhận id + mã tham chiếu của User API
# trùng với webhook. Trống = lệnh đối soát tự động không chạy.
echo 'COMPOSE_FILE=compose.yml:compose.build.yml' >> .env    # tuỳ chọn, xem mẹo ở đầu tài liệu
docker compose -f compose.yml -f compose.build.yml up -d --build   # app tự migrate --force khi khởi động
docker compose ps                                # mọi service healthy/running
curl -I https://<domain>/up                      # 200
```

Rồi mở `https://<domain>/install?token=<INSTALL_TOKEN>` để chạy wizard (như 2a). Có thể tạo admin bằng lệnh thay cho wizard:
`docker compose exec app php artisan app:make-admin` (hỏi email/tên/mật khẩu, nhập ẩn). Khi đã có admin, DB được coi là đã cài (không bị đẩy về `/install`).
Admin chỉ đăng nhập ở `/admin` (đăng nhập ở `/login` bị từ chối).

### 2c. Instance đang chạy (đã có admin) nâng cấp lên bản có wizard

Migration `create_install_settings` tự đánh dấu **đã cài** vì đã có `users.is_admin = true`: trang khách không bị chuyển về `/install`, `/install` trả 404, `migrate` không lỗi.

Compose đã gộp về một file dùng `image: ${APP_IMAGE}`. Instance dựng từ source cần thêm `APP_IMAGE` vào `.env` **trước khi `git pull`** bản này: thiếu biến thì mọi lệnh `docker compose` đều lỗi, kể cả `pg_dump` và cảnh báo Telegram trong `backup-db.sh` chạy theo cron, nên backup sẽ hỏng âm thầm. Làm **một lần** (project name `smm-panel` và tên volume `pgdata`, `app_storage`, `caddy_data`, `caddy_config` giữ nguyên nên **dữ liệu không đổi**):

```bash
cd /srv/smm-panel
echo 'APP_IMAGE=smm-panel:local' >> .env                 # TRƯỚC git pull
git pull
echo 'COMPOSE_FILE=compose.yml:compose.build.yml' >> .env      # tuỳ chọn
docker compose -f compose.yml -f compose.build.yml config -q    # phải im lặng (không báo thiếu biến)
docker compose -f compose.yml -f compose.build.yml up -d --build
```

Thiếu `APP_IMAGE` thì compose báo lỗi ngay (`required variable APP_IMAGE is missing`) chứ không chạy nhầm image. Từ nay thay mọi `docker compose …` bằng `docker compose -f compose.yml -f compose.build.yml …` (hoặc dùng `COMPOSE_FILE`).

Sau đó trong `/admin`:
0. Cài đặt → Chung (tên site, logo, ngôn ngữ), Truy cập (đăng ký/Google/bảo trì), Giao diện (màu, CSS/HTML tuỳ chỉnh), Tích hợp (SMTP, Google, SePay).
1. Cài đặt → Ngân hàng & nạp tiền: BIN, số TK, tên TK (in hoa không dấu), mức khuyến mãi.
2. Cài đặt → Cấp thành viên: ngưỡng + % Đại lý.
3. Cài đặt → Telegram: bot token + chat ID → "Gửi thử".
4. Nền tảng + Dịch vụ & giá.

### Cấu hình trong admin vs `.env`

Thứ tự ưu tiên: **giá trị nhập trong admin (khác rỗng) > `.env`**. `.env` là dự phòng: để trống/xoá giá trị trong admin → request kế tiếp dùng lại `.env`, nên runbook `.env` cũ vẫn đúng.

| Chỉnh trong admin (Cài đặt) | Vẫn chỉ ở `.env` |
|---|---|
| Chung: `site_name` (đè `APP_NAME`), ngôn ngữ mặc định (đè `APP_LOCALE`), logo, favicon, liên hệ, SEO | `APP_KEY`, `APP_URL`, `APP_DOMAIN`, `DB_*`, `TRUSTED_PROXIES`, `SEPAY_ALLOWED_IPS`, `SEPAY_API_URL` |
| Tích hợp: `MAIL_HOST/PORT/USERNAME/PASSWORD`, mã hoá, `MAIL_FROM_ADDRESS`, `GOOGLE_CLIENT_ID/SECRET`, `SEPAY_WEBHOOK_SECRET`, `SEPAY_API_TOKEN` | |
| Truy cập: đăng ký, đăng nhập Google, bảo trì khách | |

- Bí mật lưu mã hoá bằng `APP_KEY` trong bảng `settings`; **không** được ghi vào `bootstrap/cache/config.php` (DB chỉ đè trong bộ nhớ mỗi request/job; `config:cache`, `migrate`, `settings:*`... không áp cấu hình DB).
- Đổi tích hợp trong admin tự chạy `queue:restart` để worker nạp cấu hình mới. Đổi `.env` thì vẫn `docker compose up -d`.
- Đổi `APP_KEY` làm mất khả năng giải mã bí mật trong DB: site không crash (cấu hình runtime quay về `.env`), nhưng trang Tích hợp trong admin báo lỗi 500 cho tới khi xoá các bí mật cũ. Xoá rồi nhập lại trong admin:
  ```bash
  docker compose exec postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "UPDATE settings SET payload = '\''null'\'' WHERE \"group\" = '\''integrations'\'' AND name IN ('\''smtp_password'\'','\''google_client_secret'\'','\''sepay_webhook_secret'\'','\''sepay_api_token'\'');"'
  ```
- Mỗi trường bí mật có nút "Xoá (dùng giá trị .env)". Trang Tích hợp có "Gửi thử mail" (tới email admin đang đăng nhập) và "Kiểm tra SePay".
- Bảo trì trang khách: bật trong admin hoặc `docker compose exec app php artisan app:customer-maintenance on|off`. Chỉ chặn trang khách (503); `/admin`, webhook SePay và `/up` vẫn chạy.
- Logo/favicon upload nằm ở `storage/app/public` → named volume `app_storage` (gắn cho `app`, `queue`, `scheduler`), còn nguyên sau `docker compose up -d --force-recreate`. Backup volume này nếu logo quan trọng.
- Tùy biến giao diện/ngôn ngữ không cần dựng lại image: đặt file trong `custom/` (mount chỉ đọc) — xem `docs/customization.md`.

## 3. Cron backup (host)

```bash
sudo mkdir -p /srv/backups/smm-panel
crontab -e
# 5 * * * * OFFSITE_CMD='rclone copy /srv/backups/smm-panel remote:smm-panel' <thư mục cài>/docker/backup/backup-db.sh >> /var/log/smm-backup.log 2>&1
```

Giữ 48 bản hourly + 14 bản daily (bản 03:00). Lỗi bất kỳ → `app:notify-admin` gửi Telegram.
`backup-db.sh` tự tìm thư mục cài (thư mục chứa `compose.yml` gần nhất: cạnh script, rồi `../..`; đặt `APP_DIR=…` để chỉ định) và đọc `BACKUP_DIR` từ `.env` (biến môi trường `BACKUP_DIR` ưu tiên hơn; mặc định `/srv/backups/smm-panel`).
Thử cảnh báo: `BACKUP_DIR=/khong-ghi-duoc /srv/smm-panel/docker/backup/backup-db.sh` → phải nhận tin Telegram.

## 4. Deploy bản mới

```bash
cd /srv/smm-panel
docker compose exec app php artisan down --retry=60     # webhook nhận 503 → SePay tự retry
./docker/backup/backup-db.sh
git pull
docker compose -f compose.yml -f compose.build.yml up -d --build           # instance dựng từ source (APP_IMAGE=smm-panel:local); app tự migrate
curl -fsS https://<domain>/up
docker compose exec app php artisan up
docker compose exec app php artisan queue:restart
```

Trạng thái bảo trì lưu ở cache DB (`APP_MAINTENANCE_DRIVER=cache`, `APP_MAINTENANCE_STORE=database`) nên vẫn giữ khi container được tạo lại.

### 4a. Cập nhật instance cài từ release

Nguồn cập nhật là Release trên GitHub (`jhin1m/smm-panel`); mỗi bản gồm image app + image updater ghim digest và bundle cấu hình (`compose.yml`, `docker/`, `update.sh`) đã kiểm checksum. **Không có `artisan down`**: chỉ trang khách bảo trì tạm (`app:customer-maintenance`), `/admin`, webhook SePay và `/up` vẫn chạy.

**Cách 1: nút trong admin.** `/admin` → Cài đặt → **Cập nhật**: thấy phiên bản đang chạy, bản mới nhất + changelog, trạng thái updater, rồi bấm "Cập nhật lên X". Trang tự làm mới 3 giây/lần, hiện bước đang chạy và lịch sử. Bản `-rc.N` chỉ hiện khi `.env` có `UPDATER_ALLOW_PRERELEASE=true` (chỉ nên bật ở môi trường thử). Nút bị khoá khi updater không phản hồi (heartbeat cũ hơn 60 giây) hoặc đang có lần chạy khác.

**Cách 2: `update.sh` trên máy chủ** (dùng khi updater trong web lỗi, hoặc để đổi chính image updater, việc nút admin không tự làm):

```bash
cd /opt/smm-panel            # thư mục cài (INSTALL_DIR)
./update.sh                  # lên bản ổn định mới nhất
./update.sh 1.3.0            # hoặc đúng một phiên bản
```

`update.sh` tải `release.json` bản đích (kiểm checksum), đặt `UPDATER_IMAGE` theo digest trong đó, rồi chạy cùng logic với nút admin (`smm-update` trong image updater mới) và cuối cùng `up -d updater`. Nếu updater trong web đang chạy một lần cập nhật (trạng thái `running` với heartbeat còn mới) hoặc có yêu cầu đang chờ nhận, `update.sh` dừng ngay với thông báo và mã thoát khác 0, không đổi gì (vì `up -d updater` sẽ recreate container giữa chừng); đợi lần đó xong rồi chạy lại.

Mỗi lần cập nhật (cả hai cách) làm tuần tự: kiểm version (đúng semver, mới hơn bản đang chạy) → tải bundle + `sha256sum -c` → bật bảo trì khách → **backup DB vào `$BACKUP_DIR/pre-update/`** (giữ 5 bản gần nhất, tách khỏi `hourly/`) → lưu `compose.yml`/`.env`/`docker/` hiện tại vào `.update-saved/` → đổi bundle (không đụng `.env`, `custom/`) và đặt `APP_VERSION`, `APP_IMAGE` → `pull` + `up -d` **mọi service trong `compose.yml` của bản đích trừ `updater`** (lấy bằng `docker compose config --services`, nên service mới của release cũng được khởi động) → chờ `app` healthy (≤ 5 phút, health sâu: DB, settings, render `/login`) → tắt bảo trì, kiểm `GET /login` = 200 → `queue:restart`. Lỗi ở bất kỳ bước nào sau khi đổi bundle: khôi phục `compose.yml`/`.env`/`docker/` đã lưu, `up -d` lại bản cũ, ghi trạng thái `failed` + lý do, báo Telegram. Bảo trì khách luôn được tắt khi kết thúc, kể cả khi lỗi. Mỗi lần chạy có **giới hạn thời gian tổng 20 phút** (đổi bằng `UPDATER_TIMEOUT=<giây>` trong `.env`, áp dụng sau `docker compose up -d updater`): quá giờ thì updater dừng lần chạy, khôi phục bản cũ nếu đã đổi bundle và ghi `failed` với lý do "Quá thời gian tối đa". Dù smm-update kết thúc kiểu gì (kể cả bị OOM/`kill -9`), updater luôn chạy bước phục hồi ngay sau đó nên trạng thái không bao giờ kẹt ở `running`. Updater bị ngắt giữa chừng (`docker kill`, mất điện) thì lần khởi động kế tiếp tự khôi phục và ghi `failed (interrupted)`.

Điều kiện: `.env` có `UPDATER_IMAGE`, `INSTALL_DIR`, `BACKUP_DIR` (install.sh ghi sẵn); instance cũ phải thêm các dòng này trước khi dùng bản có updater (xem `.env.template`).

**`APP_VERSION` bắt buộc.** `.env` phải có `APP_VERSION=vX.Y.Z` (hoặc `vX.Y.Z-rc.N`) đúng với bản đang chạy. Thiếu hoặc sai định dạng thì updater từ chối (`failed`, lý do nêu rõ) thay vì coi là `0.0.0`, vì không biết bản đích mới hơn hay là hạ cấp. Instance chuyển tay từ bản cũ hoặc tự build từ source **phải đặt `APP_VERSION` trước khi dùng nút Cập nhật hoặc `update.sh`**.

**Health `/up` và `custom/`.** `/up` kiểm view đăng nhập **lõi**: finder tạm thời bỏ mọi đường dẫn thuộc `custom/views` (view, layout, include, component ẩn danh và Livewire phân giải qua finder), nên view tuỳ biến lỗi không làm container unhealthy hay chặn cập nhật. Không bị bỏ qua: `custom/lang` (không ảnh hưởng việc biên dịch view). Hệ quả: view tuỳ biến hỏng không bị phát hiện ở bước health, hãy tự kiểm `/login` sau khi sửa `custom/`. Updater **không mở cổng nào** và chỉ tải từ repo đóng cứng trong image của nó; app chỉ gửi cho updater một chuỗi version.

**Rollback tay từ `pre-update/`** (chỉ khi updater không tự phục hồi được, hoặc bản mới chạy được nhưng sai nghiệp vụ):

```bash
cd /opt/smm-panel
ls -t /srv/backups/smm-panel/pre-update/            # smm-<từ>-to-<đến>-<thời điểm>.dump
# .update-saved/ chỉ còn khi khôi phục tự động thất bại; có thì chép lại bản đã lưu:
test -f .update-saved/.complete && cp -a .update-saved/compose.yml .update-saved/.env . && cp -a .update-saved/docker/. docker/
# Không còn: tải tarball bundle của bản cũ từ trang Release, giải nén vào thư mục cài (không đè .env, custom/),
# rồi đặt lại APP_VERSION/APP_IMAGE cũ trong .env (digest ghi trong release.json của bản đó).
docker compose up -d app queue scheduler caddy
docker compose exec app php artisan app:customer-maintenance off      # nếu site còn kẹt bảo trì
```

Restore dump `pre-update` chỉ khi bản lỗi chưa phục vụ request nào kể từ lúc dump; làm theo mục 5 (kể cả `app:sepay-reconcile --from=<thời điểm dump>`).

## 5. Rollback

1. **Ưu tiên sửa tiến**: vá lỗi, deploy bản mới.
2. Lỗi chỉ ở code (không đổi schema): `git checkout <tag-trước>` → `docker compose -f compose.yml -f compose.build.yml up -d --build`.
3. **Restore dump CHỈ khi** bản lỗi chưa phục vụ request nào kể từ lúc dump (vd migrate hỏng ngay khi khởi động, đang bảo trì). Restore xoá mọi bút toán sau thời điểm dump. Sau restore **bắt buộc**:
   ```bash
   docker compose exec app php artisan app:sepay-reconcile --from="<thời điểm dump>"
   ```
   để lấy lại tiền nạp trong khoảng bị mất.

Restore:
```bash
docker compose stop app queue scheduler
docker compose exec -T postgres sh -c 'dropdb -U "$POSTGRES_USER" "$POSTGRES_DB" && createdb -U "$POSTGRES_USER" "$POSTGRES_DB"'
docker compose exec -T postgres sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner' < /srv/backups/smm-panel/hourly/<file>.dump
docker compose start app queue scheduler
```

### Diễn tập restore (làm trước go-live, rồi định kỳ)

```bash
docker compose exec -T postgres sh -c 'createdb -U "$POSTGRES_USER" restore_test'
docker compose exec -T postgres sh -c 'pg_restore -U "$POSTGRES_USER" -d restore_test --no-owner' < <bản mới nhất>
docker compose exec -T postgres psql -U smm -d restore_test -c 'select count(*) from wallet_transactions'
docker compose exec -T postgres psql -U smm -d smm_panel   -c 'select count(*) from wallet_transactions'
docker compose exec -T postgres sh -c 'dropdb -U "$POSTGRES_USER" restore_test'
```

## 6. Sự cố tiền nạp

- Xem `/admin/bank-transactions` tab **Chưa khớp**; log: `docker compose logs app --since 1h | grep -i sepay`.
- Khách chuyển sai nội dung → **Gán cho khách**. Gán nhầm → **Huỷ gán** (bút toán đảo) rồi gán lại.
- Webhook lỡ (server sập, sai secret) → nút **Đối soát** (hoặc `app:sepay-reconcile --from=...`), hoặc gửi lại webhook từ dashboard SePay. Đối soát tự chạy 10 phút/lần cho 3 ngày gần nhất.
- User API không dùng được → **Nhập giao dịch thủ công** (bắt buộc SePay ID — chống trùng khi webhook về sau).
- **CẤM dùng "Điều chỉnh số dư" để bù tiền nạp**: khi webhook/đối soát về sau, khách sẽ được cộng 2 lần.
- Webhook trả 503 → thiếu `SEPAY_WEBHOOK_SECRET` trong `.env` (có tin Telegram). Sửa `.env`, `docker compose up -d`, rồi Đối soát.
- Tin Telegram "nghi trùng" → giao dịch cùng mã tham chiếu + số tiền với giao dịch đã cộng nhưng khác SePay ID: KHÔNG cộng tự động. Kiểm sao kê ngân hàng; nếu là 2 khoản thật thì gán tay, nếu trùng thì Bỏ qua.
- Đối soát lỗi (token hết hạn, API đổi) → có tin Telegram tối đa 1 lần/giờ; sửa `SEPAY_API_TOKEN`/`SEPAY_API_URL`.
- Log "IP ngoài allowlist" với giao dịch thật → SePay đổi IP: cập nhật `SEPAY_ALLOWED_IPS` (chỉ ảnh hưởng log, không chặn).

## 7. Xoay vòng bí mật

| Bí mật | Cách đổi |
|---|---|
| `SEPAY_WEBHOOK_SECRET` | Đổi trên SePay + Cài đặt → Tích hợp (hoặc `.env`) gần như cùng lúc → `docker compose up -d` → Đối soát khoảng chuyển đổi |
| `SEPAY_API_TOKEN` | Tạo token mới trên SePay → Cài đặt → Tích hợp (hoặc `.env` + `docker compose up -d`) → "Kiểm tra SePay" → `app:sepay-reconcile` chạy không lỗi |
| Token Telegram | @BotFather revoke → nhập token mới ở Cài đặt → Telegram → "Gửi thử" |
| `APP_KEY` | Không đổi tuỳ tiện (mã hoá bot token, secret MFA). Nếu buộc phải đổi: nhập lại token Telegram, mọi admin chạy lại `app:make-admin` |
| Mất thiết bị MFA | Dùng recovery code; hết code → `docker compose exec app php artisan app:make-admin` với email đó (đặt mật khẩu mới, xoá MFA) |

## 8. Ghi chú vận hành

- `docker compose logs -f app queue scheduler`; job lỗi: `docker compose exec app php artisan queue:failed` (tự xoá sau 7 ngày).
- PHP-FPM `pm.max_children=20` (chat poll 5s). Nếu cạn worker khi đông khách → nâng RAM/`PHP_FPM_PM_MAX_CHILDREN` hoặc chuyển chat sang Laravel Reverb.
- Đặt Cloudflare phía trước Caddy: thêm `trusted_proxies` cho dải IP Cloudflare trong Caddyfile, nếu không IP log sẽ là IP Cloudflare.
- Dev/test: `docker compose -f compose.dev.yml up -d postgres mailpit`; cổng test chính `docker compose -f compose.dev.yml run --rm test` (PHP 8.4 + intl như production).

## 9. Checklist go-live

- [ ] HTTPS hợp lệ, `http→https`, `APP_DEBUG=false`; `/admin` chỉ admin vào được và bắt MFA.
- [ ] Cài đặt Ngân hàng (BIN, STK, tên TK) + khuyến mãi + ngưỡng/% Đại lý + Telegram đã nhập.
- [ ] SePay: webhook URL `https://{domain}/webhooks/sepay`, xác thực **HMAC** (secret khớp `.env`), **bật "Auto retry on server error"**, Payment code prefix `BP` + 6 ký tự chữ+số in hoa; `SEPAY_API_TOKEN` hợp lệ (`app:sepay-reconcile` chạy không lỗi).
- [ ] **Chuyển khoản thật 10.000đ** bằng QR trang Nạp tiền → cộng < 1 phút, `credited`, ledger đúng. Thêm 1 khoản ghi sai nội dung → chưa khớp → gán tay → huỷ gán → gán lại thành công.
- [ ] Đối soát: tạm tắt webhook trên SePay, chuyển 10.000đ → `app:sepay-reconcile` cộng được trong ≤ 10 phút (`source=reconcile`); bật lại webhook. Chạy đối soát cho ngày có giao dịch qua webhook → **không tạo bản trùng** (xác nhận id User API = id webhook).
- [ ] Log không có cảnh báo IP ngoài allowlist với giao dịch thật (nếu có → cập nhật `SEPAY_ALLOWED_IPS`).
- [ ] Google OAuth: redirect URI production đã khai; đăng nhập thử.
- [ ] SMTP: email quên mật khẩu tới hộp thư thật (SPF/DKIM).
- [ ] Telegram: "Gửi thử" OK; khách gửi chat → admin nhận tin.
- [ ] Queue + scheduler chạy (`docker compose ps`), job thất bại = 0.
- [ ] Backup mỗi giờ đã có ≥ 2 bản + off-site + diễn tập restore xong.

Biên bản go-live: ngày ………… · người kiểm ………… · ghi chú …………
