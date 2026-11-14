<?php

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\Service;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->user = userWithBalance(16_098);
    $this->order = placeOrder($this->user, Service::factory()->create(['price' => 16_098]), 1000);
});

it('hoàn đúng số tiền theo phần chưa chạy', function (int $done, int $expected) {
    if ($done > 0) {
        app(OrderService::class)->updateProgress($this->order, $done, OrderStatus::Pending, 0);
    }

    $o = app(OrderService::class)->refund($this->order, $done, OrderStatus::Pending, $done, $this->admin);

    expect($o->refunded_amount)->toBe($expected)->and($o->status)->toBe(OrderStatus::Refunded)
        ->and($this->user->fresh()->balance)->toBe($expected)
        ->and(WalletTransaction::where('type', 'refund')->count())->toBe($expected > 0 ? 1 : 0);
})->with([[0, 16_098], [300, 11_268], [1000, 0]]);

it('hoàn lần 2 → exception, vẫn 1 dòng refund', function () {
    app(OrderService::class)->refund($this->order, 0, OrderStatus::Pending, 0, $this->admin);

    expect(fn () => app(OrderService::class)->refund($this->order->fresh(), 0, OrderStatus::Refunded, 0, $this->admin))
        ->toThrow(InvalidOrderTransition::class);
    expect(WalletTransaction::where('type', 'refund')->count())->toBe(1);
});

it('đơn hoàn thành: hoàn một phần bắt buộc lý do, ghi vào ledger', function () {
    $svc = app(OrderService::class);
    $svc->complete($this->order, OrderStatus::Pending, 0);

    expect(fn () => $svc->refund($this->order, 600, OrderStatus::Completed, 1000, $this->admin, ' '))->toThrow(ValidationException::class);

    $o = $svc->refund($this->order, 600, OrderStatus::Completed, 1000, $this->admin, 'Like bị tụt 400');
    $tx = WalletTransaction::where('type', 'refund')->sole();

    expect($o->refunded_amount)->toBe(6_439)->and($o->done)->toBe(600)
        ->and($tx->amount)->toBe(6_439)->and($tx->created_by)->toBe($this->admin->id)->and($tx->description)->toBe('Like bị tụt 400');
});

it('đơn đã hoàn thành: done = số lượng → từ chối (không tiêu lượt hoàn 0đ)', function () {
    $svc = app(OrderService::class);
    $svc->complete($this->order, OrderStatus::Pending, 0);

    expect(fn () => $svc->refund($this->order, 1000, OrderStatus::Completed, 1000, $this->admin, 'x'))->toThrow(ValidationException::class);
    expect($this->order->fresh()->status)->toBe(OrderStatus::Completed);
});
