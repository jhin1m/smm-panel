<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Số liệu dashboard admin (giờ VN, 7 ngày = hôm nay + 6 ngày trước). Chỉ gom — đơn chờ/chat/chưa khớp
 * gọi lại scope/service sẵn có để badge và dashboard luôn khớp nhau.
 * Tiền lấy từ sổ cái: 1 nguồn sự thật, đã trừ hoàn/huỷ gán.
 */
class AdminMetrics
{
    public function __construct(private ChatService $chat) {}

    private function start(): CarbonImmutable
    {
        return CarbonImmutable::today()->subDays(6);
    }

    /** Doanh thu thuần = tiền đơn trừ hoàn: −SUM(amount) với type order/refund. */
    public function revenue7d(): int
    {
        return (int) -DB::table('wallet_transactions')
            ->whereIn('type', ['order', 'refund'])
            ->where('created_at', '>=', $this->start())
            ->sum('amount');
    }

    /** @return array<int, array{day: string, revenue: int}> đủ 7 ngày, ngày trống = 0 */
    public function revenueSeries(): array
    {
        $start = $this->start()->toDateString();

        $rows = DB::select(<<<'SQL'
            SELECT d::date AS day, COALESCE(-SUM(wt.amount), 0) AS revenue
            FROM generate_series(?::date, ?::date + 6, interval '1 day') d
            LEFT JOIN wallet_transactions wt
              ON wt.created_at >= d AND wt.created_at < d + interval '1 day' AND wt.type IN ('order', 'refund')
            GROUP BY d ORDER BY d
            SQL, [$start, $start]);

        return array_map(fn ($r) => ['day' => (string) $r->day, 'revenue' => (int) $r->revenue], $rows);
    }

    /** Nạp thuần 7 ngày: topup + topup_reversal (không gồm khuyến mãi). */
    public function deposits7d(): int
    {
        return (int) DB::table('wallet_transactions')
            ->whereIn('type', ['topup', 'topup_reversal'])
            ->where('created_at', '>=', $this->start())
            ->sum('amount');
    }

    public function newCustomers7d(): int
    {
        return DB::table('users')->where('is_admin', false)->where('created_at', '>=', $this->start())->count();
    }

    public function pendingOrders(): int
    {
        return Order::pending()->count();
    }

    public function unreadChats(): int
    {
        return $this->chat->unreadThreadsForAdmin();
    }

    public function unmatchedDeposits(): int
    {
        return BankTransaction::unmatched()->count();
    }
}
