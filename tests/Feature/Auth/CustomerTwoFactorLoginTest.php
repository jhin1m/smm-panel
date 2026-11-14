<?php

use App\Livewire\AccountSecurity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

function loginWithPassword(string $email = 'u@example.com'): void
{
    test()->post('/login', ['email' => $email, 'password' => 'password']);
}

it('đã bật 2FA: mật khẩu đúng → sang challenge, chưa đăng nhập', function () {
    $u = User::factory()->create(['email' => 'u@example.com']);
    enableTwoFactor($u);

    $this->post('/login', ['email' => 'u@example.com', 'password' => 'password'])->assertRedirect('/two-factor-challenge');

    $this->assertGuest();
    $this->get('/')->assertRedirect('/login');
    $this->get('/two-factor-challenge')->assertOk()->assertSee(__('security.challenge.title'));
});

it('challenge không có phiên đăng nhập dở → về /login', function () {
    $this->get('/two-factor-challenge')->assertRedirect('/login');
});

it('mã TOTP đúng → đăng nhập', function () {
    $u = User::factory()->create(['email' => 'u@example.com']);
    $secret = enableTwoFactor($u);
    loginWithPassword();

    $this->post('/two-factor-challenge', ['code' => totpCode($secret)])->assertRedirect('/');

    $this->assertAuthenticatedAs($u);
});

it('mã sai → ở lại challenge với lỗi đã dịch, vẫn chưa đăng nhập', function () {
    $u = User::factory()->create(['email' => 'u@example.com']);
    enableTwoFactor($u);
    loginWithPassword();

    $this->post('/two-factor-challenge', ['code' => '123456'])
        ->assertRedirect('/two-factor-challenge')
        ->assertSessionHasErrors(['code' => __('security.errors.invalid_code')]);
    $this->post('/two-factor-challenge', ['recovery_code' => 'nope'])
        ->assertSessionHasErrors(['recovery_code' => __('security.errors.invalid_recovery_code')]);

    $this->assertGuest();
});

it('recovery code dùng được đúng 1 lần', function () {
    $u = User::factory()->create(['email' => 'u@example.com']);
    enableTwoFactor($u);

    loginWithPassword();
    $this->post('/two-factor-challenge', ['recovery_code' => 'rc1-aaaaaaaaaa'])->assertRedirect('/');
    $this->assertAuthenticatedAs($u);

    auth()->logout();
    $this->flushSession();
    loginWithPassword();
    $this->post('/two-factor-challenge', ['recovery_code' => 'rc1-aaaaaaaaaa'])->assertSessionHasErrors('recovery_code');
    $this->assertGuest();
});

it('sai quá 5 lần/phút → 429, mã đúng cũng bị chặn', function () {
    $u = User::factory()->create(['email' => 'u@example.com']);
    $secret = enableTwoFactor($u);
    loginWithPassword();

    foreach (range(1, 5) as $i) {
        $this->post('/two-factor-challenge', ['code' => sprintf('%06d', $i)])->assertSessionHasErrors('code');
    }

    $this->post('/two-factor-challenge', ['code' => '000006'])->assertTooManyRequests();
    $this->post('/two-factor-challenge', ['code' => totpCode($secret)])->assertTooManyRequests();
    $this->assertGuest();
});

it('bộ đếm theo user: mở phiên mới (đăng nhập lại) không reset được', function () {
    $u = User::factory()->create(['email' => 'u@example.com']);
    $secret = enableTwoFactor($u);
    loginWithPassword();
    foreach (range(1, 5) as $i) {
        $this->post('/two-factor-challenge', ['code' => sprintf('%06d', $i)]);
    }

    $this->flushSession();
    loginWithPassword();

    $this->post('/two-factor-challenge', ['code' => totpCode($secret)])->assertTooManyRequests();
});

it('chưa xác nhận (chỉ có secret) → đăng nhập thẳng', function () {
    $u = User::factory()->create(['email' => 'u@example.com']);
    enableTwoFactor($u);
    $u->forceFill(['two_factor_confirmed_at' => null])->save();

    $this->post('/login', ['email' => 'u@example.com', 'password' => 'password'])->assertRedirect('/');
    $this->assertAuthenticatedAs($u);
});

it('admin vẫn bị chặn ở /login kể cả khi có cột 2FA', function () {
    $admin = User::factory()->admin()->create(['email' => 'a@example.com']);
    enableTwoFactor($admin);

    $this->post('/login', ['email' => 'a@example.com', 'password' => 'password'])->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(session('login.id'))->toBeNull();
});

it('sai mật khẩu → không vào challenge', function () {
    $u = User::factory()->create(['email' => 'u@example.com']);
    enableTwoFactor($u);

    $this->post('/login', ['email' => 'u@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');

    expect(session('login.id'))->toBeNull();
    $this->get('/two-factor-challenge')->assertRedirect('/login');
});

it('TK bị khoá giữa chừng ở challenge: vào được 1 request rồi bị đăng xuất ở request kế', function () {
    $u = User::factory()->create(['email' => 'u@example.com']);
    $secret = enableTwoFactor($u);
    loginWithPassword();
    $u->forceFill(['locked_at' => now()])->save();

    $this->post('/two-factor-challenge', ['code' => totpCode($secret)])->assertRedirect('/');

    $this->get('/')->assertRedirect('/login');
    $this->assertGuest();
});

it('remember cookie cấp trước khi bật 2FA không còn dùng được sau khi xác nhận', function () {
    $u = User::factory()->create(['email' => 'u@example.com', 'remember_token' => 'old-token']);
    $guard = Auth::guard();
    $recaller = $guard->getRecallerName();
    // Cookie remember dạng id|token|hash-mật-khẩu (test case tự mã hoá cookie).
    $cookie = $u->id.'|old-token|'.$u->getAuthPassword();

    $this->withCookie($recaller, $cookie)->get('/')->assertOk();

    $c = Livewire::actingAs($u)->test(AccountSecurity::class)->call('enable');
    $c->set('code', totpCode(twoFactorSecretOf($u)))->call('confirm');
    forgetRequestState();
    auth()->logout();

    $this->withCookie($recaller, $cookie)->get('/')->assertRedirect('/login');
});
