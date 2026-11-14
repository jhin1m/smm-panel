# SMM Panel

**Tiếng Việt** · [English](README.en.md)

Panel bán dịch vụ mạng xã hội (SMM) mã nguồn mở, tự host trên VPS của bạn bằng Docker. Khách nạp tiền qua VietQR/SePay, đặt đơn, chat với admin; admin quản trị bằng Filament. Giao diện tiếng Việt và tiếng Anh.

> Bản ổn định đầu tiên là `v1.0.0`. Trước đó chỉ có bản thử nghiệm `-rc.N`: chưa nên dùng cho production.

## Tính năng

Chỉ liệt kê những gì đã có trong mã nguồn.

**Khách hàng**
- Đăng ký / đăng nhập bằng email + mật khẩu, đăng nhập Google, quên mật khẩu qua email (SMTP).
- Xác thực hai lớp (2FA, TOTP + mã khôi phục) tùy chọn, khách tự bật tại trang Bảo mật; admin có thể tắt công tắc này trong Cài đặt → Truy cập.
- Ví số dư, nạp tiền bằng mã QR VietQR; giao dịch tự cộng vào ví khi SePay gửi webhook (có đối soát tự động mỗi 10 phút), khuyến mãi nạp theo mức cấu hình.
- Chọn dịch vụ theo nền tảng, đặt đơn (link + số lượng), xem lịch sử và tiến độ đơn; giá tính ở server, có % giảm theo cấp Thành viên / Đại lý.
- Hộp chat với admin, thông báo từ admin trên bảng điều khiển.
- Chuyển ngôn ngữ vi/en.

**Quản trị (`/admin`, bắt buộc MFA TOTP)**
- Người dùng (khoá/mở khoá, đổi cấp, điều chỉnh số dư có lý do), đơn hàng (đổi trạng thái, cập nhật tiến độ, hoàn tiền), nền tảng và dịch vụ, thông báo.
- Giao dịch ngân hàng: gán giao dịch chưa khớp cho khách, huỷ gán, bỏ qua, nhập tay, đối soát; sổ cái ví chỉ ghi thêm (append-only); nhật ký thao tác admin.
- Hộp thư chat, bot Telegram báo admin khi có tin mới hoặc sự cố (webhook, đối soát, backup).
- Trang Cài đặt: Chung (tên site, logo, favicon, liên hệ, SEO, ngôn ngữ mặc định), Truy cập (đăng ký, Google, 2FA khách, bảo trì), Giao diện (màu, CSS/HTML tùy chỉnh), Tích hợp (SMTP, Google, SePay), Ngân hàng & nạp tiền, Cấp thành viên, Telegram.
- Trang Cập nhật: xem phiên bản, cập nhật bằng một nút kèm sao lưu DB và tự quay lại bản cũ nếu lỗi.

**Vận hành**
- Cài bằng một lệnh (`install.sh`) + wizard `/install` trên trình duyệt.
- Docker Compose: `app` (nginx + php-fpm), `queue`, `scheduler`, `postgres:17`, `caddy` (HTTPS tự động), `updater`.
- Script backup PostgreSQL theo giờ, thư mục `custom/` để tùy biến mà vẫn cập nhật được.

**Giới hạn hiện tại**: đơn do admin xử lý thủ công (chưa gọi API nhà cung cấp); nạp tiền chỉ qua VietQR/SePay; giao diện chỉ có theme sáng; image phát hành chỉ build cho `linux/amd64`.

## Ảnh chụp màn hình

_Chưa có ảnh chụp màn hình._

## Yêu cầu VPS

- Ubuntu 22.04+ hoặc Debian 12+, kiến trúc x86_64 (amd64).
- Tối thiểu 1 GB RAM (khuyến nghị 2 GB), quyền root.
- Tên miền có bản ghi A trỏ về IP VPS; cổng 80 và 443 còn trống.

## Cài đặt

```bash
curl -fsSL https://github.com/jhin1m/smm-panel/releases/latest/download/install.sh | sudo bash -s -- --domain panel.example.com
```

Script cài Docker nếu thiếu, tải bản phát hành và kiểm tra checksum, sinh `.env` (khoá ngẫu nhiên), khởi động các container rồi in ra địa chỉ `https://<tên-miền>/install?token=...`.

- `latest` chỉ có hiệu lực sau khi phát hành bản ổn định đầu tiên. Để cài một bản cụ thể (kể cả rc), thêm `--version`, ví dụ: `... | sudo bash -s -- --domain panel.example.com --version v1.0.0-rc.1`. Với bản rc, thay `latest/download` bằng `download/v1.0.0-rc.1` trong URL tải script.
- Tham số khác: `--dir <thư mục>` (mặc định `/opt/smm-panel`), `--yes`, `--dry-run` (chỉ in các bước, không cài).
- Chạy lại script an toàn: giữ nguyên `.env` đã có.

Backup định kỳ không được `install.sh` tự bật: thêm cron theo hướng dẫn script in ra cuối cùng (xem [docs/deployment.md](docs/deployment.md)).

### Lần chạy đầu: wizard `/install`

Mở địa chỉ có `token` mà script in ra. Wizard gồm 5 bước: mã token + ngôn ngữ, thông tin site, tài khoản admin, ngân hàng/SMTP (bỏ qua được), hoàn tất. Hoàn tất xong wizard bị khoá vĩnh viễn. Sau đó đăng nhập `https://<tên-miền>/admin`, thiết lập MFA ngay và cất mã khôi phục.

## Cập nhật

- **Trong admin**: Cài đặt → Cập nhật → "Cập nhật lên X". Hệ thống sao lưu DB, đổi phiên bản, kiểm tra sức khoẻ và tự quay về bản cũ nếu lỗi.
- **Trên máy chủ**: `cd /opt/smm-panel && ./update.sh` (hoặc `./update.sh 1.0.1` cho đúng một phiên bản). Dùng khi nút trong admin không chạy được.

Chi tiết, rollback và backup: [docs/deployment.md](docs/deployment.md).

## Tùy biến

Bốn tầng, từ dễ đến khó: cài đặt trong admin, thư mục `custom/` (view, chữ, ngôn ngữ, CSS; giữ nguyên khi cập nhật), image kế thừa, fork. Xem [docs/customization.md](docs/customization.md).

## Tài liệu

- [Triển khai và vận hành](docs/deployment.md)
- [Tùy biến](docs/customization.md)
- [Kiến trúc hệ thống](docs/system-architecture.md) · [Tóm tắt codebase](docs/codebase-summary.md) · [Công nghệ](docs/tech-stack.md)
- [Quy trình phát hành](docs/release-process.md)
- [Đóng góp](CONTRIBUTING.md) · [Bảo mật](SECURITY.md)

## Dịch vụ cài đặt và hỗ trợ

Phần mềm miễn phí. Nếu bạn cần được cài đặt, tùy biến hoặc hỗ trợ vận hành có tính phí, liên hệ qua GitHub Discussions hoặc Issues của repo này.

## Giấy phép

[MIT](LICENSE)
