<?php

use App\Models\AdminAuditLog;
use App\Models\BankTransaction;
use App\Models\User;
use App\Services\DepositService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['sepay.webhook_secret' => SEPAY_TEST_SECRET, 'sepay.api_token' => 'api-token']);
    configureDeposit();
});

function fakeUserApi(array $items): void
{
    Http::fake(['my.sepay.vn/*' => Http::response(['status' => 200, 'error' => null, 'messages' => ['success' => true], 'transactions' => $items])]);
}

function apiItem(int $id, string $content, int $in = 200_000, ?string $ref = null): array
{
    return [
        'id' => (string) $id, 'bank_brand_name' => 'MBBank', 'account_number' => '0123456789', 'sub_account' => null,
        'transaction_date' => '2026-11-14 10:00:00', 'amount_out' => '0.00', 'amount_in' => number_format($in, 2, '.', ''),
        'accumulated' => '0.00', 'transaction_content' => $content, 'reference_number' => $ref ?? "REF{$id}", 'code' => null,
    ];
}

it('giao dịch chưa có → tạo source=reconcile + credited; đã có qua webhook → không trùng', function () {
    $u = User::factory()->create();
    postSepay(sepayPayload(['id' => 5001, 'code' => $u->deposit_code, 'referenceCode' => 'REF5001', 'transferAmount' => 200_000]))->assertOk();
    fakeUserApi([apiItem(5001, $u->deposit_code), apiItem(5002, "nap {$u->deposit_code}", 300_000)]);

    $this->artisan('app:sepay-reconcile')->assertSuccessful();

    expect(BankTransaction::count())->toBe(2)
        ->and(BankTransaction::firstWhere('sepay_id', 5002)->only(['source', 'status']))->toBe(['source' => 'reconcile', 'status' => 'credited'])
        ->and($u->fresh()->balance)->toBe(500_000);
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer api-token') && str_contains($r->url(), 'transaction_date_min='));
});

it('chạy đối soát 2 lần không cộng trùng', function () {
    $u = User::factory()->create();
    fakeUserApi([apiItem(6001, $u->deposit_code)]);

    $this->artisan('app:sepay-reconcile')->assertSuccessful();
    $this->artisan('app:sepay-reconcile')->assertSuccessful();

    expect($u->fresh()->balance)->toBe(200_000);
});

it('trùng mã tham chiếu + số tiền nhưng khác id → lưu chưa khớp (hiện ở tab Chưa khớp), không cộng', function () {
    $u = User::factory()->create();
    postSepay(sepayPayload(['id' => 7001, 'code' => $u->deposit_code, 'referenceCode' => 'FTSAME', 'transferAmount' => 200_000]))->assertOk();
    fakeUserApi([apiItem(999999, $u->deposit_code, 200_000, 'FTSAME')]);

    $this->artisan('app:sepay-reconcile')->assertSuccessful();

    expect(BankTransaction::firstWhere('sepay_id', 999999)->status)->toBe('unmatched')->and($u->fresh()->balance)->toBe(200_000);
});

it('không có token → bỏ qua êm', function () {
    config(['sepay.api_token' => null]);
    Http::fake();

    $this->artisan('app:sepay-reconcile')->assertSuccessful();

    Http::assertNothingSent();
});

it('API lỗi → lệnh thất bại', function () {
    Http::fake(['my.sepay.vn/*' => Http::response([], 500)]);

    $this->artisan('app:sepay-reconcile')->assertFailed();
});

it('nhập tay: source=manual + audit + Telegram, trùng id không tạo thêm', function () {
    Queue::fake();
    $u = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $data = ['sepay_id' => 8001, 'amount' => 150_000, 'content' => "ck {$u->deposit_code}", 'transaction_date' => '2026-11-14 11:00:00'];

    expect(app(DepositService::class)->importManual($data, $admin))->toBe('credited');
    expect(app(DepositService::class)->importManual($data, $admin))->toBe('duplicate');

    expect(BankTransaction::sole()->source)->toBe('manual')->and($u->fresh()->balance)->toBe(150_000)
        ->and(AdminAuditLog::where('action', 'deposit.manual_import')->count())->toBe(2);
});
