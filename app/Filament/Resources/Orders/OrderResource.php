<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Support\MoneyColumn;
use App\Models\Order;
use App\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Xử lý đơn tay: không tạo/sửa/xoá, chỉ chuyển trạng thái qua OrderService. */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('admin.orders.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.orders.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Order::pending()->count();

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

    /** Cột dùng chung cho danh sách đơn và widget "Đơn mới nhất". */
    public static function columns(): array
    {
        return [
            TextColumn::make('id')->label(__('admin.orders.id'))->formatStateUsing(fn ($state) => "#{$state}")->fontFamily('mono')->searchable(),
            TextColumn::make('user.name')->label(__('admin.common.customer'))->description(fn (Order $record) => $record->user?->email)
                ->searchable(['name', 'email']),
            TextColumn::make('service_name')->label(__('admin.orders.service'))->wrap()->weight('semibold')
                ->description(fn (Order $record) => $record->note ? __('admin.orders.note_prefix', ['note' => $record->note]) : null),
            TextColumn::make('link')->label(__('admin.orders.link'))->limit(36)->copyable()
                ->url(fn (Order $record) => $record->link, shouldOpenInNewTab: true)
                ->extraAttributes(['rel' => 'noopener noreferrer'])
                ->tooltip(fn (Order $record) => $record->link),
            ViewColumn::make('done')->label(__('admin.orders.progress'))->view('filament.tables.order-progress'),
            MoneyColumn::make('total')->label(__('admin.orders.total'))
                ->description(fn (Order $record) => $record->refunded_amount ? __('admin.orders.refunded', ['amount' => Money::vnd($record->refunded_amount)]) : null),
            TextColumn::make('status')->label(__('admin.common.status'))->badge()
                ->formatStateUsing(fn (OrderStatus $state) => $state->label())->color(fn (OrderStatus $state) => $state->color()),
            TextColumn::make('created_at')->label(__('admin.common.time'))->dateTime('d/m H:i')->sortable(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('id', 'desc')
            ->poll('30s')
            ->columns(self::columns())
            ->filters([
                SelectFilter::make('status')->label(__('admin.common.status'))->options(OrderStatus::options()),
            ])
            ->recordActions(OrderActions::all());
    }

    public static function getPages(): array
    {
        return ['index' => ListOrders::route('/')];
    }
}
