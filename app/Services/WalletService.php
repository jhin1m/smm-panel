<?php

namespace App\Services;

use App\Enums\TxType;
use App\Exceptions\IdempotencyConflict;
use App\Exceptions\InsufficientBalance;
use App\Exceptions\SelfAdjustmentForbidden;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Nơi DUY NHẤT thay đổi users.balance / users.total_deposited.
 * Mỗi lệnh: transaction { khoá dòng user → kiểm idempotency → tính số dư → ghi sổ cái → cập nhật user }.
 * Lồng trong transaction của nơi gọi → chạy như savepoint (khoá giữ tới khi transaction ngoài commit).
 *
 * Quy ước khoá: khoá thực thể nghiệp vụ (order, bank_transaction) TRƯỚC, user SAU.
 * Ngoại lệ: tạo đơn mới khoá user trước khi INSERT order (FK lấy KEY SHARE trên users → deadlock nếu khoá sau).
 */
class WalletService
{
    public function credit(User $user, int $amount, TxType $type, string $key, ?Model $ref = null, ?User $actor = null, ?string $description = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('credit cần số tiền > 0');
        }

        return $this->apply($user->id, $amount, $type, $key, $ref, $actor, $description);
    }

    public function debit(User $user, int $amount, TxType $type, string $key, ?Model $ref = null, ?User $actor = null, ?string $description = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('debit cần số tiền > 0');
        }

        return $this->apply($user->id, -$amount, $type, $key, $ref, $actor, $description);
    }

    /** Điều chỉnh tay của admin: lý do bắt buộc, không tự điều chỉnh chính mình. */
    public function adjust(User $user, int $signed, string $reason, User $admin, string $key): WalletTransaction
    {
        if ($user->id === $admin->id) {
            throw new SelfAdjustmentForbidden;
        }

        if (blank($reason)) {
            throw new InvalidArgumentException('Điều chỉnh số dư cần lý do');
        }

        return $this->apply($user->id, $signed, TxType::Adjust, $key, null, $admin, trim($reason));
    }

    private function apply(int $userId, int $delta, TxType $type, string $key, ?Model $ref, ?User $actor, ?string $description): WalletTransaction
    {
        $type->assertSign($delta);

        return DB::transaction(function () use ($userId, $delta, $type, $key, $ref, $actor, $description) {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

            $dup = WalletTransaction::where('idempotency_key', $key)->first();
            if ($dup) {
                if ($dup->user_id !== $userId || $dup->amount !== $delta || $dup->type !== $type) {
                    throw new IdempotencyConflict($key);
                }

                return $dup;
            }

            $newBalance = $user->balance + $delta;
            if ($newBalance < 0) {
                throw new InsufficientBalance($user->balance, -$delta);
            }

            $tx = WalletTransaction::create([
                'user_id' => $userId,
                'type' => $type,
                'amount' => $delta,
                'balance_after' => $newBalance,
                'idempotency_key' => $key,
                'reference_type' => $ref?->getMorphClass(),
                'reference_id' => $ref?->getKey(),
                'description' => $description,
                'created_by' => $actor?->id,
            ]);

            $depositDelta = $type->affectsTotalDeposited() ? $delta : 0;

            DB::table('users')->where('id', $userId)->update([
                'balance' => $newBalance,
                'total_deposited' => DB::raw('total_deposited + '.(int) $depositDelta),
            ]);

            return $tx;
        }, attempts: 3);
    }
}
