<?php

use App\Enums\Tier;
use App\Models\BankTransaction;
use App\Models\User;
use App\Models\WalletTransaction;

beforeEach(function () {
    config(['sepay.webhook_secret' => SEPAY_TEST_SECRET]);
    configureDeposit();
});

it('hợp lệ + khớp mã → 200, credited, topup đúng, tổng nạp tăng, payload đủ', function () {
    $u = User::factory()->create();
    $p = sepayPayload(['code' => $u->deposit_code, 'content' => "{$u->deposit_code} nap tien", 'transferAmount' => 250_000]);

    postSepay($p)->assertOk()->assertExactJson(['success' => true]);

    $tx = BankTransaction::sole();
    expect($tx->status)->toBe('credited')->and($tx->user_id)->toBe($u->id)->and($tx->source)->toBe('webhook')
        ->and($tx->payload['referenceCode'])->toBe($p['referenceCode'])
        ->and($tx->transaction_date->format('Y-m-d H:i:s'))->toBe($p['transactionDate']);
    $topup = WalletTransaction::sole();
    expect($topup->type->value)->toBe('topup')->and($topup->amount)->toBe(250_000)->and($topup->idempotency_key)->toBe("sepay:{$p['id']}:1")
        ->and($u->fresh()->balance)->toBe(250_000)->and($u->fresh()->total_deposited)->toBe(250_000);
});

it('trùng id → 200, vẫn 1 bank tx + 1 topup', function () {
    $u = User::factory()->create();
    $p = sepayPayload(['code' => $u->deposit_code]);

    postSepay($p)->assertOk();
    postSepay($p)->assertOk()->assertExactJson(['success' => true]);

    expect(BankTransaction::count())->toBe(1)->and(WalletTransaction::count())->toBe(1)->and($u->fresh()->balance)->toBe(100_000);
});

it('transferType out → ignored, không ledger', function () {
    $u = User::factory()->create();

    postSepay(sepayPayload(['transferType' => 'out', 'code' => $u->deposit_code]))->assertOk();

    expect(BankTransaction::sole()->status)->toBe('ignored')->and(WalletTransaction::count())->toBe(0);
});

it('thiếu id / số tiền sai → 422', function (array $override) {
    $p = sepayPayload($override);
    if (array_key_exists('id', $override) && $override['id'] === null) {
        unset($p['id']);
    }

    postSepay($p)->assertStatus(422);
    expect(BankTransaction::count())->toBe(0);
})->with([[['id' => null]], [['transferAmount' => 0]], [['transferType' => 'x']]]);

it('không khớp / 2 mã của 2 user / sai STK → unmatched', function (Closure $make) {
    $a = User::factory()->create();
    $b = User::factory()->create();

    postSepay($make($a, $b))->assertOk();

    expect(BankTransaction::sole()->status)->toBe('unmatched')->and(WalletTransaction::count())->toBe(0);
})->with([
    'không mã' => [fn ($a, $b) => sepayPayload(['content' => 'chuyen tien an trua'])],
    '2 mã 2 user' => [fn ($a, $b) => sepayPayload(['content' => "{$a->deposit_code} {$b->deposit_code}"])],
    'sai STK' => [fn ($a, $b) => sepayPayload(['code' => $a->deposit_code, 'accountNumber' => '999999'])],
]);

it('khớp qua VA (subAccount) khi cấu hình STK', function () {
    $u = User::factory()->create();

    postSepay(sepayPayload(['code' => $u->deposit_code, 'accountNumber' => 'KHAC', 'subAccount' => '0123456789']))->assertOk();

    expect(BankTransaction::sole()->status)->toBe('credited');
});

it('khách bị khoá vẫn được cộng', function () {
    $u = User::factory()->locked()->create();

    postSepay(sepayPayload(['code' => $u->deposit_code]))->assertOk();

    expect($u->fresh()->balance)->toBe(100_000);
});

it('nạp đủ ngưỡng → lên Đại lý', function () {
    $u = User::factory()->create();

    postSepay(sepayPayload(['code' => $u->deposit_code, 'transferAmount' => 5_000_000]))->assertOk();

    expect($u->fresh()->tier)->toBe(Tier::Agency);
});

it('fixture payload thật chạy lại đúng', function () {
    $file = base_path('tests/Fixtures/sepay/webhook-in.json');
    $fixture = json_decode(file_get_contents($file), true);
    $u = User::factory()->create();
    $u->forceFill(['deposit_code' => $fixture['code']])->save();
    configureDeposit(['account_number' => $fixture['accountNumber']]);

    postSepay($fixture)->assertOk();

    expect(BankTransaction::sole()->status)->toBe('credited')->and($u->fresh()->balance)->toBe($fixture['transferAmount']);
});
