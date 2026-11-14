<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\TxType;
use App\Filament\Support\MoneyColumn;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Sổ cái của khách — chỉ đọc. */
class WalletTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'walletTransactions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.common.ledger_title');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('admin.common.time'))->dateTime('d/m/Y H:i:s'),
                TextColumn::make('type')->label(__('admin.common.type'))->badge()
                    ->formatStateUsing(fn (TxType $state) => $state->label())->color(fn (TxType $state) => $state->color()),
                MoneyColumn::make('amount', signed: true)->label(__('admin.common.amount')),
                MoneyColumn::make('balance_after')->label(__('admin.common.balance_after')),
                TextColumn::make('description')->label(__('admin.common.note'))->limit(60)->placeholder('—'),
                TextColumn::make('creator.name')->label(__('admin.common.admin'))->placeholder('—'),
                TextColumn::make('idempotency_key')->label(__('admin.wallet_transactions.key'))->size('xs')->color('gray')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([SelectFilter::make('type')->label(__('admin.common.type'))->options(TxType::options())]);
    }
}
