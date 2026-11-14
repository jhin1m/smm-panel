<?php

namespace App\Support;

/** Ảnh QR chuyển khoản qua quick link img.vietqr.io (không tự sinh QR). */
final class VietQr
{
    public static function imageUrl(string $bin, string $account, ?int $amount, string $addInfo, string $accountName): string
    {
        $query = array_filter([
            'amount' => $amount && $amount > 0 ? (string) $amount : null,
            'addInfo' => $addInfo,
            'accountName' => $accountName,
        ], fn ($v) => $v !== null && $v !== '');

        return 'https://img.vietqr.io/image/'.rawurlencode($bin).'-'.rawurlencode($account).'-compact2.png?'
            .http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
