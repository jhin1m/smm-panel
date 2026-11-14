<?php

use App\Models\User;
use App\Support\DepositCode;

it('sinh mã đúng định dạng BP + 6 ký tự không nhầm lẫn', function () {
    foreach (range(1, 500) as $_) {
        expect(DepositCode::generate())->toMatch(DepositCode::PATTERN);
    }
});

it('mỗi user có mã nạp duy nhất qua 1.000 user', function () {
    User::factory()->count(1000)->create();

    expect(User::distinct()->count('deposit_code'))->toBe(1000)
        ->and(User::pluck('deposit_code')->every(fn ($c) => preg_match(DepositCode::PATTERN, $c)))->toBeTrue();
});
