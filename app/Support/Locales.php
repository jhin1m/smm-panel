<?php

namespace App\Support;

/**
 * Danh sách ngôn ngữ hệ thống hỗ trợ = thư mục con của lang/ (mỗi thư mục có meta.php với 'name').
 * Cache trong request; flush() cho test hoặc khi thư mục lang đổi giữa request (worker dài hạn).
 */
class Locales
{
    /** @var array<string, string>|null code => tên hiển thị */
    private static ?array $cache = null;

    /** @return array<string, string> code => tên hiển thị */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $found = [];
        // Thư mục custom/lang đọc sau lang lõi: locale mới được thêm, locale trùng chỉ đổi tên nếu có meta.php.
        $roots = [lang_path()];
        if (is_dir($custom = (string) config('app.custom_path').'/lang')) {
            $roots[] = $custom;
        }
        foreach ($roots as $root) {
            foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $code = basename($dir);
                if (! preg_match('/^[a-z]{2,3}(_[A-Za-z]{2,4})?$/', $code)) {
                    continue;
                }
                $meta = is_file("{$dir}/meta.php") ? require "{$dir}/meta.php" : [];
                if (isset($meta['name']) || ! isset($found[$code])) {
                    $found[$code] = (string) ($meta['name'] ?? $code);
                }
            }
        }
        ksort($found);

        return self::$cache = $found;
    }

    /** @return array<int, string> */
    public static function supported(): array
    {
        return array_keys(self::all());
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::all());
    }

    public static function name(string $locale): string
    {
        return self::all()[$locale] ?? $locale;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
