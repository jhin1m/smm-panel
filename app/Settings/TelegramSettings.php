<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Bot Telegram báo admin (tin chat mới, sự cố webhook, backup lỗi...).
 * bot_token lưu mã hoá trong bảng settings.
 */
class TelegramSettings extends Settings
{
    public bool $enabled;

    public ?string $bot_token;

    public ?string $chat_id;

    public static function group(): string
    {
        return 'telegram';
    }

    public static function encrypted(): array
    {
        return ['bot_token'];
    }
}
