<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Công tắc truy cập trang khách. Mặc định giữ hành vi cũ (đăng ký + Google bật, không bảo trì).
 */
class AccessSettings extends Settings
{
    public bool $registration_enabled = true;

    public bool $google_login_enabled = true;

    public bool $customer_2fa_enabled = true;

    public bool $maintenance_enabled = false;

    public ?string $maintenance_message = null;

    public static function group(): string
    {
        return 'access';
    }
}
