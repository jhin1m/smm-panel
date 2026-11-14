<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mã nạp cố định theo khách: "BP" + 6 ký tự từ bảng 32 ký tự (bỏ O/0/I/1 dễ nhầm), in hoa.
 * Khách ghi mã này vào nội dung chuyển khoản; SePay tách thành trường `code`.
 */
final class DepositCode
{
    public const PREFIX = 'BP';

    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const PATTERN = '/^BP[A-HJ-NP-Z2-9]{6}$/';

    public static function generate(): string
    {
        $code = self::PREFIX;

        for ($i = 0; $i < 6; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /** Sinh mã chưa có trong DB (thử tối đa 5 lần; unique index là lớp chặn cuối). */
    public static function unique(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $code = self::generate();

            if (! DB::table('users')->where('deposit_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Không sinh được mã nạp duy nhất');
    }
}
