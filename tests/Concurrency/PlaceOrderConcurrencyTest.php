<?php

use App\Exceptions\InsufficientBalance;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\OrderService;

it('10 lệnh đặt song song, đủ tiền 3 đơn → đúng 3 đơn, 0 deadlock', function () {
    $user = userWithBalance(48_294);              // đủ 3 × 16.098
    $service = Service::factory()->create(['price' => 16_098]);
    [$uid, $sid] = [$user->id, $service->id];

    $tasks = [];
    foreach (range(1, 10) as $i) {
        $tasks[] = fn () => app(OrderService::class)->place(User::find($uid), $sid, ['link' => "https://fb.com/{$i}", 'quantity' => 1000]) ? 'ok' : 'x';
    }
    $r = collect(runParallel($tasks))->countBy();

    expect($r['ok'] ?? 0)->toBe(3)
        ->and($r[InsufficientBalance::class] ?? 0)->toBe(7)
        ->and(Order::where('user_id', $uid)->count())->toBe(3)
        ->and($user->fresh()->balance)->toBe(0)
        ->and((int) WalletTransaction::where('user_id', $uid)->sum('amount'))->toBe(0);
});
