<?php

use App\Models\User;
use Filament\Auth\Pages\Login;
use Livewire\Livewire;

it('admin chưa có MFA vào /admin → chuyển tới màn thiết lập MFA', function () {
    $this->actingAs(User::factory()->admin(withMfa: false)->create())
        ->get('/admin')
        ->assertRedirect('/admin/multi-factor-authentication/set-up');
});

it('đăng nhập /admin với MFA → bắt nhập mã, chưa đăng nhập ngay', function () {
    filament()->setCurrentPanel('admin');
    User::factory()->admin()->create(['email' => 'admin@example.com']);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'admin@example.com', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertGuest();
});
