<?php

use App\Livewire\MyOrders;
use App\Models\Service;
use Livewire\Livewire;

it('khách chỉ thấy đơn của mình', function () {
    $a = userWithBalance(100_000);
    $b = userWithBalance(100_000);
    $s = Service::factory()->create();
    placeOrder($a, $s, 1000, 'https://facebook.com/cua-a');
    placeOrder($b, $s, 1000, 'https://facebook.com/cua-b');

    Livewire::actingAs($a)->test(MyOrders::class)
        ->assertSee('https://facebook.com/cua-a')
        ->assertDontSee('https://facebook.com/cua-b');
});

it('trang đơn hàng hiển thị tag trạng thái', function () {
    $a = userWithBalance(100_000);
    placeOrder($a, Service::factory()->create());

    $this->actingAs($a)->get('/don-hang')->assertOk()->assertSee('Chờ xử lý');
});
