# SMM Panel

> **Đang phát triển — chưa dùng cho production.** Bản ổn định đầu tiên sẽ là `v1.0.0`.

Panel bán dịch vụ mạng xã hội (SMM) tự host: ví nạp tiền qua VietQR/SePay, đặt đơn, chat khách–admin, thông báo Telegram, quản trị bằng Filament. Giao diện tiếng Việt và tiếng Anh.

## Cài đặt (bản thử nghiệm)

Yêu cầu: VPS Ubuntu 22.04+/Debian 12+, 1 GB RAM, tên miền trỏ về VPS, cổng 80/443 trống.

```bash
curl -fsSL https://github.com/jhin1m/smm-panel/releases/latest/download/install.sh | sudo bash -s -- --domain panel.example.com
```

Script cài Docker (nếu thiếu), tải bản phát hành, kiểm checksum, sinh cấu hình rồi in đường dẫn `/install?token=…` để hoàn tất trên trình duyệt.

## Tài liệu

- [Triển khai và vận hành](docs/deployment.md)
- [Tùy biến giao diện, ngôn ngữ (`custom/`)](docs/customization.md)
- [Quy trình phát hành](docs/release-process.md)
- [Kiến trúc hệ thống](docs/system-architecture.md)

## Bảo mật

Báo lỗ hổng theo [SECURITY.md](SECURITY.md), không mở issue công khai.

## License

[MIT](LICENSE)
