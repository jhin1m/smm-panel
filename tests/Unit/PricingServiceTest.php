<?php

use App\Enums\ServiceUnit;
use App\Services\PricingService;

it('tính giá đơn làm tròn lên', function (int $price, ServiceUnit $unit, int $qty, int $pct, int $expected) {
    expect((new PricingService)->total($price, $unit, $qty, $pct))->toBe($expected);
})->with([
    'per_1000 × 1.000' => [16_098, ServiceUnit::Per1000, 1000, 0, 16_098],
    'per_1000 × 50' => [16_098, ServiceUnit::Per1000, 50, 0, 805],
    'per_unit 2.000 × 3' => [2_000, ServiceUnit::PerUnit, 3, 0, 6_000],
    'đại lý 8%' => [16_098, ServiceUnit::Per1000, 1000, 8, 14_811],
    'giảm 100%' => [16_098, ServiceUnit::Per1000, 1000, 100, 0],
    'giá 0' => [0, ServiceUnit::Per1000, 1000, 0, 0],
    'cực đại không tràn' => [100_000_000, ServiceUnit::PerUnit, 10_000_000, 0, 1_000_000_000_000_000],
]);

it('tính số hoàn phần chưa chạy (làm tròn xuống)', function (int $done, int $expected) {
    expect((new PricingService)->refundAmount(16_098, 1000, $done))->toBe($expected);
})->with([[0, 16_098], [300, 11_268], [600, 6_439], [1000, 0]]);
