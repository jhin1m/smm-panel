<?php

use App\Settings\GeneralSettings;
use App\Support\CustomOverrides;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    // Manifest Vite chỉ có trong image đã build; test không dựng asset.
    $this->withoutVite();
});

it('/up trả 200 khi DB, settings và view đều ổn', function () {
    $this->get('/up')->assertOk();
});

it('/up vẫn 200 khi chưa cài đặt (không bị chuyển sang /install)', function () {
    markNotInstalled();

    $this->get('/up')->assertOk();
});

it('/up không ghi session và không đổi locale', function () {
    app()->setLocale('en');

    $response = $this->get('/up');

    $response->assertOk();
    expect(app()->getLocale())->toBe('en')
        ->and($response->headers->getCookies())->toBe([]);
});

it('/up trả 500 khi DB không trả lời', function () {
    DB::shouldReceive('select')->with('select 1')->once()->andThrow(new RuntimeException('db down'));

    $this->get('/up')->assertServerError();
});

it('/up trả 500 khi không đọc được settings', function () {
    app()->bind(GeneralSettings::class, fn () => throw new RuntimeException('settings broken'));

    $this->get('/up')->assertServerError();
});

it('/up trả 500 khi view đăng nhập lỗi', function () {
    // Thành phần con của view đăng nhập lõi (include) lỗi biên dịch/render.
    View::composer('auth._tabs', fn () => throw new RuntimeException('view broken'));

    $this->get('/up')->assertServerError();
});

it('/up trả 500 khi bảng settings không đọc được', function () {
    app()->forgetInstance(GeneralSettings::class);
    DB::statement('alter table settings rename to settings_broken');

    $this->get('/up')->assertServerError();
});

it('/up vẫn 200 khi view tuỳ biến trong custom/ bị lỗi (đăng nhập, layout, include, component)', function () {
    config(['app.custom_path' => base_path('tests/Fixtures/custom-broken')]);
    CustomOverrides::register();

    // Xác nhận fixture thật sự thắng bản lõi ở render thường; chỉ health bỏ qua nó.
    expect(fn () => view('auth.login')->with('errors', new ViewErrorBag)->render())
        ->toThrow(Exception::class, 'custom view broken');

    $this->get('/up')->assertOk();

    // Sau health, view tuỳ biến vẫn có hiệu lực cho request thường.
    expect(fn () => view('auth.login')->with('errors', new ViewErrorBag)->render())
        ->toThrow(Exception::class, 'custom view broken');
});
