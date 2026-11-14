<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Trạng thái cài đặt. installed_at = null nghĩa là wizard /install còn mở; có giá trị = đã khoá vĩnh viễn.
 * Mặc định null theo quy ước chung (mọi property có default PHP).
 */
class InstallSettings extends Settings
{
    public ?string $installed_at = null;

    public static function group(): string
    {
        return 'install';
    }
}
