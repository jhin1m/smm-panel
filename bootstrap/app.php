<?php

use App\Http\Middleware\CustomerMaintenance;
use App\Http\Middleware\EnsureAccountNotLocked;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrustProxies;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('throttle:120,1')->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(BaseTrustProxies::class, TrustProxies::class);
        $middleware->alias([
            'ensure.feature' => EnsureFeatureEnabled::class,
            'customer.maintenance' => CustomerMaintenance::class,
        ]);
        $middleware->web(append: [EnsureAccountNotLocked::class, SetLocale::class, EnsureInstalled::class]);
        // Laravel sắp middleware có tên trong priority list (auth...) lên trước phần còn lại; EnsureInstalled phải đứng
        // trước middleware xác thực (AuthenticatesRequests), nếu không khách chưa đăng nhập bị đẩy sang /login thay vì /install.
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: EnsureInstalled::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
