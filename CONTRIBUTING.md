# Đóng góp cho SMM Panel

[English summary below](#contributing-english)

Cảm ơn bạn quan tâm đến dự án. Báo lỗi, đề xuất tính năng và pull request đều được chào đón. Lỗ hổng bảo mật **không** báo qua issue công khai: xem [SECURITY.md](SECURITY.md).

## Thiết lập môi trường dev

Cần Docker (Compose v2) và Node.js 22 nếu muốn build asset trên máy.

```bash
git clone https://github.com/jhin1m/smm-panel.git && cd smm-panel
cp .env.example .env
docker compose -f compose.dev.yml up -d postgres mailpit     # Postgres (cổng host 54317) + Mailpit UI (8025)
docker compose -f compose.dev.yml run --rm test composer install
docker compose -f compose.dev.yml run --rm test php artisan key:generate
npm ci && npm run build                                      # nhiều test render view cần manifest Vite
```

Service `test` dùng PHP 8.4 + intl giống image production và kết nối DB `smm_panel_test` (tạo tự động khi volume Postgres khởi tạo lần đầu).

## Chạy kiểm tra

| Việc | Lệnh |
|---|---|
| Toàn bộ test (cổng chính) | `docker compose -f compose.dev.yml run --rm test` |
| Test hẹp | `docker compose -f compose.dev.yml run --rm test php artisan test tests/Feature/Deposit` |
| Format (Pint) | `docker compose -f compose.dev.yml run --rm test vendor/bin/pint` (CI chạy `--test`) |
| ShellCheck | `find deploy docker tests/shell -name '*.sh' -print0 \| xargs -0 shellcheck docker/updater/smm-update docker/updater/smm-updater-loop` |
| Hợp đồng bundle/`install.sh` | `tests/shell/bundle-install-contract.sh` |
| Luồng updater với docker giả | `tests/shell/updater-flow.sh` |

CI (`.github/workflows/ci.yml`) chạy test Pest, Pint, ShellCheck, hai script `tests/shell` và gitleaks; PR cần xanh hết.

## Quy ước

- **Commit**: [Conventional Commits](https://www.conventionalcommits.org/) (`feat(deposit): ...`, `fix(updates): ...`, `docs: ...`, `chore: ...`). Một commit một thay đổi có ý nghĩa.
- **Tiền**: số nguyên VND; mọi thay đổi số dư đi qua `WalletService`, không ghi `users.balance` ở nơi khác.
- **i18n**: mọi chuỗi hiển thị cho người dùng phải nằm trong `lang/vi/*.php` **và** `lang/en/*.php` với cùng tập key (dùng `__()`/`trans()`, không viết cứng chữ trong view/PHP). `tests/Feature/I18n/TranslationParityTest.php` kiểm hai chiều và sẽ fail nếu lệch.
- **Settings**: mọi property của settings class trong `app/Settings/` phải có giá trị mặc định PHP (`public bool $x = true;`, `public ?string $y = null;`) để instance cũ chưa có dòng trong bảng `settings` vẫn khởi động được. Giá trị "đè `.env`" mặc định phải rỗng. Settings class mới cần test kiểu `tests/Feature/Settings/SettingsUpgradeTest.php`.
- **Migration chỉ thêm**: thêm bảng/cột (cột mới nullable hoặc có default), không đổi tên/xoá cột đang được code dùng. Người dùng có thể quay về bản cũ sau khi cập nhật, nên schema mới phải chịu được code cũ.
- **`compose.yml`/bundle**: biến `.env` mới bắt buộc phải có giá trị mặc định an toàn (`${VAR:-...}`), vì updater không sửa `.env`. Xem [docs/release-process.md](docs/release-process.md).
- **Bí mật**: không commit `.env`, token, khoá, dữ liệu cá nhân.

## Checklist pull request

- [ ] Test liên quan đã thêm/cập nhật và `docker compose -f compose.dev.yml run --rm test` xanh.
- [ ] `vendor/bin/pint --test` sạch; ShellCheck sạch nếu sửa script.
- [ ] Chuỗi UI mới có đủ ở `lang/vi` và `lang/en`.
- [ ] Settings property mới có giá trị mặc định PHP.
- [ ] Migration chỉ thêm, tương thích code cũ.
- [ ] Đã cập nhật `docs/` nếu đổi hành vi người dùng, lệnh, cấu hình hoặc kiến trúc.
- [ ] Nếu đổi view lõi: ghi rõ để người dùng `custom/views` đối chiếu.

## Contributing (English)

Bug reports, feature requests and pull requests are welcome. Report vulnerabilities privately, never via public issues: see [SECURITY.md](SECURITY.md).

- Dev setup: `cp .env.example .env`, `docker compose -f compose.dev.yml up -d postgres mailpit`, then `docker compose -f compose.dev.yml run --rm test composer install` and `php artisan key:generate` in the same container; `npm ci && npm run build` for Vite assets.
- Run tests: `docker compose -f compose.dev.yml run --rm test`. Format: `docker compose -f compose.dev.yml run --rm test vendor/bin/pint`. Shell scripts must pass ShellCheck (see the table above).
- Commits follow Conventional Commits.
- Every UI string must exist in both `lang/vi` and `lang/en` (a parity test enforces identical keys).
- Every settings property must have a PHP default value.
- Migrations are additive only (new tables/columns, nullable or defaulted); never rename or drop a column in use.
- Open a pull request using the template and tick the checklist; CI (tests, Pint, ShellCheck, gitleaks) must be green.
