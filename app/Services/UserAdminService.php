<?php

namespace App\Services;

use App\Enums\Tier;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\AuditLog;
use App\Support\DepositCode;
use App\Support\UserSessions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Thao tác admin trên tài khoản khách. Mỗi thao tác: 1 transaction + 1 dòng audit (before/after).
 */
class UserAdminService
{
    public function __construct(private WalletService $wallet, private TierService $tiers) {}

    public function lock(User $user, User $admin): void
    {
        if ($user->id === $admin->id) {
            throw new InvalidArgumentException(__('admin.users.errors.cannot_lock_self'));
        }

        $this->update($user, $admin, 'user.lock', ['locked_at' => now()]);
    }

    public function unlock(User $user, User $admin): void
    {
        $this->update($user, $admin, 'user.unlock', ['locked_at' => null]);
    }

    /** Đổi cấp tay → tier_manual = true (không tự nâng/hạ nữa). */
    public function changeTier(User $user, Tier $tier, User $admin): void
    {
        $this->update($user, $admin, 'user.tier', ['tier' => $tier, 'tier_manual' => true, 'tier_changed_at' => now()]);
    }

    /** Trả về chế độ tự động: bỏ cờ tay, nâng ngay nếu đã đủ ngưỡng (không tự hạ). */
    public function resetTierAuto(User $user, User $admin): void
    {
        DB::transaction(function () use ($user, $admin) {
            $this->update($user, $admin, 'user.tier_auto', ['tier_manual' => false]);
            $this->tiers->promoteIfEligible($user->id);
        });
    }

    /** Nâng hàng loạt lên Đại lý (đánh dấu tay). Trả số user đổi. */
    public function bulkPromote(iterable $users, User $admin): int
    {
        $n = 0;
        foreach ($users as $user) {
            if ($user->tier !== Tier::Agency) {
                $this->changeTier($user, Tier::Agency, $admin);
                $n++;
            }
        }

        return $n;
    }

    /** Cấp mã nạp mới (mã cũ hết khớp → chuyển khoản theo mã cũ thành chưa khớp, admin gán tay). */
    public function reissueDepositCode(User $user, User $admin): string
    {
        $code = DepositCode::unique();
        $this->update($user, $admin, 'user.deposit_code', ['deposit_code' => $code]);

        return $code;
    }

    /** Điều chỉnh số dư tay. KHÔNG dùng để bù tiền nạp (dùng đối soát SePay). */
    public function adjustBalance(User $user, int $signed, string $reason, User $admin, string $key): WalletTransaction
    {
        return DB::transaction(function () use ($user, $signed, $reason, $admin, $key) {
            $before = $user->fresh()->balance;
            $tx = $this->wallet->adjust($user, $signed, $reason, $admin, $key);

            if ($tx->wasRecentlyCreated) {
                AuditLog::record($admin, 'user.adjust_balance', $user, [
                    'before' => ['balance' => $before],
                    'after' => ['balance' => $tx->balance_after],
                    'amount' => $signed,
                    'reason' => $reason,
                    'wallet_transaction_id' => $tx->id,
                ]);
            }

            return $tx;
        });
    }

    /**
     * Gỡ 2FA của khách (mất điện thoại + hết recovery code). Huỷ mọi phiên/remember cookie cũ.
     * Audit chỉ ghi trạng thái, không ghi secret/recovery code.
     */
    public function resetTwoFactor(User $user, string $reason, User $admin): void
    {
        DB::transaction(function () use ($user, $reason, $admin) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $before = $locked->hasEnabledTwoFactorAuthentication() ? 'enabled' : ($locked->two_factor_secret !== null ? 'pending' : 'off');

            $locked->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ]);
            UserSessions::invalidate($locked);

            AuditLog::record($admin, 'user.two_factor_reset', $locked, [
                'before' => ['two_factor' => $before],
                'after' => ['two_factor' => 'off'],
                'reason' => $reason,
            ]);
            $user->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /** @param  array<string, mixed>  $attrs */
    private function update(User $user, User $admin, string $action, array $attrs): void
    {
        DB::transaction(function () use ($user, $admin, $action, $attrs) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $before = collect(array_keys($attrs))->mapWithKeys(fn ($k) => [$k => $this->plain($locked->{$k})])->all();

            $locked->forceFill($attrs)->save();
            $after = collect(array_keys($attrs))->mapWithKeys(fn ($k) => [$k => $this->plain($locked->{$k})])->all();

            AuditLog::record($admin, $action, $locked, ['before' => $before, 'after' => $after]);
            $user->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private function plain(mixed $v): mixed
    {
        return match (true) {
            $v instanceof \BackedEnum => $v->value,
            $v instanceof \DateTimeInterface => $v->format('Y-m-d H:i:s'),
            default => $v,
        };
    }
}
