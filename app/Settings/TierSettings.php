<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** 2 cấp cố định: Thành viên / Đại lý. Lên Đại lý khi tổng nạp tích luỹ ≥ ngưỡng. */
class TierSettings extends Settings
{
    public int $agency_threshold;

    public int $agency_discount_percent;

    public int $member_discount_percent;

    public static function group(): string
    {
        return 'tier';
    }
}
