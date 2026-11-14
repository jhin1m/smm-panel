<?php

use App\Models\User;

it('đăng nhập đúng → vào trang chủ', function () {
    $user = User::factory()->create(['email' => 'u@example.com']);

    $this->post('/login', ['email' => 'U@example.com', 'password' => 'password'])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('sai mật khẩu → lỗi', function () {
    User::factory()->create(['email' => 'u@example.com']);

    $this->post('/login', ['email' => 'u@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('user không có mật khẩu (Google) không đăng nhập bằng mật khẩu', function () {
    $u = User::factory()->create(['email' => 'g@example.com']);
    $u->forceFill(['password' => null])->save();

    $this->post('/login', ['email' => 'g@example.com', 'password' => ''])->assertSessionHasErrors();
    $this->post('/login', ['email' => 'g@example.com', 'password' => 'password'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('trang khách yêu cầu đăng nhập', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/login')->assertOk()->assertSee('Tiếp tục với Google');
    $this->get('/register')->assertOk()->assertSee('Tên hiển thị');
});

it('đăng xuất', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});
