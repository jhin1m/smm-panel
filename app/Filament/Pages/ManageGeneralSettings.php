<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuditsSettingsChanges;
use App\Settings\GeneralSettings;
use App\Support\Locales;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageGeneralSettings extends SettingsPage
{
    use AuditsSettingsChanges;

    protected static string $settings = GeneralSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.general.nav');
    }

    public function getTitle(): string
    {
        return __('admin.settings.general.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.settings.general.brand_section'))
                ->description(__('admin.settings.general.brand_description'))
                ->columns(2)
                ->schema([
                    TextInput::make('site_name')->label(__('admin.settings.general.site_name'))->required()->maxLength(60),
                    Select::make('default_locale')->label(__('admin.settings.general.default_locale'))
                        ->options(Locales::all())->required()->native(false)
                        ->helperText(__('admin.settings.general.default_locale_hint')),
                    // Ảnh PNG/JPG/WebP (không SVG: file phục vụ cùng origin với /admin).
                    FileUpload::make('logo_path')->label(__('admin.settings.general.logo'))
                        ->disk('public')->directory('branding')->visibility('public')
                        ->image()->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->maxSize(1024)
                        ->helperText(__('admin.settings.general.logo_hint')),
                    FileUpload::make('favicon_path')->label(__('admin.settings.general.favicon'))
                        ->disk('public')->directory('branding')->visibility('public')
                        ->image()->acceptedFileTypes(['image/png', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'])->maxSize(512)
                        ->helperText(__('admin.settings.general.favicon_hint')),
                ]),
            Section::make(__('admin.settings.general.support_section'))
                ->description(__('admin.settings.general.support_description'))
                ->columns(3)
                ->schema([
                    TextInput::make('support_email')->label(__('admin.settings.general.support_email'))->email()->maxLength(255),
                    TextInput::make('support_zalo')->label(__('admin.settings.general.support_zalo'))->maxLength(100),
                    TextInput::make('support_telegram')->label(__('admin.settings.general.support_telegram'))->maxLength(100),
                ]),
            Section::make(__('admin.settings.general.seo_section'))
                ->columns(1)
                ->schema([
                    TextInput::make('seo_title')->label(__('admin.settings.general.seo_title'))->maxLength(70),
                    Textarea::make('seo_description')->label(__('admin.settings.general.seo_description'))->rows(2)->maxLength(300),
                ]),
        ]);
    }
}
