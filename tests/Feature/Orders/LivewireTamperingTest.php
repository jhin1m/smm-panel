<?php

use App\Livewire\PlaceOrder;
use App\Models\Order;
use App\Models\Service;
use Livewire\Livewire;

it('set serviceId của dịch vụ đang tắt → không đặt được', function () {
    $u = userWithBalance(1_000_000);
    Service::factory()->create();
    $off = Service::factory()->create(['is_active' => false]);

    Livewire::actingAs($u)->test(PlaceOrder::class)
        ->set('serviceId', $off->id)
        ->set('link', 'https://facebook.com/x')
        ->call('submit')
        ->assertHasErrors('service');

    expect(Order::count())->toBe(0);
});

it('selectService với dịch vụ tắt bị bỏ qua', function () {
    $u = userWithBalance(0);
    $on = Service::factory()->create();
    $off = Service::factory()->create(['is_active' => false]);

    Livewire::actingAs($u)->test(PlaceOrder::class)
        ->call('selectService', $off->id)
        ->assertSet('serviceId', $on->id)
        ->assertHasErrors('service');
});
