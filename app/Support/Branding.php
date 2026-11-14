<?php

namespace App\Support;

use App\Settings\AccessSettings;
use App\Settings\AppearanceSettings;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Đọc settings thương hiệu/giao diện cho view và Filament. Lỗi DB (chưa migrate, APP_KEY sai...) không
 * được làm hỏng trang: trả về giá trị mặc định.
 */
class Branding
{
    public const DEFAULT_COLOR = '#1f47e6';

    public static function siteName(): string
    {
        // Đọc trực tiếp settings để đổi tên có hiệu lực ngay trong cùng request; lỗi DB → tên từ .env.
        return filled(self::general()?->site_name) ? self::general()->site_name : (string) config('app.name');
    }

    public static function logoUrl(): ?string
    {
        return self::publicUrl(self::general()?->logo_path);
    }

    public static function faviconUrl(): ?string
    {
        return self::publicUrl(self::general()?->favicon_path);
    }

    public static function primaryColor(): string
    {
        $color = self::appearance()?->primary_color;

        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : self::DEFAULT_COLOR;
    }

    /** Màu nền nhạt của accent (dùng cho chip, hover). */
    public static function softColor(): string
    {
        return 'color-mix(in srgb, '.self::primaryColor().' 14%, #ffffff)';
    }

    /** Chữ trên nền accent: trắng/đen theo độ sáng tương đối của màu chính. */
    public static function onPrimaryColor(): string
    {
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim(self::primaryColor(), '#'), 2));

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#111a2e' : '#ffffff';
    }

    /**
     * Custom CSS/HTML của admin chỉ phục vụ khách: cùng origin + cookie với /admin nên KHÔNG render khi admin đang xem.
     */
    public static function appearanceForViewer(): ?AppearanceSettings
    {
        return auth()->user()?->is_admin ? null : self::appearance();
    }

    public static function access(): ?AccessSettings
    {
        try {
            return app(AccessSettings::class);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public static function general(): ?GeneralSettings
    {
        try {
            return app(GeneralSettings::class);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function appearance(): ?AppearanceSettings
    {
        try {
            return app(AppearanceSettings::class);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function publicUrl(?string $path): ?string
    {
        return filled($path) ? Storage::disk('public')->url($path) : null;
    }
}
