# Tùy biến SMM Panel

Bốn tầng, chọn tầng thấp nhất đủ dùng (càng xuống dưới càng khó cập nhật):

| Tầng | Dùng khi | Giữ được khi cập nhật |
|---|---|---|
| 1. Cài đặt admin | Tên site, logo, màu chủ đạo, CSS/HTML tùy biến, ngôn ngữ mặc định | Có (lưu trong DB) |
| 2. Thư mục `custom/` | Ghi đè giao diện khách, sửa chữ, thêm ngôn ngữ, thêm CSS/ảnh | Có (bind mount, ngoài image) |
| 3. Image kế thừa | Cần thêm extension PHP, gói hệ thống, file cố định trong image | Phải dựng lại image khi có bản mới |
| 4. Fork | Đổi logic nghiệp vụ, thêm tính năng | Tự merge từng bản |

## Tầng 1: cài đặt admin

Vào trang quản trị, mục Cài đặt. Đủ cho thương hiệu, màu sắc, đoạn CSS/HTML chèn vào `<head>`/`<body>` của trang khách.

## Tầng 2: thư mục `custom/`

`custom/` nằm cạnh `compose.yml` trên VPS và được mount **chỉ đọc** vào các container `app`, `queue`, `scheduler`. Sửa file trên host; không cần dựng lại image. Nội dung `custom/` không nằm trong git và không bị đụng khi pull image mới hoặc recreate container.

```
custom/
  views/    # Blade ghi đè resources/views, cùng đường dẫn tương đối
  lang/     # ghi đè key hoặc thêm ngôn ngữ
  public/   # phục vụ tại /custom/*
```

Biến `CUSTOM_PATH` (mặc định là `custom` cạnh ứng dụng) đổi vị trí thư mục nếu cần.

### Ví dụ: ghi đè layout trang khách

Chép `resources/views/layouts/guest.blade.php` của phiên bản đang chạy vào `custom/views/layouts/guest.blade.php` rồi sửa. Cách này áp dụng cho mọi view nằm trong `resources/views` của ứng dụng (trang khách, layout, component): cùng đường dẫn trong `custom/views` thì bản trong `custom/` thắng. Email dùng template mặc định của Laravel (không nằm trong `resources/views`) nên **không** ghi đè được qua `custom/`; đổi chữ email bằng `custom/lang/<locale>/mail.php`.

### Ví dụ: sửa chữ hoặc thêm ngôn ngữ

Sửa từng key: tạo `custom/lang/vi/common.php` chỉ chứa key cần đổi, các key còn lại giữ nguyên bản gốc.

```php
<?php

return [
    'guest' => ['headline' => 'Dịch vụ tăng tương tác số 1'],
];
```

Thêm ngôn ngữ (vd Thái): tạo `custom/lang/th/meta.php` với `return ['name' => 'ไทย'];` cùng các file nhóm (`common.php`, `auth.php`, ...) bạn dịch. Ngôn ngữ mới tự xuất hiện trong nút đổi ngôn ngữ; key chưa dịch rơi về ngôn ngữ mặc định (`APP_FALLBACK_LOCALE`).

### Ví dụ: thêm CSS

Tạo `custom/public/custom.css`. Layout khách nạp `/custom/custom.css` tự động khi file tồn tại. Ảnh, font đặt trong `custom/public/` và gọi qua `/custom/<tên-file>`.

Tailwind đã được biên dịch sẵn trong image: view tùy biến chỉ dùng được các class có sẵn; kiểu mới hãy viết trong `custom.css`.

### Giới hạn và rủi ro

- **View gốc có thể đổi giữa các phiên bản** (biến, component, cấu trúc). View bạn ghi đè sẽ không nhận thay đổi đó. Đọc release notes trước khi cập nhật và so sánh lại với bản gốc.
- View tùy biến lỗi chỉ làm hỏng trang dùng nó. Container không biên dịch trước view khi khởi động (`AUTORUN_LARAVEL_VIEW_CACHE=false`), nên request đầu tiên vào mỗi view chậm hơn một chút.
- **Trang quản trị (Filament) không hỗ trợ tùy biến qua `custom/`.** Giao diện lõi Filament nằm trong vendor nên không ghi đè được; vài view admin do ứng dụng tự viết (`resources/views/filament/...`) về kỹ thuật vẫn bị ghi đè nếu đặt cùng đường dẫn, nhưng không được đảm bảo giữa các phiên bản. Dùng tầng 1, 3 hoặc 4.
- Thư mục `custom/` phải tồn tại trên host trước `docker compose up` (repo đã có sẵn `custom/README.md`).

## Tầng 3: image kế thừa

```dockerfile
FROM ghcr.io/jhin1m/smm-panel:<tag>
USER root
RUN install-php-extensions redis
USER www-data
```

Dựng image này, đặt tên riêng rồi trỏ `image:` trong `compose.yml` tới nó. Mỗi lần có bản mới, đổi `<tag>` và dựng lại.

## Tầng 4: fork

Fork repo, sửa trực tiếp, tự dựng và đẩy image. Bạn chịu trách nhiệm merge từ upstream; hạn chế đụng file lõi nếu muốn dễ cập nhật.
