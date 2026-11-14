<?php

use App\Support\Money;

it('định dạng tiền VND kiểu vi-VN', function (int $amount, string $expected) {
    expect(Money::vnd($amount))->toBe($expected);
})->with([
    [16098, '16.098đ'],
    [0, '0đ'],
    [1500000, '1.500.000đ'],
    [-25000, '-25.000đ'],
]);
