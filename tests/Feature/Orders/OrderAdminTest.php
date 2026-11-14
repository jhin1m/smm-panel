<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Service;
use App\Models\User;
use App\Models\WalletTransaction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    filament()->setCurrentPanel('admin');
});

it('action Hoàn tiền tạo ledger refund với created_by admin', function () {
    $o = placeOrder(userWithBalance(16_098), Service::factory()->create(['price' => 16_098]), 1000);

    Livewire::actingAs($this->admin)->test(ListOrders::class)
        ->callAction(TestAction::make('refund')->table($o), ['done' => 300, 'reason' => null])
        ->assertHasNoActionErrors();

    expect($o->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and(WalletTransaction::where('type', 'refund')->sole()->only(['amount', 'created_by']))
        ->toBe(['amount' => 11_268, 'created_by' => $this->admin->id]);
});

it('bắt đầu → cập nhật tiến độ → hoàn thành', function () {
    $o = placeOrder(userWithBalance(16_098), Service::factory()->create(['price' => 16_098]), 1000);
    $page = fn () => Livewire::actingAs($this->admin)->test(ListOrders::class);

    $page()->callAction(TestAction::make('start')->table($o))->assertHasNoActionErrors();
    $page()->callAction(TestAction::make('progress')->table($o->fresh()), ['done' => 400])->assertHasNoActionErrors();
    expect($o->fresh()->done)->toBe(400)->and($o->fresh()->status)->toBe(OrderStatus::Processing);

    $page()->callAction(TestAction::make('complete')->table($o->fresh()))->assertHasNoActionErrors();
    expect($o->fresh()->status)->toBe(OrderStatus::Completed)->and($o->fresh()->done)->toBe(1000);
});

it('modal cũ (admin khác đã cập nhật) → không đổi gì', function () {
    $o = placeOrder(userWithBalance(16_098), Service::factory()->create(['price' => 16_098]), 1000);

    Livewire::actingAs($this->admin)->test(ListOrders::class)
        ->callAction(TestAction::make('refund')->table($o), ['done' => 0, 'expected_status' => 'pending', 'expected_done' => 5]);

    expect($o->fresh()->status)->toBe(OrderStatus::Pending)->and(WalletTransaction::where('type', 'refund')->count())->toBe(0);
});

it('khách không vào được /admin/orders', function () {
    $this->actingAs(User::factory()->create())->get('/admin/orders')->assertForbidden();
});
