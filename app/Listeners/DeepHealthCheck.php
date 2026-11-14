<?php

namespace App\Listeners;

use App\Settings\GeneralSettings;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;

/**
 * Mở rộng route /up: ngoài "PHP chạy được", còn kiểm DB trả lời, settings đọc được và view khách biên dịch được
 * (gồm manifest Vite của image). Lỗi ở bất kỳ bước nào ném exception → /up trả 500 → container unhealthy.
 */
class DeepHealthCheck
{
    public function handle(DiagnosingHealth $event): void
    {
        DB::select('select 1');

        // Spatie nạp giá trị lười: toArray() ép đọc bảng settings và giải mã payload.
        app(GeneralSettings::class)->toArray();

        // /up nằm ngoài nhóm middleware web: không có session/$errors. Render thuần, không đổi locale hay ghi session.
        view('auth.login')->with('errors', new ViewErrorBag)->render();
    }
}
