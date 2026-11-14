<?php

namespace App\Support;

use Illuminate\Support\Facades\View;

/**
 * Đăng ký view/lang trong custom/ (nếu tồn tại) để ghi đè bản lõi mà không rebuild image.
 * Không có thư mục thì không làm gì.
 */
class CustomOverrides
{
    public static function register(): void
    {
        $root = (string) config('app.custom_path');

        if (is_dir("{$root}/views")) {
            // Đặt trước resources/views nên view cùng tên trong custom thắng.
            View::prependLocation("{$root}/views");

            // Component Blade ẩn danh (<x-layouts::guest>, <x-foo>) và Livewire đăng ký path riêng dưới resources/views
            // bằng namespace nội bộ, không đi qua location chung: thư mục tương ứng trong custom/views đặt trước path lõi.
            $core = rtrim(resource_path('views'), '/');
            foreach (View::getFinder()->getHints() as $namespace => $paths) {
                foreach ($paths as $path) {
                    $path = rtrim($path, '/');
                    if (! str_starts_with($path, $core.'/')) {
                        continue;
                    }
                    if (is_dir($dir = "{$root}/views".substr($path, strlen($core)))) {
                        View::prependNamespace($namespace, $dir);
                    }
                }
            }
        }

        // Finder nhớ kết quả tìm view đã resolve; xóa để thứ tự path mới có hiệu lực (no-op lúc boot).
        View::getFinder()->flush();

        if (is_dir("{$root}/lang")) {
            // FileLoader gộp các path theo thứ tự thêm bằng array_replace_recursive: path thêm sau thắng từng key.
            app('translator')->getLoader()->addPath("{$root}/lang");
        }

        Locales::flush();
    }
}
