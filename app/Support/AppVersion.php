<?php

namespace App\Support;

/**
 * Phiên bản đang chạy lấy từ file VERSION do Docker build ghi vào image (không đọc từ .env, vì .env chỉ
 * quyết định tag image cần chạy). Không có file (chạy từ source) → "dev".
 */
class AppVersion
{
    public const FALLBACK = 'dev';

    public static function fromFile(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return self::FALLBACK;
        }

        $version = trim((string) file_get_contents($path));

        return $version !== '' ? $version : self::FALLBACK;
    }
}
