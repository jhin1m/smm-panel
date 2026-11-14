<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Đặt lại mật khẩu = chứng minh sở hữu email → huỷ mọi phiên đang mở (kể cả của kẻ chiếm trước),
 * rotate remember token, đánh dấu email đã xác minh. TK chưa xác minh email có thể do kẻ khác đăng ký
 * trước: xoá luôn 2FA họ cài, nếu không chủ email thật bị kẹt ở bước nhập mã (giống nhánh Google).
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /** @param  array<string, string>  $input */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, ['password' => $this->passwordRules()])->validate();

        DB::transaction(function () use ($user, $input) {
            $wipeTwoFactor = $user->email_verified_at === null ? [
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ] : [];

            $user->forceFill([
                'password' => $input['password'],
                'email_verified_at' => $user->email_verified_at ?? now(),
                ...$wipeTwoFactor,
            ])->setRememberToken(Str::random(60));
            $user->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }
}
