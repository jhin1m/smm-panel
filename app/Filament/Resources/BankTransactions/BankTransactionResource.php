<?php

namespace App\Filament\Resources\BankTransactions;

use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Filament\Support\MoneyColumn;
use App\Models\BankTransaction;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Giao dịch ngân hàng SePay — không sửa/xoá; xử lý qua action gán/huỷ gán/bỏ qua. */
class BankTransactionResource extends Resource
{
    protected static ?string $model = BankTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return __('admin.bank_transactions.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.bank_transactions.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = BankTransaction::unmatched()->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('sepay_id')->label(__('admin.bank_transactions.sepay_id'))->fontFamily('mono'),
                TextEntry::make('source')->label(__('admin.bank_transactions.source'))->formatStateUsing(fn ($state) => BankTransaction::sourceLabel($state)),
                TextEntry::make('status')->label(__('admin.common.status'))->badge()
                    ->formatStateUsing(fn ($state) => BankTransaction::statusLabel($state))->color(fn ($state) => BankTransaction::statusColor($state)),
                TextEntry::make('transfer_amount')->label(__('admin.common.amount'))->formatStateUsing(fn ($state) => Money::vnd((int) $state))->fontFamily('mono'),
                TextEntry::make('bonus_percent')->label(__('admin.bank_transactions.bonus_percent_locked'))->suffix('%'),
                TextEntry::make('bonus_amount')->label(__('admin.bank_transactions.bonus_credited'))->formatStateUsing(fn ($state) => Money::vnd((int) $state)),
                TextEntry::make('content')->label(__('admin.bank_transactions.content'))->columnSpanFull(),
                TextEntry::make('code')->label(__('admin.bank_transactions.sepay_code'))->placeholder('—')->fontFamily('mono'),
                TextEntry::make('reference_code')->label(__('admin.bank_transactions.reference_code'))->placeholder('—'),
                TextEntry::make('transaction_date')->label(__('admin.bank_transactions.tx_date'))->dateTime('d/m/Y H:i:s'),
                TextEntry::make('user.email')->label(__('admin.common.customer'))->placeholder('—'),
                TextEntry::make('assignedBy.name')->label(__('admin.bank_transactions.assigned_by'))->placeholder(__('admin.bank_transactions.automatic')),
                TextEntry::make('note')->label(__('admin.common.note'))->state(fn (BankTransaction $record) => $record->noteLabel())->placeholder('—'),
            ]),
            Section::make(__('admin.bank_transactions.payload'))->collapsed()->columnSpanFull()->schema([
                CodeEntry::make('payload')->hiddenLabel()
                    ->state(fn (BankTransaction $record) => json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('transaction_date')->label(__('admin.bank_transactions.tx_date'))->dateTime('d/m H:i')->sortable(),
                TextColumn::make('source')->label(__('admin.bank_transactions.source'))->formatStateUsing(fn ($state) => BankTransaction::sourceLabel($state))->size('xs'),
                TextColumn::make('gateway')->label(__('admin.bank_transactions.bank'))->placeholder('—'),
                MoneyColumn::make('transfer_amount')->label(__('admin.common.amount'))
                    ->color(fn (BankTransaction $record) => $record->transfer_type === 'in' ? 'success' : 'danger'),
                TextColumn::make('bonus_percent')->label(__('admin.bank_transactions.bonus_percent'))->suffix('%')->alignEnd(),
                TextColumn::make('content')->label(__('admin.bank_transactions.content'))->limit(40)->tooltip(fn (BankTransaction $record) => $record->content)->searchable(['content', 'code']),
                TextColumn::make('user.name')->label(__('admin.common.customer'))->description(fn (BankTransaction $record) => $record->user?->deposit_code)->placeholder('—'),
                TextColumn::make('status')->label(__('admin.common.status'))->badge()
                    ->formatStateUsing(fn ($state) => BankTransaction::statusLabel($state))->color(fn ($state) => BankTransaction::statusColor($state)),
            ])
            ->recordActions([
                ViewAction::make(),
                ...BankTransactionActions::record(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListBankTransactions::route('/')];
    }
}
