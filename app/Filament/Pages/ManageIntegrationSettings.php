<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuditsSettingsChanges;
use App\Services\SepayApiClient;
use App\Settings\IntegrationSettings;
use App\Support\AuditLog;
use App\Support\RuntimeConfig;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

class ManageIntegrationSettings extends SettingsPage
{
    use AuditsSettingsChanges;

    protected static string $settings = IntegrationSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.integrations.nav');
    }

    public function getTitle(): string
    {
        return __('admin.settings.integrations.title');
    }

    /** @return array<int, string> */
    protected function secretSettingKeys(): array
    {
        return IntegrationSettings::encrypted();
    }

    /** Không gửi bí mật đã lưu xuống trình duyệt. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach ($this->secretSettingKeys() as $key) {
            $data[$key] = null;
        }

        return $data;
    }

    /** Ô bí mật để trống = giữ giá trị cũ. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $current = app(IntegrationSettings::class);

        foreach ($this->secretSettingKeys() as $key) {
            if (blank($data[$key] ?? null)) {
                $data[$key] = $current->{$key};
            }
        }

        return $data;
    }

    /** Worker queue giữ config cũ trong bộ nhớ → yêu cầu khởi động lại sau khi đổi tích hợp. */
    protected function afterSettingsAudited(array $before, array $changed): void
    {
        if ($changed !== []) {
            Artisan::call('queue:restart');
        }
    }

    /** Ô bí mật + nút xoá giá trị DB (quay về .env). */
    private function secretInput(string $key): TextInput
    {
        return TextInput::make($key)
            ->label(__("admin.settings.integrations.{$key}"))
            ->password()
            ->revealable()
            ->autocomplete('new-password')
            ->maxLength(500)
            ->placeholder(fn () => filled(app(IntegrationSettings::class)->{$key}) ? __('admin.settings.integrations.secret_saved') : null)
            ->suffixAction(
                Action::make("clear_{$key}")
                    ->icon(Heroicon::OutlinedTrash)
                    ->tooltip(__('admin.settings.integrations.clear_to_env'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.settings.integrations.clear_confirm'))
                    ->visible(fn () => filled(app(IntegrationSettings::class)->{$key}))
                    ->action(fn () => $this->clearSecret($key)),
            );
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.settings.integrations.smtp_section'))
                ->description(__('admin.settings.integrations.env_fallback'))
                ->columns(2)
                ->schema([
                    TextInput::make('smtp_host')->label(__('admin.settings.integrations.smtp_host'))->maxLength(255),
                    TextInput::make('smtp_port')->label(__('admin.settings.integrations.smtp_port'))->numeric()->minValue(1)->maxValue(65535),
                    TextInput::make('smtp_username')->label(__('admin.settings.integrations.smtp_username'))->maxLength(255)->autocomplete('off'),
                    $this->secretInput('smtp_password'),
                    Select::make('smtp_encryption')->label(__('admin.settings.integrations.smtp_encryption'))
                        ->options([
                            'tls' => __('admin.settings.integrations.encryption_tls'),
                            'ssl' => __('admin.settings.integrations.encryption_ssl'),
                            'none' => __('admin.settings.integrations.encryption_none'),
                        ])->native(false),
                    TextInput::make('mail_from_address')->label(__('admin.settings.integrations.mail_from_address'))->email()->maxLength(255),
                ]),
            Section::make(__('admin.settings.integrations.google_section'))
                ->description(__('admin.settings.integrations.google_description', ['url' => url('/auth/google/callback')]))
                ->columns(2)
                ->schema([
                    TextInput::make('google_client_id')->label(__('admin.settings.integrations.google_client_id'))->maxLength(255),
                    $this->secretInput('google_client_secret'),
                ]),
            Section::make(__('admin.settings.integrations.sepay_section'))
                ->description(__('admin.settings.integrations.sepay_description', ['url' => url('/webhooks/sepay')]))
                ->columns(2)
                ->schema([
                    $this->secretInput('sepay_webhook_secret'),
                    $this->secretInput('sepay_api_token'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestMail')
                ->label(__('admin.settings.integrations.send_test_mail'))
                ->icon(Heroicon::OutlinedEnvelope)
                ->action(fn () => $this->sendTestMail()),
            Action::make('checkSepay')
                ->label(__('admin.settings.integrations.check_sepay'))
                ->icon(Heroicon::OutlinedBolt)
                ->action(fn () => $this->checkSepay()),
        ];
    }

    /** Xoá 1 giá trị bí mật trong DB → từ request kế tiếp dùng lại giá trị .env. */
    public function clearSecret(string $key): void
    {
        abort_unless(in_array($key, $this->secretSettingKeys(), true), 404);

        $settings = app(IntegrationSettings::class);

        if (blank($settings->{$key})) {
            return;
        }

        $settings->{$key} = null;
        $settings->save();

        AuditLog::record(auth()->user(), 'settings.integrations', null, [
            'before' => [$key => '***'],
            'after' => [$key => '(đã xoá, dùng .env)'],
        ]);
        Artisan::call('queue:restart');

        Notification::make()->success()->title(__('admin.settings.integrations.cleared'))->send();
    }

    /** Gửi mail thử tới email admin hiện tại bằng cấu hình ĐÃ LƯU (kể cả đè từ DB). */
    public function sendTestMail(): void
    {
        $to = auth()->user()?->email;

        try {
            RuntimeConfig::apply();
            Mail::purge();   // bỏ mailer đã resolve bằng config cũ
            Mail::raw(__('admin.settings.integrations.test_mail_body', ['app' => config('app.name')]), function ($message) use ($to) {
                $message->to($to)->subject(__('admin.settings.integrations.test_mail_subject', ['app' => config('app.name')]));
            });
        } catch (Throwable $e) {
            Notification::make()->danger()->title(__('admin.settings.integrations.test_mail_failed'))->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title(__('admin.settings.integrations.test_mail_sent', ['email' => $to]))->send();
    }

    /** Gọi User API SePay cho 1 ngày gần nhất để xác nhận token hoạt động. */
    public function checkSepay(): void
    {
        RuntimeConfig::apply();
        $api = app(SepayApiClient::class);

        if (! $api->isConfigured()) {
            Notification::make()->danger()->title(__('admin.settings.integrations.sepay_no_token'))->send();

            return;
        }

        try {
            $count = count($api->transactions(now()->subDay(), now()));
        } catch (Throwable $e) {
            Notification::make()->danger()->title(__('admin.settings.integrations.sepay_failed'))->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title(__('admin.settings.integrations.sepay_ok', ['count' => $count]))->send();
    }
}
