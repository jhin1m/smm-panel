<?php

use App\Models\BankTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\DepositService;

it('5 tiến trình cùng sepay id → cộng 1 lần', function () {
    configureDeposit();
    $u = User::factory()->create();
    $p = sepayPayload(['id' => 424242, 'code' => $u->deposit_code, 'transferAmount' => 100_000]);

    $r = runParallel(array_fill(0, 5, fn () => app(DepositService::class)->handleWebhook($p, 'webhook')));

    expect(collect($r)->countBy()->sortKeys()->all())->toBe(['credited' => 1, 'duplicate' => 4])
        ->and(BankTransaction::count())->toBe(1)
        ->and(WalletTransaction::count())->toBe(1)
        ->and($u->fresh()->balance)->toBe(100_000);
});
