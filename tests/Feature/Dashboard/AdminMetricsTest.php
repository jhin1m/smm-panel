<?php

use App\Enums\OrderStatus;
use App\Enums\TxType;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\RevenueChart;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\AdminMetrics;
use App\Services\ChatService;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

function ledgerAt(User $u, int $amount, TxType $type, CarbonImmutable $at): void
{
    test()->travelTo($at);
    $amount > 0
        ? app(WalletService::class)->credit($u, $amount, $type, uniqid('k', true))
        : app(WalletService::class)->debit($u, -$amount, $type, uniqid('k', true));
    test()->travelBack();
}

beforeEach(function () {
    $this->now = CarbonImmutable::parse('2026-11-14 15:00:00', 'Asia/Ho_Chi_Minh');
    $this->travelTo($this->now);
});

it('doanh thu 7 ngày = đơn − hoàn, bỏ dữ liệu cũ hơn 7 ngày; chuỗi đủ 7 điểm', function () {
    $u = User::factory()->create();
    ledgerAt($u, 1_000_000, TxType::Topup, $this->now->subDays(10));
    ledgerAt($u, -50_000, TxType::Order, $this->now->subDays(8));
    ledgerAt($u, -100_000, TxType::Order, $this->now);
    ledgerAt($u, 30_000, TxType::Refund, $this->now);
    $this->travelTo($this->now);

    $m = app(AdminMetrics::class);
    $series = $m->revenueSeries();

    expect($m->revenue7d())->toBe(70_000)
        ->and($series)->toHaveCount(7)
        ->and($series[0]['day'])->toBe('2026-11-08')
        ->and($series[6])->toBe(['day' => '2026-11-14', 'revenue' => 70_000])
        ->and(array_sum(array_column($series, 'revenue')))->toBe(70_000);
});

it('biên ngày giờ VN: 23:59 hôm qua và 00:00 hôm nay rơi đúng cột', function () {
    $u = User::factory()->create();
    ledgerAt($u, 1_000_000, TxType::Topup, $this->now->subDays(3));
    ledgerAt($u, -11_000, TxType::Order, CarbonImmutable::parse('2026-11-13 23:59:30', 'Asia/Ho_Chi_Minh'));
    ledgerAt($u, -22_000, TxType::Order, CarbonImmutable::parse('2026-11-14 00:00:00', 'Asia/Ho_Chi_Minh'));
    $this->travelTo($this->now);

    $series = collect(app(AdminMetrics::class)->revenueSeries())->pluck('revenue', 'day');

    expect($series['2026-11-13'])->toBe(11_000)->and($series['2026-11-14'])->toBe(22_000);
});

it('tiền nạp = topup + topup_reversal, không gồm bonus', function () {
    $u = User::factory()->create();
    ledgerAt($u, 500_000, TxType::Topup, $this->now);
    ledgerAt($u, 25_000, TxType::Bonus, $this->now);
    ledgerAt($u, -200_000, TxType::TopupReversal, $this->now);
    $this->travelTo($this->now);

    expect(app(AdminMetrics::class)->deposits7d())->toBe(300_000);
});

it('khách mới không đếm admin và user 8 ngày trước', function () {
    User::factory()->create();
    User::factory()->admin()->create();
    $this->travelTo($this->now->subDays(8));
    User::factory()->create();
    $this->travelTo($this->now);

    expect(app(AdminMetrics::class)->newCustomers7d())->toBe(1);
});

it('đơn chờ và chat chưa đọc dùng lại nguồn có sẵn', function () {
    Queue::fake();
    $o = placeOrder(userWithBalance(100_000), Service::factory()->create());
    placeOrder(userWithBalance(100_000), Service::factory()->create())->forceFill(['status' => OrderStatus::Processing])->save();
    app(ChatService::class)->postCustomerMessage(User::factory()->create(), 'hi');

    $m = app(AdminMetrics::class);
    expect($m->pendingOrders())->toBe(Order::pending()->count())->toBe(1)
        ->and($m->unreadChats())->toBe(app(ChatService::class)->unreadThreadsForAdmin())->toBe(1);
});

it('admin xem dashboard; khách vào /admin → 403', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    filament()->setCurrentPanel('admin');
    Livewire\Livewire::test(AdminStatsOverview::class)->assertSee('Doanh thu 7 ngày')->assertSee('Giao dịch chưa khớp');
    Livewire\Livewire::test(RevenueChart::class)->assertOk();
    Livewire\Livewire::test(LatestOrders::class)->assertOk();
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});
