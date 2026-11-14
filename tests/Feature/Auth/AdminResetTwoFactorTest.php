<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\UserAdminService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    filament()->setCurrentPanel('admin');
});

it('action Gỡ 2FA bắt buộc lý do', function () {
    $u = User::factory()->create();
    enableTwoFactor($u);

    Livewire::test(ViewUser::class, ['record' => $u->id])
        ->callAction('resetTwoFactor', ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($u->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('admin gỡ 2FA: xoá cột, ghi audit (kèm lý do, không lộ secret), huỷ phiên + remember token cũ', function () {
    $u = User::factory()->create(['email' => 'u@example.com']);
    enableTwoFactor($u);
    $oldRemember = $u->fresh()->remember_token;
    DB::table('sessions')->insert(['id' => 'cust', 'user_id' => $u->id, 'payload' => '', 'last_activity' => time()]);

    Livewire::test(ListUsers::class)->callAction(TestAction::make('resetTwoFactor')->table($u), ['reason' => 'mất điện thoại, đã xác minh qua chat']);

    $u->refresh();
    expect($u->two_factor_secret)->toBeNull()->and($u->two_factor_recovery_codes)->toBeNull()->and($u->two_factor_confirmed_at)->toBeNull()
        ->and($u->remember_token)->not->toBe($oldRemember)
        ->and(DB::table('sessions')->where('user_id', $u->id)->exists())->toBeFalse();

    $log = AdminAuditLog::firstWhere('action', 'user.two_factor_reset');
    expect($log->admin_id)->toBe($this->admin->id)
        ->and($log->subject_id)->toBe($u->id)
        ->and($log->changes['reason'])->toBe('mất điện thoại, đã xác minh qua chat')
        ->and($log->changes['before']['two_factor'])->toBe('enabled')
        ->and(json_encode($log->changes))->not->toContain('JBSWY3DPEHPK3PXP');

    auth()->logout();
    $this->flushSession();
    $this->post('/login', ['email' => 'u@example.com', 'password' => 'password'])->assertRedirect('/');
    $this->assertAuthenticatedAs($u);
});

it('action chỉ hiện khi khách có 2FA (đã bật hoặc đang thiết lập)', function () {
    $plain = User::factory()->create();
    $on = User::factory()->create();
    enableTwoFactor($on);

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('resetTwoFactor')->table($plain))
        ->assertActionVisible(TestAction::make('resetTwoFactor')->table($on));
});

it('audit có nhãn dịch cho user.two_factor_reset', function () {
    expect(__('admin.audit.actions.user.two_factor_reset'))->not->toBe('admin.audit.actions.user.two_factor_reset');
});

it('service ghi trạng thái pending khi mới thiết lập dở', function () {
    $u = User::factory()->create();
    enableTwoFactor($u);
    $u->forceFill(['two_factor_confirmed_at' => null])->save();

    app(UserAdminService::class)->resetTwoFactor($u, 'dọn', $this->admin);

    expect(AdminAuditLog::firstWhere('action', 'user.two_factor_reset')->changes['before']['two_factor'])->toBe('pending')
        ->and($u->fresh()->two_factor_secret)->toBeNull();
});
