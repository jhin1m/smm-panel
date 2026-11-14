<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuditsSettingsChanges;
use App\Settings\AppearanceSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageAppearanceSettings extends SettingsPage
{
    use AuditsSettingsChanges;

    protected static string $settings = AppearanceSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.appearance.nav');
    }

    public function getTitle(): string
    {
        return __('admin.settings.appearance.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.settings.appearance.color_section'))
                ->schema([
                    TextInput::make('primary_color')->label(__('admin.settings.appearance.primary_color'))
                        ->required()->regex('/^#[0-9a-fA-F]{6}$/')->placeholder('#1f47e6')->maxLength(7)
                        ->helperText(__('admin.settings.appearance.primary_color_hint')),
                ]),
            Section::make(__('admin.settings.appearance.custom_section'))
                ->description(__('admin.settings.appearance.custom_description'))
                ->columns(1)
                ->schema([
                    Textarea::make('custom_css')->label(__('admin.settings.appearance.custom_css'))->rows(6)->maxLength(20000)
                        ->extraInputAttributes(['class' => 'font-mono']),
                    Textarea::make('custom_head_html')->label(__('admin.settings.appearance.custom_head_html'))->rows(5)->maxLength(20000)
                        ->extraInputAttributes(['class' => 'font-mono'])
                        ->helperText(__('admin.settings.appearance.custom_html_warning')),
                    Textarea::make('custom_body_html')->label(__('admin.settings.appearance.custom_body_html'))->rows(5)->maxLength(20000)
                        ->extraInputAttributes(['class' => 'font-mono'])
                        ->helperText(__('admin.settings.appearance.custom_html_warning')),
                ]),
        ]);
    }
}
