<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuditsSettingsChanges;
use App\Services\TierService;
use App\Settings\TierSettings;
use App\Support\AuditLog;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageTierSettings extends SettingsPage
{
    use AuditsSettingsChanges;

    protected static string $settings = TierSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.tier.title');
    }

    public function getTitle(): string
    {
        return __('admin.settings.tier.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.settings.tier.agency_section'))
                ->description(__('admin.settings.tier.agency_description'))
                ->columns(3)->columnSpanFull()
                ->schema([
                    TextInput::make('agency_threshold')->label(__('admin.settings.tier.agency_threshold'))->integer()->required()->minValue(0)->maxValue(10_000_000_000)->suffix('đ'),
                    TextInput::make('agency_discount_percent')->label(__('admin.settings.tier.agency_discount'))->integer()->required()->minValue(0)->maxValue(100)->suffix('%'),
                    TextInput::make('member_discount_percent')->label(__('admin.settings.tier.member_discount'))->integer()->required()->minValue(0)->maxValue(100)->suffix('%'),
                ]),
        ]);
    }

    protected function afterSettingsAudited(array $before, array $changed): void
    {
        $promoted = app(TierService::class)->promoteAllEligible();

        if ($promoted > 0) {
            AuditLog::record(auth()->user(), 'tier.promote_all', null, ['promoted' => $promoted, 'threshold' => app(TierSettings::class)->agency_threshold]);
            Notification::make()->success()->title(__('admin.settings.tier.promoted', ['count' => $promoted]))->send();
        }
    }
}
