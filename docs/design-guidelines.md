# Design Guidelines

Chỉ theme sáng, không có dark mode.

## Token

| Token | Giá trị | Dùng cho |
|---|---|---|
| `--bg` | `#f2f4f9` | Nền trang |
| `--surface` | `#ffffff` | Panel, sidebar, card |
| `--surface-2` | `#e9edf6` | Hover, nền segmented, thanh tiến độ |
| `--line` | `#d8deec` | Viền |
| `--fg` | `#111a2e` | Chữ chính |
| `--muted` | `#5a667f` | Chữ phụ |
| `--accent` / `--accent-soft` | `#1f47e6` / `#e2e8ff` | Nút chính, mục đang chọn |
| `--ok` / `--ok-soft` | `#0c7f57` / `#daf3e8` | Hoàn thành, tiền vào |
| `--warn` / `--warn-soft` | `#9a5a00` / `#fcecd0` | Chờ xử lý |
| `--bad` / `--bad-soft` | `#bf2d38` / `#fde2e4` | Hoàn tiền, lỗi, khoá |
| Bo góc | `10px` panel, `8px` nút/input, `99px` pill/chip | |
| Bóng | `0 1px 2px rgba(17,26,46,.06), 0 8px 24px rgba(17,26,46,.06)` | |

Map sang Tailwind 4 qua `@theme` trong `resources/css/app.css`; Filament dùng `->colors(['primary' => '#1f47e6', ...])` + theme CSS riêng để khớp nền/viền.

## Chữ

- Sans: **Be Vietnam Pro** 400/500/600/700 — toàn bộ UI.
- Mono: **IBM Plex Mono** 400/500/600 — số tiền, mã đơn, mã nạp, số liệu (`font-variant-numeric: tabular-nums`).
- Cỡ: body 14px, h1 22px/700, h2 16px/600, nhãn bảng 11.5px uppercase tracking .06em.
- Tiền: định dạng `vi-VN` + hậu tố `đ` (ví dụ `16.098đ`); giá dịch vụ hiển thị "/ 1.000" hoặc theo đơn vị tính.

## Bố cục

- App shell: sidebar 232px trái + vùng làm việc; ≤860px sidebar thành thanh nav cuộn ngang.
- Trang khách: Tổng quan · Đặt đơn · Đơn hàng · Nạp tiền · Đăng nhập/Đăng ký · nút chat nổi góc phải.
- Đặt đơn: cột trái chọn nền tảng (chip) → danh sách dịch vụ (card có ID, tên, giá, tốc độ, min–max); cột phải form sticky + tóm tắt giá + số dư sau khi đặt.
- Nạp tiền: chip số tiền nhanh + ô nhập; QR + bảng STK/nội dung có nút sao chép; trạng thái chờ có chấm nhấp nháy.

## Thành phần

- Tag trạng thái đơn: Chờ xử lý (warn) · Đang chạy (info/accent) · Hoàn thành (ok) · Đã hoàn tiền (bad).
- Thanh tiến độ 6px dưới cột "done / qty".
- Switch bật/tắt cho dịch vụ và cổng nạp (admin).
- Toast đáy giữa, nền `--fg`, tự ẩn sau 2.6s.

## Khả năng truy cập

- `:focus-visible` viền 2px accent; nav có `aria-current`; chip/svc dùng `aria-pressed`; chat là `role=dialog`.
- Tôn trọng `prefers-reduced-motion`.
- Bảng rộng cuộn ngang trong panel, không làm trang tràn ngang ở 375px.
