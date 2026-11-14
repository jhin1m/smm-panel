<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tạo admin hoặc ghi đè user có sẵn thành admin. Luôn đặt mật khẩu MỚI + xoá MFA cũ + huỷ mọi phiên:
 * không cấp quyền admin cho TK mà người khác có thể đang giữ mật khẩu (chiếm trước email).
 * Dùng chung cho `app:make-admin` và wizard /install.
 */
class CreateOrResetAdmin
{
    public function handle(string $email, string $name, string $password): User
    {
        $email = User::normalizeEmail($email);

        return DB::transaction(function () use ($email, $name, $password) {
            $user = User::where('email', $email)->first() ?? new User;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'is_admin' => true,
                'locked_at' => null,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
            ])->setRememberToken(Str::random(60));
            $user->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();

            return $user;
        });
    }
}
