<?php

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\StaleOrderState;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;

function orderIn(OrderStatus $status, int $done = 0): Order
{
    $o = placeOrder(userWithBalance(1_000_000), Service::factory()->create(['price' => 16_098]), 1000);
    $o->forceFill(['status' => $status, 'done' => $done])->save();

    return $o->fresh();
}

function svc(): OrderService
{
    return app(OrderService::class);
}

it('ma trận chuyển trạng thái hợp lệ', function (OrderStatus $from, string $op) {
    $o = orderIn($from);
    $admin = User::factory()->admin()->create();

    $r = match ($op) {
        'start' => svc()->start($o, $from, 0),
        'complete' => svc()->complete($o, $from, 0),
        'refund' => svc()->refund($o, 0, $from, 0, $admin, 'lý do'),
    };

    expect($r->status)->toBe(match ($op) {
        'start' => OrderStatus::Processing, 'complete' => OrderStatus::Completed, 'refund' => OrderStatus::Refunded,
    });
    if ($op === 'complete') {
        expect($r->done)->toBe(1000);
    }
})->with([
    [OrderStatus::Pending, 'start'], [OrderStatus::Pending, 'complete'], [OrderStatus::Pending, 'refund'],
    [OrderStatus::Processing, 'complete'], [OrderStatus::Processing, 'refund'],
    [OrderStatus::Completed, 'refund'],
]);

it('ma trận chuyển trạng thái bị cấm', function (OrderStatus $from, string $op) {
    $o = orderIn($from, $from === OrderStatus::Completed ? 1000 : 0);
    $done = $o->done;
    $admin = User::factory()->admin()->create();

    expect(fn () => match ($op) {
        'start' => svc()->start($o, $from, $done),
        'complete' => svc()->complete($o, $from, $done),
        'refund' => svc()->refund($o, $done, $from, $done, $admin, 'x'),
        'progress' => svc()->updateProgress($o, $done, $from, $done),
    })->toThrow(InvalidOrderTransition::class);
})->with([
    [OrderStatus::Processing, 'start'],
    [OrderStatus::Completed, 'start'], [OrderStatus::Completed, 'complete'], [OrderStatus::Completed, 'progress'],
    [OrderStatus::Refunded, 'start'], [OrderStatus::Refunded, 'complete'], [OrderStatus::Refunded, 'refund'], [OrderStatus::Refunded, 'progress'],
]);

it('tiến độ chỉ tăng, không vượt số lượng', function () {
    $o = orderIn(OrderStatus::Processing, 300);

    expect(fn () => svc()->updateProgress($o, 200, OrderStatus::Processing, 300))->toThrow(ValidationException::class);
    expect(fn () => svc()->updateProgress($o, 1001, OrderStatus::Processing, 300))->toThrow(ValidationException::class);
    expect(svc()->updateProgress($o, 500, OrderStatus::Processing, 300)->done)->toBe(500);
});

it('expected_done/expected_status cũ → StaleOrderState', function () {
    $o = orderIn(OrderStatus::Processing, 300);

    expect(fn () => svc()->updateProgress($o, 400, OrderStatus::Processing, 100))->toThrow(StaleOrderState::class);
    expect(fn () => svc()->complete($o, OrderStatus::Pending, 300))->toThrow(StaleOrderState::class);
});

it('scope pending đếm đúng', function () {
    orderIn(OrderStatus::Pending);
    orderIn(OrderStatus::Processing);
    orderIn(OrderStatus::Pending);

    expect(Order::pending()->count())->toBe(2)->and(Order::active()->count())->toBe(3);
});
