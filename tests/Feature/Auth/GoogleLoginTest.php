<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeGoogleUser(string $email, bool $verified = true, string $id = 'g-123', string $name = 'Google User'): void
{
    $g = (new SocialiteUser)->setRaw(['email_verified' => $verified])->map([
        'id' => $id, 'name' => $name, 'email' => $email,
    ]);
    Socialite::shouldReceive('driver->user')->andReturn($g);
}

it('tạo user mới từ Google', function () {
    fakeGoogleUser('New@Gmail.com');

    $this->get('/auth/google/callback')->assertRedirect('/');

    $u = User::firstWhere('email', 'new@gmail.com');
    expect($u->google_id)->toBe('g-123')->and($u->password)->toBeNull()->and($u->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($u);
});

it('ghép vào TK có sẵn đã xác minh khi email Google đã xác minh', function () {
    $u = User::factory()->create(['email' => 'a@gmail.com']);
    fakeGoogleUser('a@gmail.com');

    $this->get('/auth/google/callback')->assertRedirect('/');

    expect($u->fresh()->google_id)->toBe('g-123')->and($u->fresh()->password)->not->toBeNull();
    $this->assertAuthenticatedAs($u);
});

it('từ chối khi email Google chưa xác minh', function () {
    User::factory()->create(['email' => 'a@gmail.com']);
    fakeGoogleUser('a@gmail.com', verified: false);

    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(User::firstWhere('email', 'a@gmail.com')->google_id)->toBeNull();
});

it('ghép vào TK local chưa xác minh → vô hiệu mật khẩu, xoá phiên cũ, flash giải thích', function () {
    $u = User::factory()->unverified()->create(['email' => 'victim@gmail.com']);
    DB::table('sessions')->insert(['id' => 'attacker', 'user_id' => $u->id, 'payload' => '', 'last_activity' => time()]);
    fakeGoogleUser('victim@gmail.com');

    $this->get('/auth/google/callback')->assertRedirect('/')->assertSessionHas('status');

    $u->refresh();
    expect($u->password)->toBeNull()
        ->and($u->email_verified_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('id', 'attacker')->exists())->toBeFalse();
});

it('user bị khoá bị từ chối', function () {
    User::factory()->locked()->create(['email' => 'l@gmail.com', 'google_id' => null]);
    fakeGoogleUser('l@gmail.com');

    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors(['email' => 'Tài khoản đã bị khoá.']);
    $this->assertGuest();
});

it('admin bị từ chối', function () {
    User::factory()->admin()->create(['email' => 'boss@gmail.com']);
    fakeGoogleUser('boss@gmail.com');

    $this->get('/auth/google/callback')->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Quản trị viên đăng nhập tại /admin.']);
    $this->assertGuest();
});

it('admin chưa xác minh email: từ chối mà không đụng mật khẩu / google_id', function () {
    $admin = User::factory()->admin()->unverified()->create(['email' => 'boss2@gmail.com']);
    fakeGoogleUser('boss2@gmail.com');

    $this->get('/auth/google/callback')->assertRedirect('/login');

    expect($admin->fresh()->password)->not->toBeNull()->and($admin->fresh()->google_id)->toBeNull();
});
