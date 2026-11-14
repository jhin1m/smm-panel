<?php

use App\Filament\Pages\ManageTelegramSettings;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Settings\TelegramSettings;
use Livewire\Livewire;

it('lưu cài đặt Telegram ghi audit, che token', function () {
    $this->actingAs(User::factory()->admin()->create());
    filament()->setCurrentPanel('admin');

    Livewire::test(ManageTelegramSettings::class)
        ->fillForm(['enabled' => true, 'bot_token' => '111:SECRET', 'chat_id' => '-100123'])
        ->call('save')
        ->assertHasNoFormErrors();

    $log = AdminAuditLog::sole();
    expect($log->action)->toBe('settings.telegram')
        ->and(json_encode($log->changes))->not->toContain('SECRET')
        ->and($log->changes['after']['chat_id'])->toBe('-100123');
});

it('token Telegram đã lưu không gửi xuống form; để trống khi lưu = giữ token cũ', function () {
    $this->actingAs(User::factory()->admin()->create());
    filament()->setCurrentPanel('admin');
    $s = app(TelegramSettings::class);
    $s->bot_token = '111:KEEP';
    $s->chat_id = '-1';
    $s->enabled = true;
    $s->save();

    Livewire::test(ManageTelegramSettings::class)
        ->assertFormSet(['bot_token' => null])
        ->assertDontSee('111:KEEP')
        ->fillForm(['chat_id' => '-2'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(TelegramSettings::class)->bot_token)->toBe('111:KEEP');
});
