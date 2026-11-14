<?php

use App\Models\User;

it('khách vào /admin → 403', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('admin bị khoá → 403', function () {
    $this->actingAs(User::factory()->admin()->locked()->create())->get('/admin')->assertForbidden();
});

it('admin đăng nhập qua /login → bị từ chối, hướng về /admin', function () {
    User::factory()->admin()->create(['email' => 'admin@example.com']);

    $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Quản trị viên đăng nhập tại /admin.']);

    $this->assertGuest();
});

it('admin đã có MFA vào được dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
});

it('trang cài đặt Telegram chỉ admin vào được', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin/manage-telegram-settings')->assertOk();
    $this->actingAs(User::factory()->create())->get('/admin/manage-telegram-settings')->assertForbidden();
});
