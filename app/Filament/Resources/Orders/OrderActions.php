<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Exceptions\StaleOrderState;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PricingService;
use App\Support\Money;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

/**
 * Action xử lý đơn. Mỗi modal gửi kèm expected_status/expected_done đọc lúc mở → OrderService từ chối nếu đơn đã đổi.
 */
final class OrderActions
{
    /** @return array<Action> */
    public static function all(): array
    {
        return [self::start(), self::progress(), self::complete(), self::refund()];
    }

    private static function service(): OrderService
    {
        return app(OrderService::class);
    }

    /** @return array<string, mixed> */
    private static function expected(Order $o): array
    {
        return ['expected_status' => $o->status->value, 'expected_done' => $o->done];
    }

    /** @return array<Hidden> */
    private static function expectedFields(): array
    {
        return [Hidden::make('expected_status'), Hidden::make('expected_done')];
    }

    /** Chạy thao tác, đổi lỗi nghiệp vụ thành thông báo + giữ modal. */
    private static function run(Action $action, Closure $fn, string $ok): void
    {
        try {
            $fn();
        } catch (StaleOrderState|InvalidOrderTransition $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $action->cancel();
        } catch (ValidationException $e) {
            // Lỗi nghiệp vụ từ OrderService (key không khớp field modal) → hiện thông báo, giữ modal.
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
            $action->halt();
        }
        Notification::make()->success()->title($ok)->send();
    }

    public static function start(): Action
    {
        return Action::make('start')->label(__('admin.orders.actions.start.label'))->icon('heroicon-o-play')->color('info')
            ->visible(fn (Order $record) => $record->status === OrderStatus::Pending)
            ->requiresConfirmation()
            ->fillForm(fn (Order $record) => self::expected($record))
            ->schema(self::expectedFields())
            ->action(fn (Order $record, array $data, Action $action) => self::run($action,
                fn () => self::service()->start($record, OrderStatus::from($data['expected_status']), (int) $data['expected_done']),
                __('admin.orders.actions.start.done', ['id' => $record->id])));
    }

    public static function progress(): Action
    {
        return Action::make('progress')->label(__('admin.orders.actions.progress.label'))->icon('heroicon-o-chart-bar')
            ->visible(fn (Order $record) => in_array($record->status, [OrderStatus::Pending, OrderStatus::Processing], true))
            ->fillForm(fn (Order $record) => self::expected($record) + ['done' => $record->done])
            ->schema(fn (Order $record) => [
                TextInput::make('done')->label(__('admin.orders.actions.progress.field', ['done' => $record->done, 'quantity' => $record->quantity]))
                    ->integer()->required()->minValue($record->done)->maxValue($record->quantity),
                ...self::expectedFields(),
            ])
            ->action(fn (Order $record, array $data, Action $action) => self::run($action,
                fn () => self::service()->updateProgress($record, (int) $data['done'], OrderStatus::from($data['expected_status']), (int) $data['expected_done']),
                __('admin.orders.actions.progress.done')));
    }

    public static function complete(): Action
    {
        return Action::make('complete')->label(__('admin.orders.actions.complete.label'))->icon('heroicon-o-check-circle')->color('success')
            ->visible(fn (Order $record) => in_array($record->status, [OrderStatus::Pending, OrderStatus::Processing], true))
            ->requiresConfirmation()
            ->modalDescription(__('admin.orders.actions.complete.description'))
            ->fillForm(fn (Order $record) => self::expected($record))
            ->schema(self::expectedFields())
            ->action(fn (Order $record, array $data, Action $action) => self::run($action,
                fn () => self::service()->complete($record, OrderStatus::from($data['expected_status']), (int) $data['expected_done']),
                __('admin.orders.actions.complete.done', ['id' => $record->id])));
    }

    public static function refund(): Action
    {
        return Action::make('refund')->label(__('admin.orders.actions.refund.label'))->icon('heroicon-o-arrow-uturn-left')->color('danger')
            ->visible(fn (Order $record) => $record->status !== OrderStatus::Refunded)
            ->modalDescription(__('admin.orders.actions.refund.description'))
            ->fillForm(fn (Order $record) => self::expected($record) + ['done' => $record->done])
            ->schema(fn (Order $record) => [
                TextInput::make('done')->label(__('admin.orders.actions.refund.done_field', ['quantity' => $record->quantity]))->integer()->required()
                    ->minValue($record->status === OrderStatus::Completed ? 0 : $record->done)
                    ->maxValue($record->status === OrderStatus::Completed ? $record->quantity - 1 : $record->quantity)
                    ->live(debounce: 300),
                Text::make(function (Get $get) use ($record) {
                    $done = filter_var($get('done'), FILTER_VALIDATE_INT);
                    if ($done === false || $done < 0 || $done > $record->quantity) {
                        return __('admin.orders.actions.refund.amount_unknown');
                    }

                    return __('admin.orders.actions.refund.amount', ['amount' => Money::vnd(app(PricingService::class)->refundAmount($record->total, $record->quantity, $done))]);
                }),
                Textarea::make('reason')->label(__('admin.common.reason'))->maxLength(255)
                    ->required($record->status === OrderStatus::Completed)
                    ->helperText($record->status === OrderStatus::Completed ? __('admin.orders.actions.refund.reason_hint') : null),
                ...self::expectedFields(),
            ])
            ->action(fn (Order $record, array $data, Action $action) => self::run($action,
                fn () => self::service()->refund($record, (int) $data['done'], OrderStatus::from($data['expected_status']), (int) $data['expected_done'], auth()->user(), $data['reason'] ?? null),
                __('admin.orders.actions.refund.done', ['id' => $record->id])));
    }
}
