<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Thương hiệu + liên hệ + SEO. Quy ước: MỌI property có giá trị mặc định PHP, để instance cũ
 * (chưa có dòng trong bảng settings) vẫn đọc được thay vì ném MissingSettings.
 * site_name / default_locale để trống = dùng giá trị từ .env.
 */
class GeneralSettings extends Settings
{
    public string $site_name = '';

    public ?string $logo_path = null;

    public ?string $favicon_path = null;

    public ?string $support_email = null;

    public ?string $support_zalo = null;

    public ?string $support_telegram = null;

    public ?string $default_locale = null;

    public ?string $seo_title = null;

    public ?string $seo_description = null;

    public static function group(): string
    {
        return 'general';
    }
}
