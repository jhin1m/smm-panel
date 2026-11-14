# Tech Stack — SMM Panel

Trạng thái: đã duyệt · Ngày: 2026-11-14 · Mockup: `docs/mockup/be-phong-panel.html`

## Phạm vi giai đoạn 1 (đã chốt)

| Hạng mục | Quyết định |
|---|---|
| Xử lý đơn | Admin làm tay: đổi trạng thái + tiến độ `done/qty`. Không gọi API nhà cung cấp |
| Dịch vụ | CRUD: danh mục/nền tảng (tên, icon, thứ tự) + dịch vụ (giá, đơn vị tính, min/max, mô tả, bật/tắt) |
| Form đặt đơn | Cố định: liên kết + số lượng + ghi chú. Không form builder |
| Giá đại lý | `% giảm theo cấp`, không lưu giá riêng từng cấp |
| Nạp tiền | Chỉ VietQR qua webhook SePay, khớp theo mã `BPxxxxx` trong nội dung |
| Đăng nhập | Email/mật khẩu + Google OAuth |
| Chat | Hộp chat trong panel (khách ↔ admin) + bot Telegram báo admin khi có tin mới |
| Theme | Chỉ sáng, không dark mode |
| Hosting | 1 VPS, Docker Compose |

Ngoài phạm vi giai đoạn 1: MoMo, USDT, thẻ cào, Facebook/Telegram login, 2FA, API cho đại lý, đa ngôn ngữ, tự động gọi nhà cung cấp.

## Stack

| Lớp | Chọn | Lý do |
|---|---|---|
| Runtime | PHP 8.4 | Laravel 13 cần ≥ 8.3; 8.4 ổn định, image Docker phổ biến |
| Framework | Laravel 13 | Queue, scheduler, auth, mail có sẵn |
| Admin | Filament 5 | 6 màn quản trị là CRUD + bảng lọc, gần như có sẵn; đổi theme sang màu mockup |
| Trang khách | Livewire (theo bản Filament dùng) + Blade + Tailwind 4 | Bám sát mockup, không cần SPA riêng |
| Auth | Laravel Fortify (backend) + Socialite (Google) | View tự viết theo mockup; admin dùng chung bảng `users` với cờ `is_admin` |
| DB | PostgreSQL 17 | Transaction + `SELECT … FOR UPDATE` cho sổ cái |
| Queue/cache/session | Driver `database` | Không cần Redis ở quy mô này |
| Realtime chat | `wire:poll` 4–5s (chỉ khi tab hiển thị) | SSE trên PHP-FPM chiếm 1 worker/kết nối, dễ treo pool. Chỗ cần nâng cấp: Laravel Reverb |
| Thông báo admin | Telegram Bot API qua job trong queue | Bot gửi vào chat/nhóm của admin |
| QR | Ảnh VietQR quick link (`img.vietqr.io`) theo STK + số tiền + nội dung | Không tự sinh QR |
| Test | Pest | Test bắt buộc cho sổ cái, webhook, tính giá, hoàn tiền |
| Deploy | Docker Compose: `app` (php-fpm + nginx), `queue`, `scheduler`, `postgres`, `caddy` (TLS) | Giống cách vận hành dcn-infra |

Phiên bản chính xác ghim khi `composer create-project` (kiểm Packagist lúc scaffold).

## Lõi nghiệp vụ phải đúng

1. **Sổ cái số dư**: bảng `wallet_transactions` (loại: nạp, khuyến mãi, đơn, hoàn, điều chỉnh; số tiền có dấu, `balance_after`, tham chiếu). `users.balance` chỉ cập nhật trong cùng transaction, khoá dòng user. Không sửa số dư trực tiếp ở đâu khác.
2. **Webhook SePay idempotent**: unique theo `id` SePay; xác thực API key/HMAC; cộng đúng `transferAmount` thực nhận; giao dịch không khớp mã → lưu chờ admin gán tay; luôn trả 2xx sau khi lưu.
3. **Đặt đơn**: giá tính phía server (giá dịch vụ × % giảm cấp), lưu snapshot giá vào đơn, kiểm min/max + số dư, trừ tiền nguyên tử.
4. **Hoàn tiền**: chuyển trạng thái hoàn → trả `total × (qty − done) / qty`, chỉ một lần mỗi đơn.
5. **Cấp đại lý + khuyến mãi nạp**: ngưỡng và % cấu hình trong trang cài đặt.

## Rủi ro

