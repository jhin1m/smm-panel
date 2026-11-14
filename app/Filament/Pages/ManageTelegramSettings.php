<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuditsSettingsChanges;
use App\Jobs\SendTelegramMessage;
use App\Settings\TelegramSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use RuntimeException;
use UnitEnum;

class ManageTelegramSettings extends SettingsPage
{
    use AuditsSettingsChanges;

    protected static string $settings = TelegramSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?int $navigationSort = 90;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.telegram.nav');
    }

    public function getTitle(): string
    {
        return __('admin.settings.telegram.title');
    }

    protected function secretSettingKeys(): array
    {
        return ['bot_token'];
    }

    /** Không gửi token đã lưu xuống trình duyệt. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['bot_token'] = null;

        return $data;
    }

    /** Ô token để trống = giữ token cũ. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['bot_token'] ?? null)) {
            $data['bot_token'] = app(TelegramSettings::class)->bot_token;
        }

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.settings.telegram.bot_section'))
                ->description(__('admin.settings.telegram.bot_description'))
                ->columns(2)
                ->schema([
                    Toggle::make('enabled')->label(__('admin.settings.telegram.enabled'))->columnSpanFull(),
                    TextInput::make('bot_token')
                        ->label(__('admin.settings.telegram.bot_token'))
                        ->password()
                        ->maxLength(100)
                        ->placeholder(fn () => filled(app(TelegramSettings::class)->bot_token) ? __('admin.settings.telegram.bot_token_saved') : null)
                        ->helperText(__('admin.settings.telegram.bot_token_hint')),
                    TextInput::make('chat_id')
                        ->label(__('admin.settings.telegram.chat_id'))
                        ->maxLength(50)
                        ->helperText(__('admin.settings.telegram.chat_id_hint')),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label(__('admin.settings.telegram.send_test'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->action(fn () => $this->sendTest()),
        ];
    }

    /** Gửi đồng bộ bằng cấu hình ĐÃ LƯU và báo kết quả ngay. */
    public function sendTest(): void
    {
        $s = app(TelegramSettings::class);

        if (blank($s->bot_token) || blank($s->chat_id)) {
            Notification::make()->danger()->title(__('admin.settings.telegram.not_saved'))->send();

            return;
        }

        try {
            $res = SendTelegramMessage::post((string) $s->bot_token, (string) $s->chat_id, __('telegram.test_message', ['app' => e(config('app.name'))], config('app.default_locale')));
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title(__('admin.settings.telegram.connect_failed'))->body($e->getMessage())->send();

            return;
        }

        if ($res->successful()) {
            Notification::make()->success()->title(__('admin.settings.telegram.test_sent'))->send();

            return;
        }

        Notification::make()->danger()
            ->title(__('admin.settings.telegram.rejected', ['status' => $res->status()]))
            ->body((string) $res->json('description'))
            ->send();
    }
}
