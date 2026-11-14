<?php

use App\Livewire\AccountSecurity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('khách mở được /bao-mat; admin bị chặn', function () {
    $this->actingAs(User::factory()->create())->get('/bao-mat')->assertOk()->assertSee(__('security.enable'));
    $this->actingAs(User::factory()->admin()->create())->get('/bao-mat')->assertForbidden();
});

it('bật xong chưa xác nhận → login mật khẩu vẫn vào thẳng, không hỏi mã', function () {
    $u = User::factory()->create(['email' => 'u@example.com']);

    Livewire::actingAs($u)->test(AccountSecurity::class)->call('enable');
    expect($u->fresh()->two_factor_secret)->not->toBeNull()->and($u->fresh()->two_factor_confirmed_at)->toBeNull();

    auth()->logout();
    $this->post('/login', ['email' => 'u@example.com', 'password' => 'password'])->assertRedirect('/');
    $this->assertAuthenticatedAs($u);
});

it('trang không giữ secret giải mã trong state; QR + secret render từ server', function () {
    $u = User::factory()->create();

    $c = Livewire::actingAs($u)->test(AccountSecurity::class)->call('enable');
    $secret = twoFactorSecretOf($u);

    $c->assertSeeHtml('<svg')->assertSee($secret);
    expect(json_encode($c->snapshot))->not->toContain($secret);
});

it('xác nhận mã sai bị từ chối; mã đúng → bật + hiện 8 recovery code', function () {
    $u = User::factory()->create();
    $c = Livewire::actingAs($u)->test(AccountSecurity::class)->call('enable');

    $c->set('code', '000000')->call('confirm')->assertHasErrors('code');
    expect($u->fresh()->two_factor_confirmed_at)->toBeNull();

    $c->set('code', totpCode(twoFactorSecretOf($u)))->call('confirm')->assertHasNoErrors();
    $u->refresh();
    expect($u->hasEnabledTwoFactorAuthentication())->toBeTrue()->and($u->recoveryCodes())->toHaveCount(8);
    foreach ($u->recoveryCodes() as $rc) {
        $c->assertSee($rc);
    }
});

it('đang chờ xác nhận: enable lại đổi secret (tạo lại QR)', function () {
    $u = User::factory()->create();
    $c = Livewire::actingAs($u)->test(AccountSecurity::class)->call('enable');
    $first = twoFactorSecretOf($u);

    $c->call('enable');

    expect(twoFactorSecretOf($u))->not->toBe($first);
});

it('đã bật + xác nhận: enable lại bị từ chối, secret không đổi', function () {
    $u = User::factory()->create();
    $secret = enableTwoFactor($u);

    Livewire::actingAs($u)->test(AccountSecurity::class)->call('enable')->assertForbidden();

    expect(twoFactorSecretOf($u))->toBe($secret);
});

it('công tắc tắt: ẩn nút bật, gọi thẳng enable bị từ chối', function () {
    setCustomerTwoFactorSwitch(false);
    $u = User::factory()->create();

    $c = Livewire::actingAs($u)->test(AccountSecurity::class)
        ->assertDontSee(__('security.enable'))
        ->assertSee(__('security.feature_off'));
    $c->call('enable')->assertForbidden();

    expect($u->fresh()->two_factor_secret)->toBeNull();
});

