<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('ghi đè user có sẵn: mật khẩu mới, is_admin, xoá MFA và phiên cũ', function () {
    $u = User::factory()->create(['email' => 'boss@example.com']);
    $u->forceFill(['app_authentication_secret' => 'OLDSECRET'])->save();
    DB::table('sessions')->insert(['id' => 'sess-1', 'user_id' => $u->id, 'payload' => '', 'last_activity' => time()]);

    $this->artisan('app:make-admin')
        ->expectsQuestion('Email', 'Boss@example.com')
        ->expectsQuestion('Tên hiển thị', 'Sếp')
        ->expectsQuestion('Mật khẩu (tối thiểu 12 ký tự)', 'super-secret-123')
        ->expectsQuestion('Nhập lại mật khẩu', 'super-secret-123')
        ->expectsConfirmation('User boss@example.com đã tồn tại. Ghi đè mật khẩu, xoá MFA và cấp quyền admin?', 'yes')
        ->assertSuccessful();

    $u->refresh();
    expect($u->is_admin)->toBeTrue()
        ->and(Hash::check('super-secret-123', $u->password))->toBeTrue()
        ->and($u->app_authentication_secret)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $u->id)->count())->toBe(0);
});

it('tạo admin mới', function () {
    $this->artisan('app:make-admin')
        ->expectsQuestion('Email', 'new@example.com')
        ->expectsQuestion('Tên hiển thị', 'Admin')
        ->expectsQuestion('Mật khẩu (tối thiểu 12 ký tự)', 'super-secret-123')
        ->expectsQuestion('Nhập lại mật khẩu', 'super-secret-123')
        ->assertSuccessful();

    expect(User::firstWhere('email', 'new@example.com')->is_admin)->toBeTrue();
});

it('mật khẩu ngắn → thất bại', function () {
    $this->artisan('app:make-admin')
        ->expectsQuestion('Email', 'new@example.com')
        ->expectsQuestion('Tên hiển thị', 'Admin')
        ->expectsQuestion('Mật khẩu (tối thiểu 12 ký tự)', 'short')
        ->expectsQuestion('Nhập lại mật khẩu', 'short')
        ->assertFailed();

    expect(User::count())->toBe(0);
});
