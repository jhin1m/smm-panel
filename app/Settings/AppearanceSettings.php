<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Giao diện trang khách. custom_* chỉ render cho người xem không phải admin.
 */
class AppearanceSettings extends Settings
{
    public string $primary_color = '#1f47e6';

    public ?string $custom_css = null;

    public ?string $custom_head_html = null;

    public ?string $custom_body_html = null;

    public static function group(): string
    {
        return 'appearance';
    }
}
