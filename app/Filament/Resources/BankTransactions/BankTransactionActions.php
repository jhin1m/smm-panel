<?php

namespace App\Filament\Resources\BankTransactions;

use App\Exceptions\ConfirmationRequired;
use App\Exceptions\DepositAlreadyProcessed;
use App\Exceptions\InsufficientBalance;
use App\Models\BankTransaction;
use App\Models\User;
use App\Services\DepositService;
use App\Services\SepayApiClient;
use App\Services\SepayReconciler;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use InvalidArgumentException;
use Throwable;

final class BankTransactionActions
{
    /** @return array<Action> */
    public static function record(): array
    {
        return [self::assign(), self::unassign(), self::ignore()];
    }

    /** @return array<Action> */
    public static function header(): array
    {
        return [self::reconcile(), self::manualImport()];
    }

    private static function deposits(): DepositService
    {
        return app(DepositService::class);
    }

    private static function run(Action $action, Closure $fn, string $ok): void
    {
        try {
            $fn();
        } catch (DepositAlreadyProcessed|ConfirmationRequired|InsufficientBalance|InvalidArgumentException $e) {
            $msg = $e instanceof InsufficientBalance ? __('admin.bank_transactions.actions.unassign.insufficient_balance') : $e->getMessage();
            Notification::make()->danger()->title($msg)->send();
            $action->halt();
        }
        Notification::make()->success()->title($ok)->send();
    }

