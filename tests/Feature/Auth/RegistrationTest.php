<?php

use App\Models\User;

it('đăng ký → tạo user, email lowercase, đăng nhập luôn', function () {
    $this->post('/register', [
        'name' => 'Khoa Trần',
        'email' => '  Khoa.Tran@Example.COM ',
        'password' => 'password1',
        'password_confirmation' => 'password1',
    ])->assertRedirect('/');

    $user = User::firstWhere('email', 'khoa.tran@example.com');
    expect($user)->not->toBeNull()->and($user->is_admin)->toBeFalse();
    $this->assertAuthenticatedAs($user);
});

it('email trùng khác hoa thường → lỗi', function () {
    User::factory()->create(['email' => 'a@example.com']);

    $this->post('/register', [
        'name' => 'X', 'email' => 'A@Example.com', 'password' => 'password1', 'password_confirmation' => 'password1',
    ])->assertSessionHasErrors('email');

    expect(User::count())->toBe(1);
});

it('mật khẩu 7 ký tự hoặc không khớp xác nhận → lỗi', function (string $pw, string $confirm) {
    $this->post('/register', [
        'name' => 'X', 'email' => 'x@example.com', 'password' => $pw, 'password_confirmation' => $confirm,
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
})->with([['1234567', '1234567'], ['password1', 'password2']]);

it('tên hiển thị quá 50 ký tự → lỗi', function () {
    $this->post('/register', [
        'name' => str_repeat('a', 51), 'email' => 'x@example.com', 'password' => 'password1', 'password_confirmation' => 'password1',
    ])->assertSessionHasErrors('name');
});

it('mass assignment không set được is_admin', function () {
    $u = User::create(['name' => 'x', 'email' => 'x@example.com', 'password' => 'secret123', 'is_admin' => true]);

    expect($u->fresh()->is_admin)->toBeFalse();
});
