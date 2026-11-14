<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ChatInbox;
use App\Filament\Resources\BankTransactions\BankTransactionResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Services\AdminMetrics;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $m = app(AdminMetrics::class);

        return [
            Stat::make(__('admin.widgets.revenue_7d'), Money::vnd($m->revenue7d()))
                ->description(__('admin.widgets.revenue_7d_desc')),
            Stat::make(__('admin.widgets.deposits_7d'), Money::vnd($m->deposits7d()))
                ->description(__('admin.widgets.deposits_7d_desc')),
            Stat::make(__('admin.widgets.pending_orders'), (string) $m->pendingOrders())
                ->description(__('admin.widgets.pending_orders_desc'))->color('warning')
                ->url(OrderResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending']]])),
            Stat::make(__('admin.widgets.new_customers'), (string) $m->newCustomers7d()),
            Stat::make(__('admin.widgets.unread_chats'), (string) $m->unreadChats())
                ->description(__('admin.widgets.unread_chats_desc'))->url(ChatInbox::getUrl()),
            Stat::make(__('admin.widgets.unmatched'), (string) $m->unmatchedDeposits())
                ->description(__('admin.widgets.unmatched_desc'))->color('warning')
                ->url(BankTransactionResource::getUrl('index', ['activeTab' => 'unmatched'])),
        ];
    }
}
