<?php

namespace App\Listeners;

use App\Settings\GeneralSettings;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;

/**
 * Mở rộng route /up: ngoài "PHP chạy được", còn kiểm DB trả lời, settings đọc được và view khách LÕI biên dịch được
 * (gồm manifest Vite của image). Lỗi ở bất kỳ bước nào ném exception → /up trả 500 → container unhealthy.
 *
 * View đăng nhập được render từ file lõi và finder tạm thời bỏ mọi đường dẫn thuộc custom/views (cả path chung lẫn
 * namespace của component ẩn danh/Livewire), nên view tuỳ biến lỗi không làm container unhealthy (khi đó mọi lần cập nhật
 * bị rollback và caddy không khởi động được). Phạm vi bỏ qua: mọi view/component/include phân giải qua finder của view factory.
 * KHÔNG bỏ qua: bản dịch trong custom/lang (không ảnh hưởng độ biên dịch) và PHP/Blade directive đã nạp từ trước.
 */
class DeepHealthCheck
{
    public function handle(DiagnosingHealth $event): void
    {
        DB::select('select 1');

        // Spatie nạp giá trị lười: toArray() ép đọc bảng settings và giải mã payload.
        app(GeneralSettings::class)->toArray();

        $this->renderCoreLogin();
    }

    /** /up nằm ngoài nhóm middleware web: không có session/$errors. Render thuần, không đổi locale hay ghi session. */
    private function renderCoreLogin(): void
    {
        $factory = view();
        $finder = $factory->getFinder();
        $custom = rtrim((string) config('app.custom_path'), '/').'/views';
        $isCore = fn (string $path): bool => $path !== $custom && ! str_starts_with(rtrim($path, '/'), $custom.'/');

        $paths = $finder->getPaths();
        $hints = $finder->getHints();

        try {
            $finder->setPaths(array_values(array_filter($paths, $isCore)));
            foreach ($hints as $namespace => $dirs) {
                $finder->replaceNamespace($namespace, array_values(array_filter($dirs, $isCore)));
            }
            $finder->flush();

            $factory->file(resource_path('views/auth/login.blade.php'))
                ->with('errors', new ViewErrorBag)
                ->render();
        } finally {
            // Khôi phục để các request sau (worker dùng lại process) vẫn thấy view tuỳ biến.
            $finder->setPaths($paths);
            foreach ($hints as $namespace => $dirs) {
                $finder->replaceNamespace($namespace, $dirs);
            }
            $finder->flush();
        }
    }
}
