<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.users.account'))->columns(3)->columnSpanFull()->schema([
                TextEntry::make('name')->label(__('admin.users.display_name')),
                TextEntry::make('email')->label(__('admin.users.email'))->copyable(),
                TextEntry::make('deposit_code')->label(__('admin.common.deposit_code'))->copyable()->fontFamily(FontFamily::Mono),
                TextEntry::make('tier')->label(__('admin.common.tier'))->badge()
                    ->formatStateUsing(fn ($state, $record) => $record->tier_manual ? __('admin.users.tier_manual', ['tier' => $state->label()]) : $state->label())
                    ->color(fn ($state) => $state->color()),
                TextEntry::make('balance')->label(__('admin.common.balance'))->formatStateUsing(fn ($state) => Money::vnd((int) $state))->fontFamily(FontFamily::Mono),
                TextEntry::make('total_deposited')->label(__('admin.users.total_deposited'))->formatStateUsing(fn ($state) => Money::vnd((int) $state))->fontFamily(FontFamily::Mono),
                TextEntry::make('locked_at')->label(__('admin.common.status'))->badge()
                    ->state(fn ($record) => $record->locked_at ? __('admin.users.locked_yes') : __('admin.users.active'))
                    ->color(fn ($record) => $record->locked_at ? 'danger' : 'success'),
                TextEntry::make('created_at')->label(__('admin.users.joined'))->dateTime('d/m/Y H:i'),
                TextEntry::make('google_id')->label(__('admin.users.google'))->state(fn ($record) => $record->google_id ? __('admin.users.google_linked') : __('admin.users.google_unlinked')),
            ]),
        ]);
    }
}
