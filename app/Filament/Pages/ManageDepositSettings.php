<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuditsSettingsChanges;
use App\Services\AdminNotifier;
use App\Settings\DepositSettings;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Đổi thông tin ngân hàng = đổi nơi tiền khách chảy về → bắt nhập lại mật khẩu, báo Telegram, ghi audit.
 */
class ManageDepositSettings extends SettingsPage
{
    use AuditsSettingsChanges;

    private const BANK_FIELDS = ['bank_bin', 'bank_name', 'account_number', 'account_name'];

    protected static string $settings = DepositSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.deposit.title');
    }

    public function getTitle(): string
    {
        return __('admin.settings.deposit.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.settings.deposit.account_section'))
                ->description(__('admin.settings.deposit.account_description'))
                ->columns(2)->columnSpanFull()
                ->schema([
                    TextInput::make('bank_bin')->label(__('admin.settings.deposit.bank_bin'))->numeric()->length(6)->placeholder('970422')
                        ->helperText(__('admin.settings.deposit.bank_bin_hint')),
                    TextInput::make('bank_name')->label(__('admin.settings.deposit.bank_name'))->maxLength(100)->placeholder('MB Bank'),
                    TextInput::make('account_number')->label(__('admin.settings.deposit.account_number'))->maxLength(30)->regex('/^[0-9A-Za-z]+$/'),
                    TextInput::make('account_name')->label(__('admin.settings.deposit.account_name'))->maxLength(100)
                        ->dehydrateStateUsing(fn (?string $state) => $state ? Str::upper(Str::ascii(trim($state))) : null)
                        ->helperText(__('admin.settings.deposit.account_name_hint')),
                ]),
            Section::make(__('admin.settings.deposit.bonus_section'))
                ->description(__('admin.settings.deposit.bonus_description'))
                ->columnSpanFull()
                ->schema([
                    Repeater::make('bonus_rules')->label(__('admin.settings.deposit.bonus_rules'))->defaultItems(0)->columns(2)
                        ->schema([
                            TextInput::make('min_amount')->label(__('admin.settings.deposit.min_amount'))->integer()->required()->minValue(1)->suffix('đ'),
                            TextInput::make('percent')->label(__('admin.settings.deposit.percent'))->integer()->required()->minValue(0)->maxValue(100)->suffix('%'),
                        ]),
                ]),
            Section::make(__('admin.settings.deposit.confirm_section'))->columnSpanFull()->schema([
                TextInput::make('current_password')->label(__('admin.settings.deposit.current_password'))->password()->dehydrated(false)
                    ->helperText(__('admin.settings.deposit.current_password_hint')),
            ]),
        ]);
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $current = app(DepositSettings::class);
        $changed = collect(self::BANK_FIELDS)->contains(fn ($f) => (string) ($data[$f] ?? '') !== (string) ($current->{$f} ?? ''));

        if ($changed && ! Hash::check((string) ($this->data['current_password'] ?? ''), (string) auth()->user()->password)) {
            throw ValidationException::withMessages(['data.current_password' => __('admin.settings.deposit.password_wrong')]);
        }

        $data['bonus_rules'] = collect($data['bonus_rules'] ?? [])
            ->map(fn ($r) => ['min_amount' => (int) $r['min_amount'], 'percent' => (int) $r['percent']])
            ->sortBy('min_amount')->values()->all();

        return $data;
    }

    protected function afterSettingsAudited(array $before, array $changed): void
    {
        $this->data['current_password'] = null;
        $bankChanged = array_intersect_key($changed, array_flip(self::BANK_FIELDS));

        if ($bankChanged !== []) {
            $lines = collect($bankChanged)->map(fn ($new, $k) => "{$k}: ".($before[$k] ?? '—')." → {$new}")->implode("\n");
            // Telegram luôn theo ngôn ngữ mặc định của site, không theo locale của admin đang thao tác.
            app(AdminNotifier::class)->send(__('telegram.bank_account_changed', [
                'email' => auth()->user()->email,
                'lines' => $lines,
            ], config('app.default_locale')));
        }
    }
}
