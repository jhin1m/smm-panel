<?php

namespace App\Support;

use App\Models\User;

/** Khớp giao dịch với khách theo mã nạp: ưu tiên `code` SePay, dự phòng quét `content`; chỉ khớp khi đúng 1 khách (không bao giờ khớp tài khoản admin). */
class DepositCodeMatcher
{
    public function match(?string $code, ?string $content): ?User
    {
        $candidates = filled($code) ? [strtoupper(trim($code))] : [];

        // Bỏ dấu cách/gạch/ký tự lạ rồi tìm mọi chuỗi BP + 6 ký tự (lookahead bắt cả chuỗi chồng lấn).
        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $content));
        preg_match_all('/(?=(BP[A-HJ-NP-Z2-9]{6}))/', $normalized, $m);
        $candidates = array_values(array_unique([...$candidates, ...$m[1]]));

        if ($candidates === []) {
            return null;
        }

        $users = User::where('is_admin', false)->whereIn('deposit_code', $candidates)->limit(2)->get();

        return $users->count() === 1 ? $users->first() : null;
    }
}