- Bán tương tác vi phạm ToS nền tảng → không dùng Stripe/PayPal; tài khoản ngân hàng nhận nhiều giao dịch nhỏ có thể bị soi.
- Khách chưa rõ danh mục dịch vụ → schema dịch vụ phải chung chung (đã xử lý bằng đơn vị tính + mô tả tự do).
- Webhook lỡ khi server sập → SePay tự gửi lại; thêm nút "đối soát thủ công" cho admin.

## Quyết định bổ sung (2026-11-14)

- Cấp Đại lý: theo **tổng nạp tích luỹ** (ngưỡng mặc định 5.000.000đ, cấu hình được); lên cấp vĩnh viễn, admin vẫn nâng/hạ tay. Ô "đã nạp" trên dashboard đổi thành tổng tích luỹ.
- Quên mật khẩu: **email reset qua SMTP** (Resend/Brevo), code sẵn, cấu hình `MAIL_*` lúc deploy.

## Câu hỏi mở

- Tên thương hiệu cuối cùng (thư mục để `smm-panel` phòng khi đổi tên).

## Phiên bản thực tế & lệch so với dự kiến (2026-11-14)

Phiên bản ghim trong `composer.lock`:

| Gói | Bản thực tế | Ghi chú |
|---|---|---|
| PHP | 8.4 (`composer.json` ghim `config.platform.php` = 8.4.0 để lock khớp image prod; yêu cầu `^8.3`) | Image `serversideup/php:8.4-cli` + intl cho dev/test và prod |
| `laravel/framework` | 13.42 | |
| `filament/filament` | 5.11 | |
| `livewire/livewire` | 4.4 | Bản đi kèm Filament 5 |
| `pestphp/pest` | 4.7 | **Lệch**: dùng Pest 4, không phải Pest 5 (lệch có chủ đích ghi trong plan giai đoạn 1) |
| `laravel/fortify` | 1.42 | Route khai tay trong `routes/web.php` (`Fortify::ignoreRoutes()`) để gắn limiter riêng |
| `spatie/laravel-settings` | 3.10 | 3 nhóm: `deposit`, `tier`, `telegram` |

Lệch có chủ đích so với tài liệu trên:

| Hạng mục | Thực tế | Lý do |
|---|---|---|
| SePay User API (đối soát) | **Legacy v1**: `GET https://my.sepay.vn/userapi/transactions/list`, Bearer, cấu hình `SEPAY_API_URL` | User API v2 trả `id` dạng UUID, không trùng `id` số của webhook nên không dùng được cho khoá chống trùng `sepay_id`. SePay ghi legacy "tiếp tục hoạt động", chưa có ngày ngừng. Client cô lập ở `app/Services/SepayApiClient.php` |
| Xác thực webhook | Chỉ HMAC-SHA256 + timestamp (`X-SePay-Signature`, `X-SePay-Timestamp`), không có nhánh API key | Header `Authorization: Apikey …` bị bỏ qua. Thiếu secret → 503 |
| Chống cộng đôi | Thêm lớp theo `reference_code` + số tiền + loại (khoá advisory) ngoài unique `sepay_id` | Phòng id User API v1 khác id webhook. Smoke test thật vẫn phải xác nhận, xem phase-05 |
| Chat | `ChatWidget` poll 5 giây khi mở, 20 giây khi đóng (`wire:poll.visible`); admin dùng trang Filament `ChatInbox`; mỗi khách 1 thread | Bot Telegram chỉ **báo 1 lần** cho mỗi chuỗi tin chưa đọc (`admin_notified_at`), không trả lời ngược từ Telegram |
| Telegram | `AdminNotifier` + job `SendTelegramMessage`; cấu hình trong admin (token mã hoá); ngoài chat còn báo sự cố webhook, đối soát lỗi, nghi trùng giao dịch, backup lỗi (`app:notify-admin`) | Một điểm gửi duy nhất |
| Admin | MFA TOTP bắt buộc (Filament app authentication); admin chỉ đăng nhập ở `/admin` | "2FA" ngoài phạm vi giai đoạn 1 áp cho **khách**; riêng admin có MFA |
| Phân quyền đơn | Không có `OrderPolicy`; truy vấn luôn lọc theo `auth()->id()` | YAGNI |
| Queue/scheduler | Dùng chung image runtime với `app` (compose đặt lệnh khác nhau) | Đơn giản hoá build |
| Việc còn lại | Smoke SePay thật (10.000đ) và thay `tests/Fixtures/sepay/webhook-in.json` bằng payload thật | Cần tài khoản SePay + ngân hàng thật. Checklist: `docs/deployment.md` mục 9 |
