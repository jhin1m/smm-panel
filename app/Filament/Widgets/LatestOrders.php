<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestOrders extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.widgets.latest_orders'))
            ->query(Order::query()->with('user')->latest('id')->limit(5))
            ->paginated(false)
            ->columns(OrderResource::columns())
            ->recordActions(OrderActions::all());
    }
}