    public static function assign(): Action
    {
        return Action::make('assign')->label(__('admin.bank_transactions.actions.assign.label'))->icon('heroicon-o-user-plus')->color('success')
            ->visible(fn (BankTransaction $record) => $record->transfer_type === 'in' && in_array($record->status, ['unmatched', 'ignored'], true))
            ->schema(fn (BankTransaction $record) => [
                Text::make(__('admin.bank_transactions.actions.assign.summary', [
                    'amount' => Money::vnd($record->transfer_amount),
                    'bonus' => Money::vnd(intdiv($record->transfer_amount * $record->bonus_percent, 100)),
                    'percent' => $record->bonus_percent,
                ])),
                Select::make('user_id')->label(__('admin.common.customer'))->required()->searchable()
                    ->getSearchResultsUsing(fn (string $search) => User::query()->where('is_admin', false)
                        ->where(fn ($q) => $q->where('email', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%")
                            ->orWhere('deposit_code', strtoupper(trim($search))))
                        ->limit(20)->get()->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} · {$u->email} · {$u->deposit_code}"])->all())
                    ->getOptionLabelUsing(fn ($value) => ($u = User::find($value)) ? "{$u->name} · {$u->email}" : null),
                Checkbox::make('confirm_ignored')->label(__('admin.bank_transactions.actions.assign.confirm_ignored'))
                    ->visible($record->status === 'ignored')->accepted($record->status === 'ignored'),
            ])
            ->action(fn (BankTransaction $record, array $data, Action $action) => self::run($action, function () use ($record, $data) {
                $user = User::where('is_admin', false)->findOrFail($data['user_id']);
                self::deposits()->assign($record->id, $user, auth()->user(), (bool) ($data['confirm_ignored'] ?? false));
            }, __('admin.bank_transactions.actions.assign.done')));
    }

    public static function unassign(): Action
    {
        return Action::make('unassign')->label(__('admin.bank_transactions.actions.unassign.label'))->icon('heroicon-o-arrow-uturn-left')->color('danger')
            ->visible(fn (BankTransaction $record) => $record->status === 'credited')
            ->modalDescription(fn (BankTransaction $record) => $record->bonus_amount
                ? __('admin.bank_transactions.actions.unassign.description_with_bonus', ['amount' => Money::vnd($record->transfer_amount), 'bonus' => Money::vnd($record->bonus_amount)])
                : __('admin.bank_transactions.actions.unassign.description', ['amount' => Money::vnd($record->transfer_amount)]))
            ->schema([Textarea::make('reason')->label(__('admin.common.reason'))->required()->maxLength(255)])
            ->action(fn (BankTransaction $record, array $data, Action $action) => self::run($action,
                fn () => self::deposits()->unassign($record->id, auth()->user(), $data['reason']), __('admin.bank_transactions.actions.unassign.done')));
    }

    public static function ignore(): Action
    {
        return Action::make('ignore')->label(__('admin.bank_transactions.actions.ignore.label'))->icon('heroicon-o-eye-slash')->color('gray')
            ->visible(fn (BankTransaction $record) => $record->status === 'unmatched')
            ->schema([Textarea::make('reason')->label(__('admin.common.reason'))->required()->maxLength(255)])
            ->action(fn (BankTransaction $record, array $data, Action $action) => self::run($action,
                fn () => self::deposits()->ignore($record->id, auth()->user(), $data['reason']), __('admin.bank_transactions.actions.ignore.done')));
    }

    public static function reconcile(): Action
    {
        return Action::make('reconcile')->label(__('admin.bank_transactions.actions.reconcile.label'))->icon('heroicon-o-arrow-path')
            ->visible(fn () => app(SepayApiClient::class)->isConfigured())
            ->modalDescription(__('admin.bank_transactions.actions.reconcile.description'))
            ->fillForm(fn () => ['from' => now()->subDays(3)->toDateString(), 'to' => now()->toDateString()])
            ->schema([
                DatePicker::make('from')->label(__('admin.common.date_from'))->required()->live(),
                DatePicker::make('to')->label(__('admin.common.date_until'))->required()->afterOrEqual('from')
                    ->rule(fn (Get $get) => function (string $attr, $value, Closure $fail) use ($get) {
                        if ($get('from') && CarbonImmutable::parse($get('from'))->diffInDays(CarbonImmutable::parse($value)) > 31) {
                            $fail(__('admin.bank_transactions.actions.reconcile.range_max'));
                        }
                    }),
            ])
            ->action(function (array $data, Action $action) {
                try {
                    $c = app(SepayReconciler::class)->run(
                        CarbonImmutable::parse($data['from'])->startOfDay(),
                        CarbonImmutable::parse($data['to'])->endOfDay(),
                    );
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title(__('admin.bank_transactions.actions.reconcile.failed'))->body($e->getMessage())->send();
                    $action->halt();
                }
                Notification::make()->success()->title(__('admin.bank_transactions.actions.reconcile.done'))
                    ->body(__('admin.bank_transactions.actions.reconcile.summary', $c))
                    ->send();
            });
    }

    public static function manualImport(): Action
    {
        return Action::make('manualImport')->label(__('admin.bank_transactions.actions.manual_import.label'))->icon('heroicon-o-pencil-square')->color('gray')
            ->modalDescription(__('admin.bank_transactions.actions.manual_import.description'))
            ->schema([
                TextInput::make('sepay_id')->label(__('admin.bank_transactions.actions.manual_import.sepay_id'))->integer()->required()->minValue(1),
                TextInput::make('amount')->label(__('admin.common.amount'))->integer()->required()->minValue(1)->suffix('đ'),
                Textarea::make('content')->label(__('admin.bank_transactions.actions.manual_import.content'))->required()->maxLength(500),
                TextInput::make('reference_code')->label(__('admin.bank_transactions.actions.manual_import.reference_code'))->required()->maxLength(100)
                    ->helperText(__('admin.bank_transactions.actions.manual_import.reference_hint')),
                DateTimePicker::make('transaction_date')->label(__('admin.bank_transactions.actions.manual_import.tx_time'))->required()->seconds(false),
            ])
            ->action(function (array $data) {
                $result = self::deposits()->importManual($data, auth()->user());
                $label = match ($result) {
                    DepositService::CREDITED => __('admin.bank_transactions.actions.manual_import.results.credited'),
                    DepositService::UNMATCHED => __('admin.bank_transactions.actions.manual_import.results.unmatched'),
                    DepositService::DUPLICATE => __('admin.bank_transactions.actions.manual_import.results.duplicate'),
                    default => __('admin.bank_transactions.actions.manual_import.results.other', ['result' => $result]),
                };
                Notification::make()->{$result === DepositService::DUPLICATE ? 'warning' : 'success'}()->title($label)->send();
            });
    }
}