it('công tắc tắt: khách đã bật vẫn tắt và tạo lại recovery code được', function () {
    setCustomerTwoFactorSwitch(false);
    $u = User::factory()->create();
    $secret = enableTwoFactor($u);

    $c = Livewire::actingAs($u)->test(AccountSecurity::class)->assertSee(__('security.manage.disable'));
    $c->set('code', totpCode($secret))->call('regenerateRecoveryCodes')->assertHasNoErrors();
    $c->set('code', totpCode($secret, 1))->call('disable')->assertHasNoErrors();

    expect($u->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('tắt 2FA cần mã: không mã / sai mã thì giữ nguyên; TOTP đúng thì tắt', function () {
    $u = User::factory()->create();
    $secret = enableTwoFactor($u);
    $c = Livewire::actingAs($u)->test(AccountSecurity::class);

    $c->set('code', '')->call('disable')->assertHasErrors('code');
    $c->set('code', '111111')->call('disable')->assertHasErrors('code');
    expect($u->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

    $c->set('code', totpCode($secret))->call('disable')->assertHasNoErrors();
    $u->refresh();
    expect($u->two_factor_secret)->toBeNull()->and($u->two_factor_recovery_codes)->toBeNull()->and($u->two_factor_confirmed_at)->toBeNull();
});

it('tắt bằng recovery code (TK Google không mật khẩu) và recovery code bị tiêu thụ', function () {
    $u = User::factory()->create();
    $u->forceFill(['password' => null, 'google_id' => 'g-1'])->save();
    enableTwoFactor($u);

    $c = Livewire::actingAs($u)->test(AccountSecurity::class);
    $c->set('code', 'rc1-aaaaaaaaaa')->call('regenerateRecoveryCodes')->assertHasNoErrors();
    expect($u->fresh()->recoveryCodes())->not->toContain('rc1-aaaaaaaaaa');

    $new = $u->fresh()->recoveryCodes()[0];
    $c->set('code', $new)->call('disable')->assertHasNoErrors();
    expect($u->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('tạo lại recovery code cần mã; mã cũ hết hiệu lực', function () {
    $u = User::factory()->create();
    $secret = enableTwoFactor($u);
    $old = $u->fresh()->recoveryCodes();
    $c = Livewire::actingAs($u)->test(AccountSecurity::class);

    $c->set('code', '999999')->call('regenerateRecoveryCodes')->assertHasErrors('code');
    expect($u->fresh()->recoveryCodes())->toBe($old);

    $c->set('code', totpCode($secret))->call('regenerateRecoveryCodes')->assertHasNoErrors();
    expect($u->fresh()->recoveryCodes())->toHaveCount(8)->not->toBe($old);
});

it('nhập sai quá nhiều lần → khoá thử, mã đúng cũng bị từ chối', function () {
    $u = User::factory()->create();
    $secret = enableTwoFactor($u);
    $c = Livewire::actingAs($u)->test(AccountSecurity::class);

    foreach (range(1, 5) as $i) {
        $c->set('code', sprintf('%06d', $i))->call('disable')->assertHasErrors('code');
    }
    $c->set('code', totpCode($secret))->call('disable')->assertHasErrors('code');

    expect($u->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('xác nhận bật: huỷ phiên khác + remember token cũ, giữ phiên hiện tại', function () {
    $u = User::factory()->create();
    $oldRemember = $u->remember_token;
    DB::table('sessions')->insert(['id' => 'other', 'user_id' => $u->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('sessions')->insert(['id' => 'someone-else', 'user_id' => User::factory()->create()->id, 'payload' => '', 'last_activity' => time()]);

    $c = Livewire::actingAs($u)->test(AccountSecurity::class)->call('enable');
    $c->set('code', totpCode(twoFactorSecretOf($u)))->call('confirm');

    expect($u->fresh()->remember_token)->not->toBe($oldRemember)
        ->and(DB::table('sessions')->where('id', 'other')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'someone-else')->exists())->toBeTrue();
});

it('tắt 2FA: huỷ phiên khác + remember token cũ', function () {
    $u = User::factory()->create();
    $secret = enableTwoFactor($u);
    $oldRemember = $u->fresh()->remember_token;
    DB::table('sessions')->insert(['id' => 'other', 'user_id' => $u->id, 'payload' => '', 'last_activity' => time()]);

    Livewire::actingAs($u)->test(AccountSecurity::class)->set('code', totpCode($secret))->call('disable');

    expect($u->fresh()->remember_token)->not->toBe($oldRemember)
        ->and(DB::table('sessions')->where('id', 'other')->exists())->toBeFalse();
});

it('hủy thiết lập dở được, nhưng không huỷ được khi đã bật', function () {
    $u = User::factory()->create();
    $c = Livewire::actingAs($u)->test(AccountSecurity::class)->call('enable')->call('cancelSetup');
    expect($u->fresh()->two_factor_secret)->toBeNull();

    enableTwoFactor($u);
    Livewire::actingAs($u)->test(AccountSecurity::class)->call('cancelSetup')->assertForbidden();
    expect($u->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('secret/recovery code bị ẩn khi serialize user', function () {
    $u = User::factory()->create();
    enableTwoFactor($u);

    expect($u->fresh()->toArray())->not->toHaveKeys(['two_factor_secret', 'two_factor_recovery_codes']);
});
