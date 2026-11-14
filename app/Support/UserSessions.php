<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Huỷ phiên đăng nhập của khách: xoay remember token (vô hiệu cookie ghi nhớ) + xoá dòng `sessions` còn lại. */
final class UserSessions
{
    /** @param  string|null  $keepSessionId  phiên được giữ lại (user tự thao tác); null = xoá hết */
    public static function invalidate(User $user, ?string $keepSessionId = null): void
    {
        $user->setRememberToken(Str::random(60));
        $user->save();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($keepSessionId !== null, fn ($q) => $q->where('id', '!=', $keepSessionId))
            ->delete();
    }
}
