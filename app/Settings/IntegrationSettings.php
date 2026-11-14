<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * SMTP / Google / SePay. Giá trị rỗng = dùng cấu hình .env; khác rỗng = đè .env lúc chạy (RuntimeConfig).
 * Các khoá bí mật lưu mã hoá.
 */
class IntegrationSettings extends Settings
{
    public ?string $smtp_host = null;

    public ?int $smtp_port = null;

    public ?string $smtp_username = null;

    public ?string $smtp_password = null;

    /** tls (STARTTLS) | ssl (SMTPS) | none */
    public ?string $smtp_encryption = null;

    public ?string $mail_from_address = null;

    public ?string $google_client_id = null;

    public ?string $google_client_secret = null;

    public ?string $sepay_webhook_secret = null;

    public ?string $sepay_api_token = null;

    public static function group(): string
    {
        return 'integrations';
    }

    public static function encrypted(): array
    {
        return ['smtp_password', 'google_client_secret', 'sepay_webhook_secret', 'sepay_api_token'];
    }
}
