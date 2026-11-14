<?php

use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\LocaleController;
use App\Livewire\AccountSecurity;
use App\Livewire\CustomerDashboard;
use App\Livewire\Deposit;
use App\Livewire\Install\InstallWizard;
use App\Livewire\MyOrders;
use App\Livewire\PlaceOrder;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;

/*
| Đổi ngôn ngữ: cả khách chưa đăng nhập lẫn đã đăng nhập.
*/
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

/*
| Wizard cài đặt lần đầu: chỉ mở khi chưa cài (đã cài → 404 trong component). Không throttle route vì
| token 192 bit và throttle của route không áp cho endpoint Livewire.
*/
Route::livewire('/install', InstallWizard::class)->name('install');

/*
| Auth: route Fortify tự khai (Fortify::ignoreRoutes) để gắn limiter riêng.
*/
Route::middleware(['guest', 'customer.maintenance'])->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');

    // Bước nhập mã 2FA sau khi mật khẩu/Google đúng (phiên chỉ giữ login.id, chưa đăng nhập). Không khai route quản lý 2FA của Fortify.
    Route::get('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'create'])->name('two-factor.login');
    Route::post('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])->middleware('throttle:two-factor')->name('two-factor.login.store');

    Route::get('/register', [RegisteredUserController::class, 'create'])->middleware('ensure.feature:registration')->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware(['ensure.feature:registration', 'throttle:register'])->name('register.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:forgot')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:forgot')->name('password.update');

    Route::middleware(['ensure.feature:google_login', 'throttle:oauth'])->group(function () {
        Route::get('/auth/google/redirect', [GoogleLoginController::class, 'redirect'])->name('auth.google.redirect');
        Route::get('/auth/google/callback', [GoogleLoginController::class, 'callback'])->name('auth.google.callback');
    });
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

/*
| Trang khách (đăng nhập).
*/
Route::middleware(['auth', 'customer.maintenance'])->group(function () {
    Route::livewire('/', CustomerDashboard::class)->name('home');
    Route::livewire('/dat-don', PlaceOrder::class)->name('orders.create');
    Route::livewire('/don-hang', MyOrders::class)->name('orders.index');
    Route::livewire('/nap-tien', Deposit::class)->name('deposit');
    Route::livewire('/bao-mat', AccountSecurity::class)->name('security');
});
