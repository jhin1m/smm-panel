## Mô tả / Description

Thay đổi gì và vì sao. / What changed and why.

## Kiểm thử / Testing

Đã chạy gì (vd `docker compose -f compose.dev.yml run --rm test`). / What you ran.

## Checklist

- [ ] Test liên quan đã thêm/cập nhật; toàn bộ test xanh / Tests added or updated and green
- [ ] `vendor/bin/pint --test` sạch; ShellCheck sạch nếu sửa script / Pint clean; ShellCheck clean if scripts changed
- [ ] Chuỗi UI mới có ở cả `lang/vi` và `lang/en` / New UI strings in both `lang/vi` and `lang/en`
- [ ] Settings property mới có giá trị mặc định PHP / New settings properties have PHP defaults
- [ ] Migration chỉ thêm, tương thích code cũ / Migrations are additive only
- [ ] Đã cập nhật `docs/` nếu đổi hành vi, lệnh, cấu hình hoặc kiến trúc / Docs updated if behavior, commands, config or architecture changed
- [ ] Không có bí mật hay dữ liệu cá nhân trong thay đổi / No secrets or personal data
