<?php

use App\Models\User;

it('TK bị khoá không đăng nhập được', function () {
    User::factory()->locked()->create(['email' => 'l@example.com']);

    $this->post('/login', ['email' => 'l@example.com', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Tài khoản đã bị khoá.']);

    $this->assertGuest();
});

it('đang đăng nhập bị khoá → request kế tiếp đăng xuất + về /login', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/')->assertOk();

    $user->forceFill(['locked_at' => now()])->save();

    $this->get('/')->assertRedirect('/login');
    $this->assertGuest();
});
