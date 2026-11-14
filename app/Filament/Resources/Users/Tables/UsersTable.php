<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Tier;
use App\Filament\Resources\Users\UserActions;
use App\Filament\Support\MoneyColumn;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('admin.common.name'))->searchable()->weight('semibold'),
                TextColumn::make('email')->label(__('admin.users.email'))->searchable()->size('sm'),
                TextColumn::make('tier')->label(__('admin.common.tier'))->badge()
                    ->formatStateUsing(fn (Tier $state) => $state->label())
                    ->color(fn (Tier $state) => $state->color()),
                MoneyColumn::make('balance')->label(__('admin.common.balance'))->sortable(),
                MoneyColumn::make('total_deposited')->label(__('admin.users.total_deposited'))->sortable(),
                TextColumn::make('deposit_code')->label(__('admin.common.deposit_code'))->searchable()->copyable()->fontFamily(FontFamily::Mono),
                TextColumn::make('created_at')->label(__('admin.users.joined_short'))->date('d/m/Y')->sortable(),
                IconColumn::make('locked_at')->label(__('admin.users.locked'))->boolean()
                    ->state(fn ($record) => $record->locked_at !== null)
                    ->trueColor('danger')->falseColor('success')
                    ->trueIcon('heroicon-o-lock-closed')->falseIcon('heroicon-o-check-circle'),
            ])
            ->filters([
                SelectFilter::make('tier')->label(__('admin.common.tier'))->options(Tier::options()),
                TernaryFilter::make('locked_at')->label(__('admin.common.status'))->nullable()
                    ->trueLabel(__('admin.users.locked_yes'))->falseLabel(__('admin.users.active')),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(UserActions::all()),
            ])
            ->toolbarActions([
                UserActions::bulkPromote(),
            ]);
    }
}
