<?php

use App\Enums\TxType;
use App\Exceptions\InsufficientBalance;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;

it('không trừ quá số dư khi 10 lệnh chạy song song', function () {
    $user = User::factory()->create();
    app(WalletService::class)->credit($user, 300, TxType::Topup, "seed:{$user->id}");
    $id = $user->id;

    $tasks = [];
    foreach (range(1, 10) as $i) {
        $tasks[] = fn () => app(WalletService::class)->debit(User::find($id), 100, TxType::Order, "t:{$id}:{$i}") ? 'ok' : 'x';
    }
    $r = collect(runParallel($tasks))->countBy();

    expect($r['ok'] ?? 0)->toBe(3)
        ->and($r[InsufficientBalance::class] ?? 0)->toBe(7)
        ->and($user->fresh()->balance)->toBe(0)
        ->and((int) WalletTransaction::where('user_id', $id)->sum('amount'))->toBe(0);
});

it('5 tiến trình credit cùng key → chỉ 1 bút toán', function () {
    $user = User::factory()->create();
    $id = $user->id;

    $tasks = array_fill(0, 5, fn () => app(WalletService::class)->credit(User::find($id), 50_000, TxType::Topup, "same:{$id}")->id);
    $ids = runParallel($tasks);

    expect(array_unique($ids))->toHaveCount(1)
        ->and(WalletTransaction::where('user_id', $id)->count())->toBe(1)
        ->and($user->fresh()->balance)->toBe(50_000)
        ->and($user->fresh()->total_deposited)->toBe(50_000);
});
