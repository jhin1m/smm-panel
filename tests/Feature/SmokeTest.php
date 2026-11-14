<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('health check trả 200', function () {
    $this->get('/up')->assertOk();
});

it('chạy trên pgsql với timezone giờ VN', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::selectOne("select current_setting('TimeZone') as tz")->tz)->toBe('Asia/Ho_Chi_Minh')
        ->and(config('app.timezone'))->toBe('Asia/Ho_Chi_Minh');
});

it('trang đăng nhập admin hiển thị', function () {
    $this->get('/admin/login')->assertOk();
});

it('tin X-Forwarded-For chỉ từ proxy trong TRUSTED_PROXIES', function () {
    config(['app.trusted_proxies' => ['10.0.0.0/8']]);
    Route::get('/_test/ip', fn () => request()->ip());

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
        ->get('/_test/ip', ['X-Forwarded-For' => '203.0.113.9'])
        ->assertSeeText('203.0.113.9');

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->get('/_test/ip', ['X-Forwarded-For' => '203.0.113.9'])
        ->assertSeeText('198.51.100.7');
});

it('trusted proxies đọc qua config, không gọi env() lúc chạy', function () {
    expect(file_get_contents(base_path('bootstrap/app.php')))->not->toContain("env('TRUSTED_PROXIES')")
        ->and(file_get_contents(app_path('Http/Middleware/TrustProxies.php')))->not->toContain('env(');
});
