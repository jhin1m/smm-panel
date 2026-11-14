<?php

namespace App\Support;

/** Định dạng tiền VND kiểu vi-VN: 16098 → "16.098đ". */
final class Money
{
    public static function vnd(int $amount): string
    {
        return number_format($amount, 0, ',', '.').'đ';
    }

    /** Số nguyên kiểu vi-VN không hậu tố: 1000 → "1.000". */
    public static function number(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
