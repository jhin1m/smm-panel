<?php

use Livewire\Mechanisms\HandleRequests\EndpointResolver;

beforeEach(function () {
    $this->withoutVite();
    markNotInstalled();
});

it('chưa cài: trang khách, đăng nhập và /admin đều chuyển về /install', function (string $path) {
    $this->get($path)->assertRedirect(route('install'));
})->with(['/', '/login', '/register', '/dat-don', '/admin', '/admin/login']);

it('chưa cài: /install và /up vẫn truy cập được', function () {
    $this->get('/install')->assertOk();
    $this->get('/up')->assertOk();
});

it('chưa cài: webhook không bị chuyển hướng về /install', function () {
    $response = $this->postJson('/webhooks/sepay', []);

    expect($response->isRedirect())->toBeFalse();
});

it('chưa cài: đổi ngôn ngữ không bị chuyển hướng về /install', function () {
    $this->from('/install')->post(route('locale.update'), ['locale' => 'en'])->assertRedirect('/install');
});

it('chưa cài: POST HTTP thật tới URI update của Livewire không bị chuyển hướng', function () {
    $uri = route('default-livewire.update', absolute: false);

    $response = $this->withHeaders(['X-Livewire' => '1', 'Accept' => 'application/json'])
        ->post($uri, ['components' => []]);

    expect($response->isRedirect())->toBeFalse()
        ->and($response->headers->get('Location'))->toBeNull();
});

it('chưa cài: route livewire.* (upload) không bị chuyển hướng về /install', function () {
    $response = $this->withHeaders(['X-Livewire' => '1'])->post(route('livewire.upload-file'));

    expect($response->isRedirect() && str_contains((string) $response->headers->get('Location'), '/install'))->toBeFalse();
});

it('tên route Livewire dùng trong allowlist tồn tại thật', function () {
    expect(route('default-livewire.update', absolute: false))->toStartWith('/'.trim(EndpointResolver::prefix(), '/'))
        ->and(app('router')->has('livewire.upload-file'))->toBeTrue();
});

it('đã cài: trang khách không bị chuyển về /install, /install trả 404', function () {
    markInstalled();

    $this->get('/login')->assertOk();
    $this->get('/install')->assertNotFound();
    $this->get('/admin/login')->assertOk();
});
