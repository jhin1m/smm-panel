<?php

namespace App\Filament\Resources\Users;

use App\Enums\Tier;
use App\Exceptions\InsufficientBalance;
use App\Models\User;
use App\Services\UserAdminService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/** Action dùng chung cho bảng khách hàng và trang xem chi tiết. Mọi action ghi audit qua UserAdminService. */
final class UserActions
{
    /** @return array<Action> */
    public static function all(): array
    {
        return [self::lock(), self::unlock(), self::changeTier(), self::resetTierAuto(), self::adjustBalance(), self::reissueDepositCode(), self::resetTwoFactor()];
    }

    private static function service(): UserAdminService
    {
        return app(UserAdminService::class);
    }

    private static function admin(): User
    {
        return auth()->user();
    }

    public static function lock(): Action
    {
        return Action::make('lock')->label(__('admin.users.actions.lock.label'))->icon('heroicon-o-lock-closed')->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('admin.users.actions.lock.description'))
            ->visible(fn (User $record) => $record->locked_at === null && $record->id !== auth()->id())
            ->action(function (User $record) {
                self::service()->lock($record, self::admin());
                Notification::make()->success()->title(__('admin.users.actions.lock.done'))->send();
            });
    }

    public static function unlock(): Action
    {
        return Action::make('unlock')->label(__('admin.users.actions.unlock.label'))->icon('heroicon-o-lock-open')->color('success')
            ->requiresConfirmation()
            ->visible(fn (User $record) => $record->locked_at !== null)
            ->action(function (User $record) {
                self::service()->unlock($record, self::admin());
                Notification::make()->success()->title(__('admin.users.actions.unlock.done'))->send();
            });
    }

    public static function changeTier(): Action
    {
        return Action::make('changeTier')->label(__('admin.users.actions.change_tier.label'))->icon('heroicon-o-star')
            ->fillForm(fn (User $record) => ['tier' => $record->tier->value])
            ->schema([
                Select::make('tier')->label(__('admin.common.tier'))->options(Tier::options())->required(),
                Text::make(__('admin.users.actions.change_tier.hint')),
            ])
            ->action(function (User $record, array $data) {
                self::service()->changeTier($record, Tier::from($data['tier']), self::admin());
                Notification::make()->success()->title(__('admin.users.actions.change_tier.done'))->send();
            });
    }

    public static function resetTierAuto(): Action
    {
        return Action::make('resetTierAuto')->label(__('admin.users.actions.reset_tier_auto.label'))->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->visible(fn (User $record) => $record->tier_manual)
            ->action(function (User $record) {
                self::service()->resetTierAuto($record, self::admin());
                Notification::make()->success()->title(__('admin.users.actions.reset_tier_auto.done'))->send();
            });
    }

    public static function adjustBalance(): Action
    {
        return Action::make('adjustBalance')->label(__('admin.users.actions.adjust_balance.label'))->icon('heroicon-o-banknotes')->color('warning')
            ->visible(fn (User $record) => $record->id !== auth()->id())
            // Key sinh lúc mở modal: bấm "Xác nhận" 2 lần vẫn chỉ 1 bút toán.
            ->fillForm(fn () => ['idempotency_key' => 'adjust:'.Str::uuid()])
            ->schema([
                Text::make(__('admin.users.actions.adjust_balance.warning'))
                    ->color('danger'),
                TextInput::make('amount')->label(__('admin.users.actions.adjust_balance.amount'))->integer()->required()
                    ->notIn([0])->minValue(-100_000_000)->maxValue(100_000_000)->suffix('đ'),
                Textarea::make('reason')->label(__('admin.common.reason'))->required()->maxLength(255),
                Hidden::make('idempotency_key'),
            ])
            ->action(function (User $record, array $data, Action $action) {
                try {
                    $tx = self::service()->adjustBalance($record, (int) $data['amount'], $data['reason'], self::admin(), $data['idempotency_key']);
                } catch (InsufficientBalance $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();
                }
                Notification::make()->success()->title(__('admin.users.actions.adjust_balance.done', ['balance' => Money::vnd($tx->balance_after)]))->send();
            });
    }

    public static function reissueDepositCode(): Action
    {
        return Action::make('reissueDepositCode')->label(__('admin.users.actions.reissue_deposit_code.label'))->icon('heroicon-o-qr-code')
            ->requiresConfirmation()
            ->modalDescription(__('admin.users.actions.reissue_deposit_code.description'))
            ->action(function (User $record) {
                $code = self::service()->reissueDepositCode($record, self::admin());
                Notification::make()->success()->title(__('admin.users.actions.reissue_deposit_code.done', ['code' => $code]))->send();
            });
    }

    public static function resetTwoFactor(): Action
    {
        return Action::make('resetTwoFactor')->label(__('admin.users.actions.reset_two_factor.label'))->icon('heroicon-o-shield-exclamation')->color('warning')
            ->visible(fn (User $record) => $record->two_factor_secret !== null || $record->two_factor_confirmed_at !== null)
            ->modalDescription(__('admin.users.actions.reset_two_factor.description'))
            ->schema([
                Textarea::make('reason')->label(__('admin.common.reason'))->required()->maxLength(255),
            ])
            ->action(function (User $record, array $data) {
                self::service()->resetTwoFactor($record, $data['reason'], self::admin());
                Notification::make()->success()->title(__('admin.users.actions.reset_two_factor.done'))->send();
            });
    }

    public static function bulkPromote(): BulkAction
    {
        return BulkAction::make('bulkPromote')->label(__('admin.users.actions.bulk_promote.label'))->icon('heroicon-o-star')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) {
                $n = self::service()->bulkPromote($records, self::admin());
                Notification::make()->success()->title(__('admin.users.actions.bulk_promote.done', ['count' => $n]))->send();
            });
    }
}
