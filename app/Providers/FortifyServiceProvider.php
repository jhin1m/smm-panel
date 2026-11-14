<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\FailedTwoFactorLoginResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse as FailedTwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Responses\SuccessfulPasswordResetLinkRequestResponse;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Route Fortify tự khai ở routes/web.php để gắn limiter riêng cho register/forgot.
        Fortify::ignoreRoutes();

        // Quên mật khẩu: email không tồn tại cũng trả cùng thông báo (không lộ email nào đã đăng ký).
        $this->app->singleton(
            FailedPasswordResetLinkRequestResponse::class,
            fn () => new SuccessfulPasswordResetLinkRequestResponse(Password::RESET_LINK_SENT),
        );

        // Lỗi mã 2FA khi đăng nhập dùng chuỗi dịch của app thay vì chuỗi tiếng Anh mặc định của Fortify.
        $this->app->singleton(FailedTwoFactorLoginResponseContract::class, FailedTwoFactorLoginResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));

        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where('email', User::normalizeEmail($request->input('email')))->first();

            if (! $user || $user->password === null || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            // Admin chỉ đăng nhập ở /admin (có thử thách MFA); phiên từ /login sẽ né được MFA.
            if ($user->is_admin) {
                throw ValidationException::withMessages(['email' => __('account.errors.admin_only')]);
            }

            if ($user->isLocked()) {
                throw ValidationException::withMessages(['email' => __('account.errors.locked')]);
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $key = Str::transliterate(User::normalizeEmail($request->input('email')).'|'.$request->ip());

            return Limit::perMinute(5)->by($key);
        });

        // Khoá theo user đang bị thử thách (không theo phiên: kẻ biết mật khẩu mở phiên mới không reset được bộ đếm).
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by('2fa:'.($request->session()->get('login.id') ?? $request->ip())));
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('forgot', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('oauth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
