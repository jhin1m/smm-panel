<?php

namespace App\Support;

use App\Settings\GeneralSettings;
use App\Settings\IntegrationSettings;
use Throwable;

/**
 * Áp cấu hình lưu trong DB (settings) đè lên config lúc runtime, MỖI request/job, trong bộ nhớ.
 *
 * - DB khác rỗng thắng .env; DB rỗng → giữ nguyên giá trị .env (kể cả khi xoá giá trị trong admin).
 * - Không bao giờ chạy trong các lệnh build/migrate: nhờ vậy `config:cache` chỉ chứa giá trị .env,
 *   bí mật giải mã từ DB không bị ghi ra bootstrap/cache/config.php.
 * - Mọi lỗi (bảng chưa có, thiếu dòng, APP_KEY đổi nên không giải mã được...) bị nuốt + report:
 *   boot hay `migrate` khi nâng cấp không được phép crash.
 */
class RuntimeConfig
{
    /** Lệnh artisan KHÔNG áp cấu hình DB. Mục kết thúc bằng ':' là tiền tố. */
    private const SKIP_COMMANDS = [
        'migrate', 'config:cache', 'config:clear', 'optimize', 'optimize:clear', 'package:discover', 'filament:upgrade',
    ];

    private const SKIP_PREFIXES = ['migrate:', 'settings:'];

    public static function apply(): void
    {
        if (self::shouldSkip()) {
            return;
        }

        try {
            self::applyGeneral(app(GeneralSettings::class));
            self::applyIntegrations(app(IntegrationSettings::class));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function shouldSkip(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        // Xét mọi token không phải option, không chỉ argv[1]: `artisan -q optimize`, `artisan --env prod config:cache`
        // đặt tên lệnh ở vị trí sau. Bỏ qua nhầm (token trùng tên lệnh) chỉ khiến lệnh đó dùng config .env, vô hại.
        foreach (array_slice($_SERVER['argv'] ?? [], 1) as $token) {
            if (! is_string($token) || $token === '' || $token[0] === '-') {
                continue;
            }

            if (in_array($token, self::SKIP_COMMANDS, true)) {
                return true;
            }

            foreach (self::SKIP_PREFIXES as $prefix) {
                if (str_starts_with($token, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function applyGeneral(GeneralSettings $general): void
    {
        if (filled($general->site_name)) {
            config(['app.name' => $general->site_name, 'mail.from.name' => $general->site_name]);
        }

        if (filled($general->default_locale) && Locales::isSupported($general->default_locale)) {
            config(['app.default_locale' => $general->default_locale]);
        }
    }

    private static function applyIntegrations(IntegrationSettings $i): void
    {
        if (filled($i->smtp_host)) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.url' => null,
                'mail.mailers.smtp.host' => $i->smtp_host,
                // smtps = TLS ngay khi kết nối; smtp tự nâng cấp STARTTLS nếu máy chủ hỗ trợ.
                'mail.mailers.smtp.scheme' => $i->smtp_encryption === 'ssl' ? 'smtps' : 'smtp',
            ]);
            self::set('mail.mailers.smtp.port', $i->smtp_port);
            // Host do admin đặt → dùng trọn bộ thông tin đăng nhập trong DB (kể cả rỗng), không bao giờ
            // gửi MAIL_USERNAME/MAIL_PASSWORD của .env tới máy chủ do admin chọn.
            config([
                'mail.mailers.smtp.username' => filled($i->smtp_username) ? $i->smtp_username : null,
                'mail.mailers.smtp.password' => filled($i->smtp_password) ? $i->smtp_password : null,
            ]);
        }

        self::set('mail.from.address', $i->mail_from_address);

        if (filled($i->google_client_id)) {
            config([
                'services.google.client_id' => $i->google_client_id,
                'services.google.redirect' => url('/auth/google/callback'),
            ]);
        }
        self::set('services.google.client_secret', $i->google_client_secret);

        self::set('sepay.webhook_secret', $i->sepay_webhook_secret);
        self::set('sepay.api_token', $i->sepay_api_token);
    }

    /** Chỉ ghi đè khi giá trị DB khác rỗng. */
    private static function set(string $key, mixed $value): void
    {
        if (filled($value)) {
            config([$key => $value]);
        }
    }
}
