<?php

namespace App\Filament\Resources\BankTransactions\Pages;

use App\Filament\Resources\BankTransactions\BankTransactionActions;
use App\Filament\Resources\BankTransactions\BankTransactionResource;
use App\Models\BankTransaction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBankTransactions extends ListRecords
{
    protected static string $resource = BankTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return BankTransactionActions::header();
    }

    public function getTabs(): array
    {
        return [
            'unmatched' => Tab::make(__('admin.bank_transactions.tabs.unmatched'))
                ->badge(fn () => BankTransaction::unmatched()->count() ?: null)->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', BankTransaction::STATUS_UNMATCHED)),
            'credited' => Tab::make(__('admin.bank_transactions.tabs.credited'))->modifyQueryUsing(fn (Builder $query) => $query->where('status', BankTransaction::STATUS_CREDITED)),
            'ignored' => Tab::make(__('admin.bank_transactions.tabs.ignored'))->modifyQueryUsing(fn (Builder $query) => $query->where('status', BankTransaction::STATUS_IGNORED)),
            'all' => Tab::make(__('admin.bank_transactions.tabs.all')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'unmatched';
    }
}
