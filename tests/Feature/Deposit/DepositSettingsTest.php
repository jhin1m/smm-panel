<?php

use App\Filament\Pages\ManageDepositSettings;
use App\Jobs\SendTelegramMessage;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Settings\DepositSettings;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    configureDeposit();
    $this->admin = User::factory()->admin()->create(['password' => 'admin-pass-123']);
    $this->actingAs($this->admin);
    filament()->setCurrentPanel('admin');
});

it('đổi STK thiếu/sai mật khẩu → lỗi, không lưu', function (?string $pw) {
    Livewire::test(ManageDepositSettings::class)
        ->fillForm(['account_number' => '999888777', 'current_password' => $pw])
        ->call('save')
        ->assertHasFormErrors(['current_password']);

    expect(app(DepositSettings::class)->account_number)->toBe('0123456789');
})->with([null, 'sai-mat-khau']);

it('đổi STK đúng mật khẩu → lưu + 1 job Telegram + audit', function () {
    Queue::fake();

    Livewire::test(ManageDepositSettings::class)
        ->fillForm(['account_number' => '999888777', 'account_name' => 'tran thi b', 'current_password' => 'admin-pass-123'])
        ->call('save')
        ->assertHasNoFormErrors();

    $s = app(DepositSettings::class);
    expect($s->account_number)->toBe('999888777')->and($s->account_name)->toBe('TRAN THI B');
    Queue::assertPushed(SendTelegramMessage::class, 1);
    expect(AdminAuditLog::firstWhere('action', 'settings.deposit')->changes['before']['account_number'])->toBe('0123456789');
});

it('chỉ đổi khuyến mãi không cần mật khẩu', function () {
    Livewire::test(ManageDepositSettings::class)
        ->fillForm(['bonus_rules' => [['min_amount' => 500000, 'percent' => 5]]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(DepositSettings::class)->bonus_rules)->toBe([['min_amount' => 500000, 'percent' => 5]]);
});
