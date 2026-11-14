<?php

namespace App\Services;

use App\Enums\ServiceUnit;

/**
 * Giá đơn = ceil(price × qty × (100 − d) / (100 × chia)). Số nguyên VND, làm tròn LÊN để không bán dưới giá.
 * Form khách (xem trước) và tạo đơn dùng chung hàm này.
 */
class PricingService
{
    public function total(int $price, ServiceUnit $unit, int $qty, int $discountPct): int
    {
        $discountPct = max(0, min(100, $discountPct));
        $num = $price * $qty * (100 - $discountPct);     // tối đa ~1e8 × 1e7 × 100 = 1e17 < PHP_INT_MAX
        $den = 100 * $unit->divisor();

        return intdiv($num + $den - 1, $den);
    }

    /** Hoàn phần chưa chạy: floor(total × (qty − done) / qty), luôn trong [0, total]. */
    public function refundAmount(int $total, int $qty, int $done): int
    {
        return intdiv($total * ($qty - $done), $qty);
    }
}
