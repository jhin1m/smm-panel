<?php

use App\Enums\OrderStatus;
use App\Enums\Tier;
use App\Exceptions\InsufficientBalance;
use App\Livewire\PlaceOrder;
use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\WalletTransaction;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('đặt thành công: snapshot giá, trừ tiền, ledger order = −total', function () {
    $u = userWithBalance(100_000);
    $s = Service::factory()->create(['price' => 16_098]);

    $o = placeOrder($u, $s, 1000);

    expect($o->status)->toBe(OrderStatus::Pending)->and($o->total)->toBe(16_098)->and($o->unit_price)->toBe(16_098)
        ->and($o->service_name)->toBe($s->name)->and($u->fresh()->balance)->toBe(83_902);
    $tx = WalletTransaction::where('type', 'order')->sole();
    expect($tx->amount)->toBe(-16_098)->and($tx->idempotency_key)->toBe("order:{$o->id}")->and($tx->reference_id)->toBe($o->id);

    $s->update(['price' => 99_999, 'name' => 'Đổi tên']);
    expect($o->fresh()->unit_price)->toBe(16_098)->and($o->fresh()->service_name)->not->toBe('Đổi tên');
});

it('đại lý được giảm theo cấp, lưu discount_percent', function () {
    $u = userWithBalance(100_000);
    $u->forceFill(['tier' => Tier::Agency])->save();

    $o = placeOrder($u, Service::factory()->create(['price' => 16_098]), 1000);

    expect($o->discount_percent)->toBe(8)->and($o->total)->toBe(14_811);
});

it('số lượng ngoài min/max → lỗi, không trừ tiền', function (int $qty) {
    $u = userWithBalance(1_000_000);
    $s = Service::factory()->create(['min_quantity' => 50, 'max_quantity' => 5000]);

    expect(fn () => placeOrder($u, $s, $qty))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0)->and($u->fresh()->balance)->toBe(1_000_000);
})->with([49, 5001]);

it('dịch vụ tắt hoặc danh mục tắt → lỗi', function (bool $svcOn, bool $catOn) {
    $u = userWithBalance(1_000_000);
    $s = Service::factory()->for(Category::factory()->state(['is_active' => $catOn]))->create(['is_active' => $svcOn]);

    expect(fn () => placeOrder($u, $s))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0);
})->with([[false, true], [true, false]]);

it('link không phải http(s) → lỗi', function (string $link) {
    $u = userWithBalance(1_000_000);

    expect(fn () => placeOrder($u, Service::factory()->create(), 1000, $link))->toThrow(ValidationException::class);
})->with(['javascript:alert(1)', 'ftp://x.com/a', 'không phải link']);

it('thiếu số dư → không tạo đơn, không ghi sổ', function () {
    $u = userWithBalance(10_000);

    expect(fn () => placeOrder($u, Service::factory()->create(['price' => 16_098]), 1000))->toThrow(InsufficientBalance::class);
    expect(Order::count())->toBe(0)->and(WalletTransaction::where('type', 'order')->count())->toBe(0);
});

it('user bị khoá không đặt được (service) và bị logout (web)', function () {
    $u = userWithBalance(100_000);
    $u->forceFill(['locked_at' => now()])->save();

    expect(fn () => placeOrder($u, Service::factory()->create()))->toThrow(ValidationException::class);
    $this->actingAs($u)->get('/dat-don')->assertRedirect('/login');
});

it('form Livewire: xem trước giá và đặt đơn → về /don-hang', function () {
    $u = userWithBalance(100_000);
    $s = Service::factory()->create(['price' => 16_098]);

    Livewire::actingAs($u)->test(PlaceOrder::class)
        ->assertSet('serviceId', $s->id)
        ->set('quantity', 2000)
        ->assertSee('32.196đ')
        ->set('link', 'https://facebook.com/x')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('orders.index'));

    expect(Order::sole()->total)->toBe(32_196);
});

it('form Livewire: thiếu tiền → báo lỗi + link nạp', function () {
    $u = userWithBalance(1_000);
    Service::factory()->create(['price' => 16_098]);

    Livewire::actingAs($u)->test(PlaceOrder::class)
        ->set('link', 'https://facebook.com/x')
        ->call('submit')
        ->assertHasErrors('balance');

    expect(Order::count())->toBe(0);
});

it('trang đặt đơn hiển thị card dịch vụ có tốc độ, bắt đầu sau', function () {
    $u = userWithBalance(0);
    Service::factory()->create(['name' => 'Like VN', 'speed' => '10.000/ngày', 'start_time' => '5 phút']);

    $this->actingAs($u)->get('/dat-don')->assertOk()
        ->assertSee('Like VN')->assertSee('Tốc độ 10.000/ngày')->assertSee('bắt đầu sau 5 phút');
});
