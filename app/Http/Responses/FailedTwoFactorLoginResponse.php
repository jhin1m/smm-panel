<?php

namespace App\Http\Responses;

use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse as FailedTwoFactorLoginResponseContract;

/** Mã 2FA / recovery code sai khi đăng nhập: quay lại bước thử thách với thông báo đã dịch. */
class FailedTwoFactorLoginResponse implements FailedTwoFactorLoginResponseContract
{
    public function toResponse($request)
    {
        [$key, $message] = $request->filled('recovery_code')
            ? ['recovery_code', __('security.errors.invalid_recovery_code')]
            : ['code', __('security.errors.invalid_code')];

        if ($request->wantsJson()) {
            throw ValidationException::withMessages([$key => [$message]]);
        }

        return redirect()->route('two-factor.login')->withErrors([$key => $message]);
    }
}
