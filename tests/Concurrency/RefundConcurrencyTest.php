<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\OrderService;

it('5 tiến trình hoàn cùng đơn → đúng 1 dòng refund', function () {
    $user = userWithBalance(16_098);
    $order = placeOrder($user, Service::factory()->create(['price' => 16_098]), 1000);
    $admin = User::factory()->admin()->create();
    [$oid, $aid] = [$order->id, $admin->id];

    $tasks = array_fill(0, 5, fn () => app(OrderService::class)
        ->refund(Order::find($oid), 0, OrderStatus::Pending, 0, User::find($aid))->status->value);
    $r = collect(runParallel($tasks))->countBy();

    expect($r['refunded'] ?? 0)->toBe(1)
        ->and(WalletTransaction::where('type', 'refund')->count())->toBe(1)
        ->and($user->fresh()->balance)->toBe(16_098);
});
