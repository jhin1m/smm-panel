<?php

namespace App\Services;

use App\Enums\Tier;
use App\Models\User;
use App\Settings\TierSettings;
use Illuminate\Support\Facades\DB;

class TierService
{
    /** Đọc settings mỗi lần gọi (không giữ bản cũ trong worker chạy lâu). */
    private function settings(): TierSettings
    {
        return app(TierSettings::class);
    }

    /**
     * Nâng Đại lý nếu đủ ngưỡng. Gọi trong CÙNG transaction, sau khi cộng tiền nạp (user đang bị khoá dòng).
     * Không tự nâng user đã được admin đổi cấp tay (tier_manual).
     */
    public function promoteIfEligible(int $userId): bool
    {
        $u = User::whereKey($userId)->lockForUpdate()->first();

        if (! $u || $u->tier !== Tier::Member || $u->tier_manual || $u->total_deposited < $this->settings()->agency_threshold) {
            return false;
        }

        DB::table('users')->where('id', $userId)->update(['tier' => Tier::Agency->value, 'tier_changed_at' => now()]);

        return true;
    }

    /** Nâng hàng loạt sau khi đổi ngưỡng (1 câu UPDATE). Trả số user được nâng. */
    public function promoteAllEligible(): int
    {
        return DB::table('users')
            ->where('tier', Tier::Member->value)
            ->where('tier_manual', false)
            ->where('total_deposited', '>=', $this->settings()->agency_threshold)
            ->update(['tier' => Tier::Agency->value, 'tier_changed_at' => now()]);
    }

    public function discountPercent(User $user): int
    {
        return $user->tier === Tier::Agency
            ? $this->settings()->agency_discount_percent
            : $this->settings()->member_discount_percent;
    }
}
