<?php

namespace App\Filament\Resources\WalletTransactions;

use App\Enums\TxType;
use App\Filament\Resources\WalletTransactions\Pages\ListWalletTransactions;
use App\Filament\Support\MoneyColumn;
use App\Models\WalletTransaction;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Sổ cái toàn hệ thống — chỉ đọc. */
class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 50;

    public static function getModelLabel(): string
    {
        return __('admin.wallet_transactions.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.wallet_transactions.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.books');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'creator']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->fontFamily('mono'),
                TextColumn::make('created_at')->label(__('admin.common.time'))->dateTime('d/m/Y H:i:s'),
                TextColumn::make('user.name')->label(__('admin.common.customer'))->description(fn ($record) => $record->user?->email)->searchable(),
                TextColumn::make('type')->label(__('admin.common.type'))->badge()
                    ->formatStateUsing(fn (TxType $state) => $state->label())->color(fn (TxType $state) => $state->color()),
                MoneyColumn::make('amount', signed: true)->label(__('admin.common.amount')),
                MoneyColumn::make('balance_after')->label(__('admin.common.balance_after')),
                TextColumn::make('description')->label(__('admin.common.note'))->limit(50)->placeholder('—'),
                TextColumn::make('creator.name')->label(__('admin.common.admin'))->placeholder('—'),
                TextColumn::make('idempotency_key')->label(__('admin.wallet_transactions.key'))->size('xs')->color('gray')->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('admin.common.type'))->options(TxType::options())->multiple(),
                SelectFilter::make('user_id')->label(__('admin.common.customer'))->relationship('user', 'email')->searchable(),
                SelectFilter::make('created_by')->label(__('admin.common.admin'))->relationship('creator', 'name', fn (Builder $query) => $query->where('is_admin', true)),
                Filter::make('created_at')->label(__('admin.common.date_range'))
                    ->schema([DatePicker::make('from')->label(__('admin.common.date_from')), DatePicker::make('until')->label(__('admin.common.date_until'))])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListWalletTransactions::route('/')];
    }
}
