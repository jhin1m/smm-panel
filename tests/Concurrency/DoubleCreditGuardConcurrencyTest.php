<?php

use App\Models\User;
use App\Services\DepositService;

it('webhook và đối soát cùng giao dịch (khác SePay ID, cùng mã tham chiếu) chạy song song → cộng 1 lần', function () {
    configureDeposit();
    $u = User::factory()->create();
    $a = sepayPayload(['id' => 7001, 'code' => $u->deposit_code, 'referenceCode' => 'FTPAR', 'transferAmount' => 100_000]);
    $b = sepayPayload(['id' => 7002, 'code' => $u->deposit_code, 'referenceCode' => 'FTPAR', 'transferAmount' => 100_000]);

    $r = runParallel([
        fn () => app(DepositService::class)->handleWebhook($a, 'webhook'),
        fn () => app(DepositService::class)->handleWebhook($b, 'reconcile'),
    ]);

    expect(collect($r)->sort()->values()->all())->toBe(['credited', 'unmatched'])
        ->and($u->fresh()->balance)->toBe(100_000);
});
