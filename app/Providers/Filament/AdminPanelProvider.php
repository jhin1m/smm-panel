<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\RevenueChart;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\SetLocale;
use App\Support\Branding;
use App\Support\Locales;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Bảng xám theo token mockup: 50 = nền trang, 100 = surface-2, 200 = viền, 500 = chữ phụ, 950 = chữ chính.
     *
     * @var array<int, string>
     */
    private const GRAY = [
        50 => '#f2f4f9',
        100 => '#e9edf6',
        200 => '#d8deec',
        300 => '#bcc5d8',
        400 => '#8d98b0',
        500 => '#5a667f',
        600 => '#48536b',
        700 => '#364057',
        800 => '#222b40',
        900 => '#182136',
        950 => '#111a2e',
    ];

    /**
     * Mỗi ngôn ngữ hỗ trợ một mục trong user menu, POST tới locale.update; ẩn mục của ngôn ngữ đang dùng.
     *
     * @return array<string, Action>
     */
    private function localeMenuItems(): array
    {
        $items = [];

        foreach (Locales::all() as $code => $name) {
            $items["locale-{$code}"] = Action::make("locale-{$code}")
                ->label($name)
                ->icon('heroicon-o-language')
                ->url(fn () => route('locale.update', ['locale' => $code]))
                ->postToUrl()
                ->visible(fn () => app()->getLocale() !== $code);
        }

        return $items;
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Admin bắt buộc MFA TOTP; lần đầu vào /admin bị chuyển tới màn thiết lập.
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()], isRequired: true)
            // Thương hiệu lấy từ settings lúc request (closure), không viết cứng.
            ->brandName(fn () => Branding::siteName())
            ->brandLogo(fn () => Branding::logoUrl())
            ->favicon(fn () => Branding::faviconUrl())
            ->colors(fn () => [
                'primary' => Color::hex(Branding::primaryColor()),
                'info' => Color::hex(Branding::primaryColor()),
                'success' => Color::hex('#0c7f57'),
                'warning' => Color::hex('#9a5a00'),
                'danger' => Color::hex('#bf2d38'),
                'gray' => self::GRAY,
            ])
            ->darkMode(false)
            ->font('Be Vietnam Pro', provider: LocalFontProvider::class)
            ->monoFont('IBM Plex Mono', provider: LocalFontProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->userMenuItems($this->localeMenuItems())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AdminStatsOverview::class,
                RevenueChart::class,
                LatestOrders::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                // Sau StartSession + AuthenticateSession để đọc được users.locale / session('locale').
                SetLocale::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // Panel có nhóm middleware riêng (không dùng nhóm web): chưa cài thì /admin cũng về /install.
                EnsureInstalled::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
