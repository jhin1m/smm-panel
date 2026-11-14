<?php

namespace App\Livewire;

use App\Models\User;
use App\Settings\AccessSettings;
use App\Support\UserSessions;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Trang Bảo mật: khách tự bật/tắt 2FA (TOTP) và tạo lại recovery code, gọi thẳng action của Fortify
 * (không có route quản lý 2FA của Fortify). Trạng thái luôn suy ra từ DB mỗi request; secret/recovery code
 * giải mã chỉ tồn tại lúc render (HTML), không nằm trong public property nên không lọt vào snapshot Livewire.
 *
 * Công tắc access.customer_2fa_enabled chỉ chặn việc BẬT mới; tắt / tạo lại recovery code luôn được phép.
 * Tắt và tạo lại mã yêu cầu mã TOTP hiện tại hoặc 1 recovery code (không dùng mật khẩu vì TK Google không có).
 */
class AccountSecurity extends Component
{
    private const MAX_PROOF_ATTEMPTS = 5;

    public string $code = '';

    /** Hiện recovery code đúng 1 lần sau khi bật / tạo lại; mã được đọc từ DB lúc render. */
    #[Locked]
    public bool $showRecoveryCodes = false;

    public function mount(): void
    {
        abort_if($this->user()->is_admin, 403);
    }

    /** Bắt đầu thiết lập, hoặc đổi secret/QR khi đang thiết lập dở (chưa xác nhận). */
    public function enable(): void
    {
        $user = $this->user();
        abort_unless($this->canEnable(), 403);
        // Đã xác nhận thì không được đổi secret (phiên bị đánh cắp không thay được thiết bị của chủ TK).
        abort_if($user->two_factor_confirmed_at !== null, 403);

        app(EnableTwoFactorAuthentication::class)($user, force: $user->two_factor_secret !== null);
        $this->reset('code');
        $this->showRecoveryCodes = false;
    }

    public function confirm(): void
    {
        $user = $this->user();
        abort_unless($this->canEnable(), 403);
        abort_if($user->two_factor_secret === null || $user->two_factor_confirmed_at !== null, 403);

        if (! $this->checkAttemptsAndCode()) {
            return;
        }

        try {
            app(ConfirmTwoFactorAuthentication::class)($user, trim($this->code));
        } catch (ValidationException) {
            $this->failAttempt();

            return;
        }

        RateLimiter::clear($this->limiterKey());
        UserSessions::invalidate($user, session()->getId());
        $this->reset('code');
        $this->showRecoveryCodes = true;
        $this->dispatch('toast', message: __('security.status_messages.enabled'));
    }

    /** Bỏ thiết lập dở. Chỉ khi chưa xác nhận (đã bật phải qua disable() có mã). */
    public function cancelSetup(): void
    {
        $user = $this->user();
        abort_if($user->two_factor_confirmed_at !== null, 403);

        app(DisableTwoFactorAuthentication::class)($user);
        $this->reset('code');
    }

    public function disable(): void
    {
        $user = $this->user();
        abort_unless($user->hasEnabledTwoFactorAuthentication(), 403);

        if (! $this->checkAttemptsAndCode() || ! $this->proofPasses($user)) {
            return;
        }

        app(DisableTwoFactorAuthentication::class)($user);
        UserSessions::invalidate($user, session()->getId());
        $this->reset('code');
        $this->showRecoveryCodes = false;
        $this->dispatch('toast', message: __('security.status_messages.disabled'));
    }

    public function regenerateRecoveryCodes(): void
    {
        $user = $this->user();
        abort_unless($user->hasEnabledTwoFactorAuthentication(), 403);

        if (! $this->checkAttemptsAndCode() || ! $this->proofPasses($user)) {
            return;
        }

        app(GenerateNewRecoveryCodes::class)($user);
        $this->reset('code');
        $this->showRecoveryCodes = true;
        $this->dispatch('toast', message: __('security.status_messages.regenerated'));
    }

    public function dismissRecoveryCodes(): void
    {
        $this->showRecoveryCodes = false;
    }

    private function user(): User
    {
        return auth()->user();
    }

    private function canEnable(): bool
    {
        return app(AccessSettings::class)->customer_2fa_enabled;
    }

    private function limiterKey(): string
    {
        return 'account-2fa:'.auth()->id();
    }

    /** Chặn dò mã từ phiên bị đánh cắp: quá số lần sai trong 1 phút thì từ chối cả mã đúng. */
    private function checkAttemptsAndCode(): bool
    {
        $this->resetErrorBag();

        if (RateLimiter::tooManyAttempts($this->limiterKey(), self::MAX_PROOF_ATTEMPTS)) {
            $this->addError('code', __('security.errors.too_many', ['seconds' => RateLimiter::availableIn($this->limiterKey())]));

            return false;
        }

        if (trim($this->code) === '') {
            $this->addError('code', __('security.errors.code_required'));

            return false;
        }

        return true;
    }

    private function failAttempt(): void
    {
        RateLimiter::hit($this->limiterKey(), 60);
        $this->addError('code', __('security.errors.invalid_code'));
    }

    /** Mã TOTP hiện tại, hoặc 1 recovery code (dùng xong bị thay bằng mã mới). */
    private function proofPasses(User $user): bool
    {
        $code = trim($this->code);
        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        if (app(TwoFactorAuthenticationProvider::class)->verify($secret, $code)) {
            RateLimiter::clear($this->limiterKey());

            return true;
        }

        foreach ($user->recoveryCodes() as $recovery) {
            if (hash_equals($recovery, $code)) {
                $user->replaceRecoveryCode($recovery);
                RateLimiter::clear($this->limiterKey());

                return true;
            }
        }

        $this->failAttempt();

        return false;
    }

    public function render()
    {
        $user = $this->user();
        $enabled = $user->hasEnabledTwoFactorAuthentication();
        $pending = ! $enabled && $user->two_factor_secret !== null;
        $canEnable = $this->canEnable();
        $showSetup = $pending && $canEnable;

        return view('livewire.account-security', [
            'enabled' => $enabled,
            'pending' => $pending,
            'canEnable' => $canEnable,
            'qrSvg' => $showSetup ? $user->twoFactorQrCodeSvg() : null,
            'secret' => $showSetup ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret) : null,
            'recoveryCodes' => $enabled && $this->showRecoveryCodes ? $user->recoveryCodes() : [],
        ])->title(__('security.title'));
    }
}
