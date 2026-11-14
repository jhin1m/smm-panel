# Tóm tắt codebase — SMM Panel

Cập nhật: 2026-11-14. Kiến trúc: [system-architecture.md](./system-architecture.md) · Công nghệ: [tech-stack.md](./tech-stack.md) · Triển khai: [deployment.md](./deployment.md).

## 1. Bản đồ thư mục

| Đường dẫn | Nội dung |
|---|---|
| `app/Services/` | Nghiệp vụ, nơi duy nhất đổi tiền/trạng thái (xem §2) |
| `app/Livewire/` | Trang khách: `CustomerDashboard`, `PlaceOrder`, `MyOrders`, `Deposit`, `AccountSecurity` (2FA khách), `ChatWidget`; wizard cài đặt `Install/InstallWizard` |
| `app/Filament/Resources/` | `Users`, `Orders`, `Categories`, `Services`, `BankTransactions`, `WalletTransactions`, `AdminAuditLogs`, `Notices` (mỗi resource có thư mục riêng; thao tác phức tạp tách ra `*Actions.php`) |
| `app/Filament/Pages/` | `ChatInbox`, `ManageUpdates`; trang Cài đặt: `ManageGeneralSettings`, `ManageAccessSettings`, `ManageAppearanceSettings`, `ManageIntegrationSettings`, `ManageDepositSettings`, `ManageTierSettings`, `ManageTelegramSettings` |
| `app/Filament/Widgets/` | `AdminStatsOverview`, `RevenueChart`, `LatestOrders` |
| `app/Filament/Concerns/`, `Support/` | `AuditsSettingsChanges` (audit khi đổi cài đặt), `MoneyColumn` |
| `app/Models/` | `User`, `Order`, `Service`, `Category`, `WalletTransaction`, `BankTransaction`, `ChatThread`, `ChatMessage`, `Notice`, `AdminAuditLog` |
| `app/Enums/` | `TxType`, `OrderStatus`, `ServiceUnit`, `Tier` |
| `app/Exceptions/` | `InsufficientBalance`, `IdempotencyConflict`, `SelfAdjustmentForbidden`, `InvalidOrderTransition`, `StaleOrderState`, `DepositAlreadyProcessed`, `ConfirmationRequired`, `UpdateRejected` |
| `app/Settings/` | spatie settings (mỗi class một nhóm): `GeneralSettings`, `AccessSettings`, `AppearanceSettings`, `IntegrationSettings`, `DepositSettings`, `TierSettings`, `TelegramSettings`, `InstallSettings` |
| `app/Support/` | `Money`, `DepositCode`, `DepositCodeMatcher`, `VietQr`, `AuditLog`, `RuntimeConfig` (settings đè `config()`), `InstallState`, `Branding`, `Locales`, `CustomOverrides` (`custom/`), `AppVersion`, `UserSessions` |
| `app/Http/` | `Controllers/SepayWebhookController`, `Controllers/Auth/GoogleLoginController`, `Controllers/LocaleController`; middleware `VerifySepaySignature`, `EnsureAccountNotLocked`, `EnsureInstalled`, `EnsureFeatureEnabled`, `CustomerMaintenance`, `SetLocale`, `TrustProxies`; `Responses/FailedTwoFactorLoginResponse` |
| `app/Actions/` | `CreateOrResetAdmin`; `Fortify/`: `CreateNewUser`, `ResetUserPassword`, `PasswordValidationRules` |
| `app/Listeners/` | `DeepHealthCheck` (mở rộng `/up`) |
| `app/Jobs/` | `SendTelegramMessage` |
| `app/Console/Commands/` | `MakeAdmin` (`app:make-admin`), `SepayReconcile`, `NotifyAdmin`, `CustomerMaintenance` (`app:customer-maintenance`) |
| `app/Providers/` | `AppServiceProvider`, `FortifyServiceProvider` (limiter, chặn admin ở `/login`), `Filament/AdminPanelProvider` |
| `routes/` | `web.php` (auth + trang khách), `webhooks.php` (`POST /webhooks/sepay`), `console.php` (scheduler) |
| `config/sepay.php`, `config/updates.php` | Secret webhook, token/URL User API, timestamp tolerance, IP allowlist; thư mục chung với updater, repo release, ngưỡng heartbeat |
| `database/migrations/` | Users + auth, ví/sổ cái, audit, danh mục/dịch vụ/đơn, `bank_transactions` (+ CHECK), chat, notices, settings, `users.locale`, cột 2FA khách (chỉ thêm) |
| `resources/views/` | `livewire/*` (5 trang), `auth/*`, `layouts/{app,guest}`, `components/*` (`money`, `panel`, `stat`, `tag`, `toast`...), `filament/` |
| `resources/css/`, `resources/js/` | `app.css`, `fonts.css`, theme Filament `css/filament/admin`; Vite + Tailwind 4 |
| `lang/vi/`, `lang/en/` | Chuỗi UI hai ngôn ngữ (cùng tên file, cùng tập key); mỗi thư mục có `meta.php` (`name`) |
| `custom/` | Thư mục tùy biến của người vận hành (ngoài git, chỉ giữ `README.md`); xem [customization.md](./customization.md) |
| `docker/` | `caddy/Caddyfile`, `backup/backup-db.sh`, `postgres/init-test-db.sql`, `updater/` (`Dockerfile`, `smm-update`, `smm-updater-loop`) |
| `deploy/` | `install.sh` (cài trên VPS), `update.sh` (cập nhật từ máy chủ), `build-bundle.sh` (dựng tarball release) |
| `.github/` | `workflows/ci.yml` (test, lint, gitleaks), `workflows/release.yml` (tag → image + Release), mẫu issue/PR |
| Gốc repo | `Dockerfile`, `compose.yml` (chạy image dựng sẵn), `compose.build.yml` (build từ source), `compose.dev.yml` (dev/test), `.env.template` (mẫu cho `install.sh`), `.env.example`, `.env.production.example`, `phpunit.xml`, `LICENSE`, `README.md`/`README.en.md`, `CONTRIBUTING.md`, `SECURITY.md` |
| `docs/` | Tài liệu dự án (danh sách ở [Tài liệu](#5-tài-liệu)) |

## 2. Services

| Service | Vai trò |
|---|---|
| `WalletService` | Nơi duy nhất đổi `users.balance`/`total_deposited`: `credit`, `debit`, `adjust`; khoá user, idempotency, ghi sổ cái |
| `OrderService` | `place`, `start`, `updateProgress`, `complete`, `refund`; chuyển trạng thái kiểm `expected status/done` |
| `PricingService` | Tính tổng tiền và số tiền hoàn |
| `TierService` | `discountPercent`, `promoteIfEligible`, `promoteAllEligible` |
| `DepositService` | `handleWebhook`, `assign`, `unassign`, `ignore`, `importManual` |
| `SepayApiClient` / `SepayReconciler` | Kéo giao dịch User API (legacy v1) / đưa qua `DepositService` |
| `AdminNotifier` | Điểm duy nhất gửi Telegram cho admin (dispatch `SendTelegramMessage` `afterCommit`) |
| `ChatService` | Gửi tin khách/admin, đánh dấu đã đọc, đếm chưa đọc, báo Telegram 1 lần cho chuỗi tin chưa đọc |
| `UpdateService` | Phía app của cập nhật: phiên bản đang chạy, bản mới nhất từ GitHub (cache), trạng thái/lịch sử do updater ghi, `requestUpdate` ghi `inbox/request.json` |
| `UserAdminService` | `lock`/`unlock`, `changeTier`, `resetTierAuto`, `bulkPromote`, `reissueDepositCode`, `adjustBalance`; ghi audit |
| `AdminMetrics` | Số liệu dashboard admin |

## 3. Test (Pest 4)

Cấu hình `tests/Pest.php`: `Feature` dùng `RefreshDatabase`, `Unit` thuần, `Concurrency` dùng `DatabaseTruncation` (qua `Tests\ConcurrencyTestCase`, giữ bảng `settings`). Helper trong `tests/Support/*.php` (`auth`, `orders`, `parallel`, `sepay`) tự nạp.

| Suite | Phạm vi |
|---|---|
| `tests/Unit` | `MoneyTest`, `PricingServiceTest` |
| `tests/Feature/Auth` | Đăng ký/đăng nhập, Google, quên mật khẩu, rate limit, tài khoản bị khoá, quyền admin, MFA, `app:make-admin` |
| `tests/Feature/Wallet` | Sổ cái (constraint, trigger), `WalletService`, mã nạp, lên cấp, quản trị user, audit log |
| `tests/Feature/Orders` | Đặt đơn, chuyển trạng thái, hoàn tiền, danh mục admin, `MyOrders`, chống chỉnh sửa payload Livewire |
| `tests/Feature/Deposit` | Webhook, bảo mật chữ ký, matcher mã, khuyến mãi, gán/huỷ gán, chống cộng đôi, đối soát, trang nạp, cài đặt |
| `tests/Feature/Auth` (2FA) | `CustomerTwoFactor*`, `GoogleTwoFactorLoginTest`, `AdminMfaTest`, `AdminResetTwoFactorTest` |
| `tests/Feature/Chat`, `Telegram`, `Dashboard` | Chat khách/admin, báo Telegram, số liệu và thông báo |
| `tests/Feature/Settings`, `I18n`, `Custom` | Nhóm settings + `RuntimeConfig` + `SettingsUpgradeTest`; chuyển ngôn ngữ + `TranslationParityTest`; ghi đè `custom/` |
| `tests/Feature/Install`, `Updates` | `EnsureInstalled`, wizard, `install.sh`, `.env.template`; nâng cấp instance cũ; `UpdateService`, trang `ManageUpdates` |
| `tests/shell` | `bundle-install-contract.sh` (bundle khớp `install.sh`), `updater-flow.sh` (updater/`update.sh` với docker giả) |
| `tests/Concurrency` | Chạy tiến trình PHP thật song song: ví, đặt đơn, hoàn tiền, webhook trùng `id`, cộng đôi theo mã tham chiếu, `migrate:fresh` |

**Harness `runParallel()`** (`tests/Support/parallel.php`): chạy các closure ở tiến trình riêng (`Concurrency::driver('process')`, kết nối DB riêng), mọi tiến trình ngủ tới cùng mốc (`delay` mặc định 2 giây) rồi chạy để tranh chấp khoá thật; exception trong tiến trình con trả về dưới dạng tên lớp. Chỉ dùng trong `tests/Concurrency`. Kiểm chứng harness: `ParallelHarnessTest`. Fixture webhook: `tests/Fixtures/sepay/webhook-in.json` (chưa thay bằng payload thật).

## 4. Lệnh thường dùng

| Mục đích | Lệnh |
|---|---|
| Pint (format) | `docker compose -f compose.dev.yml run --rm test vendor/bin/pint` (CI dùng `--test`) |
| Dựng DB + mailpit dev | `docker compose -f compose.dev.yml up -d postgres mailpit` (Postgres cổng host `${DB_PORT:-54317}`, Mailpit UI `8025`) |
| Chạy toàn bộ test (cổng chính, PHP 8.4 + Postgres) | `docker compose -f compose.dev.yml run --rm test` |
| Test hẹp trong container | `docker compose -f compose.dev.yml run --rm test php artisan test tests/Feature/Deposit tests/Concurrency` |
| Chạy dev local | `composer dev` (chạy `php artisan dev`) · `npm run dev` · `npm run build` |
| Tạo/đặt lại admin | `php artisan app:make-admin` (hỏi email, tên, mật khẩu; đặt lại cũng xoá MFA và mở khoá) |
| Đối soát SePay | `php artisan app:sepay-reconcile [--from="Y-m-d H:i:s"] [--to=...]` (mặc định 3 ngày) |
| Gửi tin cho admin qua Telegram | `php artisan app:notify-admin "nội dung"` (dùng bởi `backup-db.sh`) |
| Production | Xem [deployment.md](./deployment.md): cài bằng `deploy/install.sh`, lệnh artisan qua `docker compose exec app php artisan ...`; tự build từ source dùng thêm `-f compose.yml -f compose.build.yml` |

Lưu ý: DB test là `smm_panel_test`, tạo bởi `docker/postgres/init-test-db.sql` khi volume Postgres khởi tạo lần đầu.

## 5. Tài liệu

| File | Nội dung |
|---|---|
| [deployment.md](./deployment.md) | Cài đặt, cập nhật, backup, rollback, sự cố, checklist go-live |
| [customization.md](./customization.md) | Bốn tầng tùy biến, thư mục `custom/` |
| [system-architecture.md](./system-architecture.md) | Thành phần, dữ liệu, dòng tiền, bảo mật, updater |
| [tech-stack.md](./tech-stack.md) | Công nghệ và quyết định |
| [release-process.md](./release-process.md) | Phiên bản, cắt tag, asset release, image updater |
| [design-guidelines.md](./design-guidelines.md) | Token giao diện |
