<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Settings\AccessSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Đăng nhập Google. Chống chiếm tài khoản trước: chỉ ghép vào TK có sẵn khi Google xác nhận email đã xác minh;
 * TK local chưa từng xác minh email → vô hiệu mật khẩu cũ + huỷ mọi phiên (có thể do kẻ khác tạo sẵn).
 */
class GoogleLoginController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $g = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->fail(__('account.errors.google_expired'));
        } catch (Throwable) {
            return $this->fail(__('account.errors.google_failed'));
        }

        $email = User::normalizeEmail($g->getEmail());
        $verified = filter_var($g->user['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($email === '') {
            return $this->fail(__('account.errors.google_no_email'));
        }

        $user = User::where('google_id', $g->getId())->first();

        if (! $user) {
            if (! $verified) {
                return $this->fail(__('account.errors.google_unverified'));
            }

            $user = User::where('email', $email)->first();

            // Từ chối admin/TK khoá TRƯỚC khi ghép hay vô hiệu mật khẩu.
            if ($user?->is_admin) {
                return $this->fail(__('account.errors.admin_only'));
            }
            if ($user?->isLocked()) {
                return $this->fail(__('account.errors.locked'));
            }

            if ($user) {
                DB::transaction(function () use ($user, $g) {
                    if ($user->email_verified_at === null) {
                        // 2FA do kẻ đăng ký trước đặt cũng phải xoá, nếu không chủ email thật bị kẹt ở bước mã.
                        $user->forceFill([
                            'password' => null,
                            'two_factor_secret' => null,
                            'two_factor_recovery_codes' => null,
                            'two_factor_confirmed_at' => null,
                        ])->setRememberToken(Str::random(60));
                        DB::table('sessions')->where('user_id', $user->id)->delete();
                        session()->flash('status', __('account.status.password_disabled'));
                    }
                    $user->forceFill([
                        'google_id' => $g->getId(),
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ])->save();
                });
            } else {
                // Tắt đăng ký → Google chỉ để đăng nhập TK đã có, không tạo TK mới.
                if (! app(AccessSettings::class)->registration_enabled) {
                    return $this->fail(__('account.errors.registration_disabled'));
                }

                $user = new User([
                    'name' => Str::limit(trim((string) ($g->getName() ?: Str::before($email, '@'))), 50, ''),
                    'email' => $email,
                ]);
                $user->forceFill(['google_id' => $g->getId(), 'email_verified_at' => now()])->save();
            }
        }

        if ($user->is_admin) {
            return $this->fail(__('account.errors.admin_only'));
        }

        if ($user->isLocked()) {
            return $this->fail(__('account.errors.locked'));
        }

        // TK đã bật 2FA: chưa đăng nhập, chuyển sang bước nhập mã (cùng thử thách với đăng nhập mật khẩu).
        // remember = true giữ nguyên hành vi Auth::login(remember: true) bên dưới.
        if ($user->hasEnabledTwoFactorAuthentication()) {
            session()->put(['login.id' => $user->getKey(), 'login.remember' => true]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, remember: true);
        session()->regenerate();

        return redirect()->intended(route('home'));
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
