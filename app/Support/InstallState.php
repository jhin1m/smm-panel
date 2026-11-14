<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Trạng thái "đã cài" đọc THẲNG từ bảng settings mỗi lần gọi (không qua settings cache/singleton):
 * wizard và middleware phải thấy ngay kết quả của một finish() vừa commit ở request/tiến trình khác.
 * Thiếu dòng = chưa cài.
 */
class InstallState
{
    public static function isInstalled(): bool
    {
        $payload = DB::table('settings')
            ->where('group', 'install')
            ->where('name', 'installed_at')
            ->value('payload');

        return $payload !== null && filled(json_decode((string) $payload, true));
    }
}
