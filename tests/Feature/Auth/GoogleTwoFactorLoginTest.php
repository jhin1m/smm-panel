<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function stubGoogle(string $email, string $id = 'g-2fa'): void
{
    $g = (new SocialiteUser)->setRaw(['email_verified' => true])->map(['id' => $id, 'name' => 'G User', 'email' => $email]);
    Socialite::shouldReceive('driver->user')->andReturn($g);
}

it('TK Google đã bật 2FA → sang challenge, chưa đăng nhập; mã đúng mới vào', function () {
    $u = User::factory()->create(['email' => 'g@example.com']);
    $u->forceFill(['google_id' => 'g-2fa', 'password' => null])->save();
    $secret = enableTwoFactor($u);
    stubGoogle('g@example.com');

    $this->get('/auth/google/callback')->assertRedirect('/two-factor-challenge');
    $this->assertGuest();

    $this->post('/two-factor-challenge', ['code' => totpCode($secret)])->assertRedirect('/');
    $this->assertAuthenticatedAs($u);
});

it('Google + 2FA giữ hành vi remember như trước (login.remember = true)', function () {
    $u = User::factory()->create(['email' => 'g@example.com']);
    $u->forceFill(['google_id' => 'g-2fa'])->save();
    $secret = enableTwoFactor($u);
    stubGoogle('g@example.com');

    $this->get('/auth/google/callback');
    expect(session('login.remember'))->toBeTrue()->and(session('login.id'))->toBe($u->id);

    $response = $this->post('/two-factor-challenge', ['code' => totpCode($secret)]);
    expect(collect($response->headers->getCookies())->contains(fn ($c) => str_starts_with($c->getName(), 'remember_web_')))->toBeTrue();
});

it('Google + 2FA: recovery code vào được', function () {
    $u = User::factory()->create(['email' => 'g@example.com']);
    $u->forceFill(['google_id' => 'g-2fa'])->save();
    enableTwoFactor($u);
    stubGoogle('g@example.com');

    $this->get('/auth/google/callback')->assertRedirect('/two-factor-challenge');
    $this->post('/two-factor-challenge', ['recovery_code' => 'rc2-aaaaaaaaaa'])->assertRedirect('/');

    $this->assertAuthenticatedAs($u);
});

it('TK chưa bật 2FA → vào thẳng như cũ', function () {
    $u = User::factory()->create(['email' => 'g@example.com']);
    $u->forceFill(['google_id' => 'g-2fa'])->save();
    stubGoogle('g@example.com');

    $this->get('/auth/google/callback')->assertRedirect('/');

    $this->assertAuthenticatedAs($u);
});

it('TK mới chưa xác nhận 2FA (chỉ có secret) → vào thẳng', function () {
    $u = User::factory()->create(['email' => 'g@example.com']);
    $u->forceFill(['google_id' => 'g-2fa'])->save();
    enableTwoFactor($u);
    $u->forceFill(['two_factor_confirmed_at' => null])->save();
    stubGoogle('g@example.com');

    $this->get('/auth/google/callback')->assertRedirect('/');
    $this->assertAuthenticatedAs($u);
});

it('admin vẫn bị từ chối ở Google callback dù có cột 2FA', function () {
    $admin = User::factory()->admin()->create(['email' => 'a@example.com']);
    $admin->forceFill(['google_id' => 'g-2fa'])->save();
    enableTwoFactor($admin);
    stubGoogle('a@example.com');

    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(session('login.id'))->toBeNull();
});

it('TK khoá bị từ chối trước khi tới challenge', function () {
    $u = User::factory()->locked()->create(['email' => 'l@example.com']);
    $u->forceFill(['google_id' => 'g-2fa'])->save();
    enableTwoFactor($u);
    stubGoogle('l@example.com');

    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');

    expect(session('login.id'))->toBeNull();
});

it('TK đăng ký mật khẩu chưa xác minh + đã bật 2FA → chủ email login Google: xoá 2FA và vào được', function () {
    $victim = User::factory()->unverified()->create(['email' => 'victim@example.com']);
    enableTwoFactor($victim);
    DB::table('sessions')->insert(['id' => 'attacker', 'user_id' => $victim->id, 'payload' => '', 'last_activity' => time()]);
    stubGoogle('victim@example.com');

    $this->get('/auth/google/callback')->assertRedirect('/');

    $victim->refresh();
    expect($victim->two_factor_secret)->toBeNull()
        ->and($victim->two_factor_recovery_codes)->toBeNull()
        ->and($victim->two_factor_confirmed_at)->toBeNull()
        ->and($victim->password)->toBeNull()
        ->and(DB::table('sessions')->where('id', 'attacker')->exists())->toBeFalse();
    $this->assertAuthenticatedAs($victim);
});

it('TK đã xác minh email ghép Google giữ nguyên 2FA và vẫn bị hỏi mã', function () {
    $u = User::factory()->create(['email' => 'v@example.com']);
    enableTwoFactor($u);
    stubGoogle('v@example.com');

    $this->get('/auth/google/callback')->assertRedirect('/two-factor-challenge');

    expect($u->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
    $this->assertGuest();
});
