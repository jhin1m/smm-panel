<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('gửi email reset; email không tồn tại trả cùng thông báo', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'u@example.com']);

    $a = $this->post('/forgot-password', ['email' => 'U@example.com']);
    $b = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertCount(1);
    $a->assertSessionHas('status', 'Nếu email tồn tại, chúng tôi đã gửi liên kết đặt lại mật khẩu.');
    $b->assertSessionHas('status', 'Nếu email tồn tại, chúng tôi đã gửi liên kết đặt lại mật khẩu.')->assertSessionHasNoErrors();
});

it('reset bằng token → mật khẩu mới, email được xác minh, remember token đổi', function () {
    $user = User::factory()->unverified()->create(['email' => 'u@example.com']);
    $oldRemember = $user->remember_token;
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token, 'email' => 'u@example.com', 'password' => 'newpassword', 'password_confirmation' => 'newpassword',
    ])->assertSessionHasNoErrors()->assertRedirect('/login');

    $user->refresh();
    expect(Hash::check('newpassword', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->remember_token)->not->toBe($oldRemember);

    $this->post('/login', ['email' => 'u@example.com', 'password' => 'newpassword'])->assertRedirect('/');
});

it('phiên đang mở bị đá khi mật khẩu được đặt lại', function () {
    config(['session.driver' => 'database']);
    forgetRequestState();
    $user = User::factory()->create(['email' => 'victim@example.com']);

    // Phiên A (vd kẻ chiếm trước) đăng nhập thật → dòng sessions có user_id.
    $this->post('/login', ['email' => 'victim@example.com', 'password' => 'password'])->assertRedirect('/');
    $sessionId = DB::table('sessions')->where('user_id', $user->id)->value('id');
    expect($sessionId)->not->toBeNull();

    forgetRequestState();
    $this->withCookie(config('session.cookie'), $sessionId)->get('/')->assertOk();

    // Chủ email (B) đặt lại mật khẩu.
    forgetRequestState();
    app(ResetUserPassword::class)->reset($user, ['password' => 'brandnew99', 'password_confirmation' => 'brandnew99']);
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);

    // Request kế tiếp của A về /login.
    forgetRequestState();
    $this->withCookie(config('session.cookie'), $sessionId)->get('/')->assertRedirect('/login');
});

it('TK chưa xác minh email đã bị cài 2FA → chủ email reset mật khẩu: 2FA bị xoá, đăng nhập không hỏi mã', function () {
    $user = User::factory()->unverified()->create(['email' => 'victim@example.com']);
    enableTwoFactor($user);

    $this->post('/reset-password', [
        'token' => Password::createToken($user), 'email' => 'victim@example.com', 'password' => 'newpassword', 'password_confirmation' => 'newpassword',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->two_factor_secret)->toBeNull()
        ->and($user->fresh()->two_factor_confirmed_at)->toBeNull();
    $this->post('/login', ['email' => 'victim@example.com', 'password' => 'newpassword'])->assertRedirect('/');
});

it('TK đã xác minh email reset mật khẩu → 2FA giữ nguyên, đăng nhập vẫn hỏi mã', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);
    enableTwoFactor($user);

    $this->post('/reset-password', [
        'token' => Password::createToken($user), 'email' => 'owner@example.com', 'password' => 'newpassword', 'password_confirmation' => 'newpassword',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
    $this->post('/login', ['email' => 'owner@example.com', 'password' => 'newpassword'])->assertRedirect(route('two-factor.login'));
});
