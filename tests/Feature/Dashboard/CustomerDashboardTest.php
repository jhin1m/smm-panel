<?php

use App\Enums\Tier;
use App\Enums\TxType;
use App\Models\Service;
use App\Models\User;
use App\Services\WalletService;

it('member tổng nạp 1,5tr, ngưỡng 5tr → "Còn 3.500.000đ"', function () {
    $u = User::factory()->create();
    app(WalletService::class)->credit($u, 1_500_000, TxType::Topup, 'k');

    $this->actingAs($u)->get('/')->assertOk()
        ->assertSee('Còn 3.500.000đ để lên Đại lý')
        ->assertSee('1.500.000đ')
        ->assertSee('Đại lý được giảm 8%');
});

it('agency không hiện dòng "Còn ... để lên Đại lý"', function () {
    $u = User::factory()->create();
    $u->forceFill(['tier' => Tier::Agency])->save();

    $this->actingAs($u)->get('/')->assertOk()->assertDontSee('để lên Đại lý')->assertSee('Đang được giảm 8%');
});

it('đơn gần đây chỉ của mình', function () {
    $a = userWithBalance(100_000);
    $b = userWithBalance(100_000);
    $s = Service::factory()->create();
    placeOrder($a, $s, 1000, 'https://fb.com/cua-a');
    placeOrder($b, $s, 1000, 'https://fb.com/cua-b');

    $this->actingAs($a)->get('/')->assertSee('https://fb.com/cua-a')->assertDontSee('https://fb.com/cua-b');
});
