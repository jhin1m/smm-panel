# custom/

Thư mục tùy biến an toàn khi cập nhật. Nội dung ở đây không nằm trong git và không bị ghi đè khi cập nhật image.

```
custom/
  views/    # Blade ghi đè resources/views (cùng đường dẫn, vd views/layouts/guest.blade.php)
  lang/     # ghi đè từng key (lang/vi/common.php) hoặc thêm ngôn ngữ mới (lang/th/meta.php + file nhóm)
  public/   # phục vụ tại /custom/* (vd public/custom.css được nạp tự động ở layout khách)
```

- Sửa file trên host; container mount chỉ đọc. Đổi view/lang có hiệu lực ngay, không cần rebuild.
- View gốc có thể đổi giữa các phiên bản: xem release notes trước khi cập nhật.
- Không ghi đè được view của trang quản trị (Filament).

Chi tiết: `docs/customization.md`.
