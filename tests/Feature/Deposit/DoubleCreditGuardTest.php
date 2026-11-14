<?php

use App\Jobs\SendTelegramMessage;
use App\Models\BankTransaction;
use App\Models\User;
use App\Services\DepositService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    configureDeposit();
    Queue::fake();
});

it('cùng mã tham chiếu + số tiền nhưng khác SePay ID (vd đối soát trước, webhook sau) → không cộng lần 2, báo admin', function () {
    $u = User::factory()->create();
    $svc = app(DepositService::class);

    expect($svc->handleWebhook(sepayPayload(['id' => 111, 'code' => $u->deposit_code, 'referenceCode' => 'FT999']), 'reconcile'))->toBe('credited');
    expect($svc->handleWebhook(sepayPayload(['id' => 222, 'code' => $u->deposit_code, 'referenceCode' => 'FT999']), 'webhook'))->toBe('unmatched');

    expect($u->fresh()->balance)->toBe(100_000)
        ->and(BankTransaction::firstWhere('sepay_id', 222)->noteLabel())->toBe('Nghi trùng giao dịch SePay #111 (cùng mã tham chiếu)');
    Queue::assertPushed(SendTelegramMessage::class, fn ($j) => str_contains($j->html, 'nghi trùng'));
});

it('nhập tay sai SePay ID nhưng đúng mã tham chiếu → webhook sau không cộng lần 2', function () {
    $u = User::factory()->create();
    $svc = app(DepositService::class);

    $svc->importManual(['sepay_id' => 5, 'amount' => 100_000, 'content' => $u->deposit_code, 'transaction_date' => '2026-11-14 10:00:00', 'reference_code' => 'FT555'], User::factory()->admin()->create());
    $svc->handleWebhook(sepayPayload(['id' => 6, 'code' => $u->deposit_code, 'referenceCode' => 'FT555']), 'webhook');

    expect($u->fresh()->balance)->toBe(100_000);
});

it('khác số tiền cùng mã tham chiếu vẫn xử lý bình thường', function () {
    $u = User::factory()->create();
    $svc = app(DepositService::class);
    $svc->handleWebhook(sepayPayload(['id' => 1, 'code' => $u->deposit_code, 'referenceCode' => 'X1', 'transferAmount' => 100_000]), 'webhook');
    $svc->handleWebhook(sepayPayload(['id' => 2, 'code' => $u->deposit_code, 'referenceCode' => 'X1', 'transferAmount' => 50_000]), 'webhook');

    expect($u->fresh()->balance)->toBe(150_000);
});

it('không bao giờ tự khớp tiền nạp vào tài khoản admin', function () {
    $admin = User::factory()->admin()->create();

    app(DepositService::class)->handleWebhook(sepayPayload(['code' => $admin->deposit_code]), 'webhook');

    expect(BankTransaction::sole()->status)->toBe('unmatched')->and($admin->fresh()->balance)->toBe(0);
});

it('đối soát lỗi API → báo Telegram (tối đa 1 lần/giờ)', function () {
    config(['sepay.api_token' => 'tok']);
    Http::fake(['my.sepay.vn/*' => Http::response([], 401)]);

    $this->artisan('app:sepay-reconcile')->assertFailed();
    $this->artisan('app:sepay-reconcile')->assertFailed();

    Queue::assertPushed(SendTelegramMessage::class, 1);
});
