<?php

use App\Models\BankTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\DepositService;

beforeEach(function () {
    config(['sepay.webhook_secret' => SEPAY_TEST_SECRET]);
    configureDeposit(['bonus_rules' => [['min_amount' => 500_000, 'percent' => 5], ['min_amount' => 2_000_000, 'percent' => 8]]]);
});

it('khuyến mãi theo mức cao nhất, không tính vào tổng nạp', function (int $amount, int $bonus) {
    $u = User::factory()->create();

    postSepay(sepayPayload(['code' => $u->deposit_code, 'transferAmount' => $amount]))->assertOk();

    expect((int) WalletTransaction::where('type', 'bonus')->sum('amount'))->toBe($bonus)
        ->and($u->fresh()->balance)->toBe($amount + $bonus)
        ->and($u->fresh()->total_deposited)->toBe($amount);
})->with([[499_999, 0], [500_000, 25_000], [2_000_000, 160_000]]);

it('% chốt lúc nhận: đổi mức sau rồi gán tay vẫn dùng mức cũ', function () {
    $u = User::factory()->create();
    postSepay(sepayPayload(['content' => 'khong ma', 'transferAmount' => 1_000_000]))->assertOk();
    $tx = BankTransaction::sole();
    expect($tx->status)->toBe('unmatched')->and($tx->bonus_percent)->toBe(5);

    configureDeposit(['bonus_rules' => [['min_amount' => 500_000, 'percent' => 10]]]);
    app(DepositService::class)->assign($tx->id, $u, User::factory()->admin()->create());

    expect($tx->fresh()->bonus_amount)->toBe(50_000)->and($u->fresh()->balance)->toBe(1_050_000);
});
