<?php

use App\Enums\TxType;
use App\Livewire\Deposit;
use App\Models\User;
use App\Services\WalletService;
use Livewire\Livewire;

it('URL QR chứa BIN-STK và addInfo = mã nạp; không có nút tạo mã mới', function () {
    configureDeposit();
    $u = User::factory()->create();

    $this->actingAs($u)->get('/nap-tien')->assertOk()
        ->assertSee('https://img.vietqr.io/image/970422-0123456789-compact2.png', false)
        ->assertSee('addInfo='.$u->deposit_code, false)
        ->assertDontSee('Tạo mã nạp mới');
});

it('chưa cấu hình ngân hàng → "Chưa mở nạp tiền"', function () {
    $this->actingAs(User::factory()->create())->get('/nap-tien')->assertOk()->assertSee('Chưa mở nạp tiền');
});

it('lịch sử chỉ của mình', function () {
    configureDeposit();
    $a = User::factory()->create();
    $b = User::factory()->create();
    app(WalletService::class)->credit($a, 111_000, TxType::Topup, 'a1');
    app(WalletService::class)->credit($b, 222_000, TxType::Topup, 'b1');

    Livewire::actingAs($a)->test(Deposit::class)->assertSee('111.000đ')->assertDontSee('222.000đ');
});

it('poll: số dư không đổi → không render; đổi → toast', function () {
    configureDeposit();
    $u = User::factory()->create();

    $c = Livewire::actingAs($u)->test(Deposit::class);
    $c->call('checkBalance')->assertNoRedirect();
    expect($c->effects['html'] ?? null)->toBeNull();

    app(WalletService::class)->credit($u, 50_000, TxType::Topup, 'k');
    $c->call('checkBalance')->assertDispatched('toast')->assertSet('lastBalance', 50_000);
});
