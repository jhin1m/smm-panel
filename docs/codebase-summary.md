# Tóm tắt codebase — SMM Panel

Cập nhật: 2026-11-14 (giai đoạn 1). Kiến trúc: [system-architecture.md](./system-architecture.md) · Công nghệ: [tech-stack.md](./tech-stack.md) · Triển khai: [deployment.md](./deployment.md).

## 1. Bản đồ thư mục

| Đường dẫn | Nội dung |
|---|---|
| `app/Services/` | Nghiệp vụ, nơi duy nhất đổi tiền/trạng thái (xem §2) |
| `app/Livewire/` | Trang khách: `CustomerDashboard`, `PlaceOrder`, `MyOrders`, `Deposit`, `ChatWidget` |
| `app/Filament/Resources/` | `Users`, `Orders`, `Categories`, `Services`, `BankTransactions`, `WalletTransactions`, `AdminAuditLogs`, `Notices` (mỗi resource có thư mục riêng; thao tác phức tạp tách ra `*Actions.php`) |
| `app/Filament/Pages/` | `ChatInbox`, `ManageDepositSettings`, `ManageTierSettings`, `ManageTelegramSettings` |
| `app/Filament/Widgets/` | `AdminStatsOverview`, `RevenueChart`, `LatestOrders` |
| `app/Filament/Concerns/`, `Support/` | `AuditsSettingsChanges` (audit khi đổi cài đặt), `MoneyColumn` |
| `app/Models/` | `User`, `Order`, `Service`, `Category`, `WalletTransaction`, `BankTransaction`, `ChatThread`, `ChatMessage`, `Notice`, `AdminAuditLog` |
| `app/Enums/` | `TxType`, `OrderStatus`, `ServiceUnit`, `Tier` |
| `app/Exceptions/` | `InsufficientBalance`, `IdempotencyConflict`, `SelfAdjustmentForbidden`, `InvalidOrderTransition`, `StaleOrderState`, `DepositAlreadyProcessed`, `ConfirmationRequired` |
| `app/Settings/` | spatie settings: `DepositSettings`, `TierSettings`, `TelegramSettings` |
| `app/Support/` | `Money`, `DepositCode`, `DepositCodeMatcher`, `VietQr`, `AuditLog` |
| `app/Http/` | `Controllers/SepayWebhookController`, `Controllers/Auth/GoogleLoginController`; middleware `VerifySepaySignature`, `EnsureAccountNotLocked`, `TrustProxies` |
| `app/Actions/Fortify/` | `CreateNewUser`, `ResetUserPassword`, `PasswordValidationRules` |
| `app/Jobs/` | `SendTelegramMessage` |
| `app/Console/Commands/` | `MakeAdmin`, `SepayReconcile`, `NotifyAdmin` |
| `app/Providers/` | `AppServiceProvider`, `FortifyServiceProvider` (limiter, chặn admin ở `/login`), `Filament/AdminPanelProvider` |
| `routes/` | `web.php` (auth + trang khách), `webhooks.php` (`POST /webhooks/sepay`), `console.php` (scheduler) |
| `config/sepay.php` | Secret webhook, token/URL User API, timestamp tolerance, IP allowlist |
| `database/migrations/` | Users + auth, ví/sổ cái, audit, danh mục/dịch vụ/đơn, `bank_transactions` (+ CHECK), chat, notices, settings |
| `resources/views/` | `livewire/*` (5 trang), `auth/*`, `layouts/{app,guest}`, `components/*` (`money`, `panel`, `stat`, `tag`, `toast`...), `filament/` |
| `resources/css/`, `resources/js/` | `app.css`, `fonts.css`, theme Filament `css/filament/admin`; Vite + Tailwind 4 |
| `lang/vi/` | Chuỗi tiếng Việt |
| `docker/` | `caddy/Caddyfile`, `backup/backup-db.sh`, `postgres/init-test-db.sql` |
| Gốc repo | `Dockerfile`, `compose.yml` (prod), `compose.dev.yml` (dev/test), `.env.example`, `.env.production.example`, `phpunit.xml` |
| `docs/` | Tài liệu dự án; `docs/mockup/be-phong-panel.html` là mockup đã duyệt |
| `plans/` | Kế hoạch và báo cáo từng giai đoạn |

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
| `tests/Feature/Chat`, `Telegram`, `Dashboard` | Chat khách/admin, báo Telegram, số liệu và thông báo |
| `tests/Concurrency` | Chạy tiến trình PHP thật song song: ví, đặt đơn, hoàn tiền, webhook trùng `id`, cộng đôi theo mã tham chiếu, `migrate:fresh` |

**Harness `runParallel()`** (`tests/Support/parallel.php`): chạy các closure ở tiến trình riêng (`Concurrency::driver('process')`, kết nối DB riêng), mọi tiến trình ngủ tới cùng mốc (`delay` mặc định 2 giây) rồi chạy để tranh chấp khoá thật; exception trong tiến trình con trả về dưới dạng tên lớp. Chỉ dùng trong `tests/Concurrency`. Kiểm chứng harness: `ParallelHarnessTest`. Fixture webhook: `tests/Fixtures/sepay/webhook-in.json` (chưa thay bằng payload thật, xem plan phase-05).

## 4. Lệnh thường dùng

| Mục đích | Lệnh |
|---|---|
| Dựng DB + mailpit dev | `docker compose -f compose.dev.yml up -d postgres mailpit` (Postgres cổng host `${DB_PORT:-54317}`, Mailpit UI `8025`) |
| Chạy toàn bộ test (cổng chính, PHP 8.4 + Postgres) | `docker compose -f compose.dev.yml run --rm test` |
| Test hẹp trong container | `docker compose -f compose.dev.yml run --rm test php artisan test tests/Feature/Deposit tests/Concurrency` |
| Chạy dev local | `composer dev` (chạy `php artisan dev`) · `npm run dev` · `npm run build` |
| Tạo/đặt lại admin | `php artisan app:make-admin` (hỏi email, tên, mật khẩu; đặt lại cũng xoá MFA và mở khoá) |
| Đối soát SePay | `php artisan app:sepay-reconcile [--from="Y-m-d H:i:s"] [--to=...]` (mặc định 3 ngày) |
| Gửi tin cho admin qua Telegram | `php artisan app:notify-admin "nội dung"` (dùng bởi `backup-db.sh`) |
| Production | Xem [deployment.md](./deployment.md) (`docker compose up -d --build`, thêm `exec app` cho lệnh artisan) |

Lưu ý: DB test là `smm_panel_test`, tạo bởi `docker/postgres/init-test-db.sql` khi volume Postgres khởi tạo lần đầu.
