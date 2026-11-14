<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuditsSettingsChanges;
use App\Settings\AccessSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageAccessSettings extends SettingsPage
{
    use AuditsSettingsChanges;

    protected static string $settings = AccessSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.access.nav');
    }

    public function getTitle(): string
    {
        return __('admin.settings.access.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.settings.access.signup_section'))
                ->columns(1)
                ->schema([
                    Toggle::make('registration_enabled')->label(__('admin.settings.access.registration'))
                        ->helperText(__('admin.settings.access.registration_hint')),
                    Toggle::make('google_login_enabled')->label(__('admin.settings.access.google_login'))
                        ->helperText(__('admin.settings.access.google_login_hint')),
                    Toggle::make('customer_2fa_enabled')->label(__('admin.settings.access.customer_2fa'))
                        ->helperText(__('admin.settings.access.customer_2fa_hint')),
                ]),
            Section::make(__('admin.settings.access.maintenance_section'))
                ->description(__('admin.settings.access.maintenance_description'))
                ->columns(1)
                ->schema([
                    Toggle::make('maintenance_enabled')->label(__('admin.settings.access.maintenance')),
                    Textarea::make('maintenance_message')->label(__('admin.settings.access.maintenance_message'))
                        ->rows(3)->maxLength(500)
                        ->helperText(__('admin.settings.access.maintenance_message_hint')),
                ]),
        ]);
    }
}
