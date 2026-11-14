<?php

it('đăng ký lần thứ 6 trong 1 phút → 429', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/register', ['name' => 'x', 'email' => "bad{$i}", 'password' => 'x'])->assertStatus(302);
    }

    $this->post('/register', ['name' => 'x', 'email' => 'bad6', 'password' => 'x'])->assertStatus(429);
});

it('quên mật khẩu lần thứ 4 trong 1 phút → 429', function () {
    foreach (range(1, 3) as $i) {
        $this->post('/forgot-password', ['email' => "n{$i}@example.com"])->assertStatus(302);
    }

    $this->post('/forgot-password', ['email' => 'n4@example.com'])->assertStatus(429);
});

it('đăng nhập sai lần thứ 6 cùng email+IP → 429', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => 'x@example.com', 'password' => 'bad'])->assertStatus(302);
    }

    $this->post('/login', ['email' => 'x@example.com', 'password' => 'bad'])->assertStatus(429);
});
