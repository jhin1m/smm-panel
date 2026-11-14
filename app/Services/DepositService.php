<?php

namespace App\Services;

use App\Enums\TxType;
use App\Exceptions\ConfirmationRequired;
use App\Exceptions\DepositAlreadyProcessed;
use App\Models\BankTransaction;
use App\Models\User;
use App\Settings\DepositSettings;
use App\Support\AuditLog;
use App\Support\DepositCodeMatcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Nạp tiền qua SePay. Webhook, đối soát và nhập tay đều đi qua handleWebhook() (unique sepay_id chống trùng).
 * Thứ tự khoá: bank_transaction trước, user sau (WalletService).
 */
class DepositService
{
    public const DUPLICATE = 'duplicate';

    public const IGNORED = 'ignored';

    public const UNMATCHED = 'unmatched';

    public const CREDITED = 'credited';

    public function __construct(
        private WalletService $wallet,
        private TierService $tiers,
        private DepositCodeMatcher $matcher,
        private AdminNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $p  payload dạng webhook SePay (id, transferType, transferAmount, content, code, ...)
     * @return string duplicate | ignored | unmatched | credited
     */
    public function handleWebhook(array $p, string $source): string
    {
        return DB::transaction(function () use ($p, $source) {
            $type = (string) $p['transferType'];
            $amount = (int) $p['transferAmount'];

            // ON CONFLICT DO NOTHING: bản trùng (retry/replay/đối soát) chờ bản đầu commit rồi bỏ qua.
            $inserted = DB::table('bank_transactions')->insertOrIgnore([
                'sepay_id' => (int) $p['id'],
                'source' => $source,
                'gateway' => $this->str($p['gateway'] ?? null, 50),
                'account_number' => $this->str($p['accountNumber'] ?? null, 50),
                'sub_account' => $this->str($p['subAccount'] ?? null, 50),
                'transfer_type' => $type,
                'transfer_amount' => $amount,
                'code' => $this->str($p['code'] ?? null, 50),
                'content' => (string) ($p['content'] ?? ''),
                'reference_code' => $this->str($p['referenceCode'] ?? null, 100),
                'transaction_date' => $this->parseDate($p['transactionDate'] ?? null),
                'payload' => json_encode($p, JSON_UNESCAPED_UNICODE),
                'bonus_percent' => $type === 'in' ? app(DepositSettings::class)->bonusPercentFor($amount) : 0,
                'status' => BankTransaction::STATUS_PENDING,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return self::DUPLICATE;
            }

            $tx = BankTransaction::where('sepay_id', (int) $p['id'])->lockForUpdate()->firstOrFail();

            if ($tx->transfer_type !== 'in') {
                $tx->update(['status' => BankTransaction::STATUS_IGNORED, 'note' => 'deposit.notes.transfer_out']);

                return self::IGNORED;
            }

            if (! $this->accountMatches($tx)) {
                $tx->update(['status' => BankTransaction::STATUS_UNMATCHED, 'note' => 'deposit.notes.wrong_account']);

                return self::UNMATCHED;
            }

            // Chống cộng 2 lần qua nhiều đường (webhook / đối soát / nhập tay) khi SePay ID không trùng nhau:
            // cùng mã tham chiếu ngân hàng + số tiền đã có ở giao dịch khác → giữ chưa khớp, báo admin kiểm tay.
            if ($twin = $this->sameBankReference($tx)) {
                $tx->update(['status' => BankTransaction::STATUS_UNMATCHED, 'note' => BankTransaction::noteKey('deposit.notes.suspected_duplicate', ['twin' => $twin->sepay_id])]);
                $this->notifier->send(__('telegram.deposit_suspected_duplicate', [
                    'id' => $tx->sepay_id,
                    'twin' => $twin->sepay_id,
                    'reference' => $tx->reference_code,
                    'amount' => $tx->transfer_amount,
                ], config('app.default_locale')));

                return self::UNMATCHED;
            }

            $user = $this->matcher->match($tx->code, $tx->content);
            if (! $user) {
                $tx->update(['status' => BankTransaction::STATUS_UNMATCHED]);

                return self::UNMATCHED;
            }

            $this->creditFor($tx, $user, null);

            return self::CREDITED;
        });
    }

    /** Admin gán giao dịch chưa khớp (hoặc đã bỏ qua, cần xác nhận) cho khách. */
    public function assign(int $bankTxId, User $user, User $admin, bool $confirmIgnored = false): BankTransaction
    {
        return DB::transaction(function () use ($bankTxId, $user, $admin, $confirmIgnored) {
            $tx = BankTransaction::whereKey($bankTxId)->lockForUpdate()->firstOrFail();

            if ($tx->transfer_type !== 'in' || ! in_array($tx->status, [BankTransaction::STATUS_UNMATCHED, BankTransaction::STATUS_IGNORED], true)) {
                throw new DepositAlreadyProcessed;
            }

            if ($user->is_admin) {
                throw new InvalidArgumentException(__('admin.bank_transactions.errors.cannot_assign_admin'));
            }

            if ($tx->status === BankTransaction::STATUS_IGNORED && ! $confirmIgnored) {
                throw new ConfirmationRequired;
            }

            $before = $tx->only(['status', 'user_id']);
            $this->creditFor($tx, $user, $admin);
            AuditLog::record($admin, 'deposit.assign', $tx, [
                'before' => $before,
                'after' => $tx->only(['status', 'user_id', 'bonus_amount', 'assignment_no']),
            ]);

            return $tx;
        });
    }

    /**
     * Huỷ gán (gán nhầm khách): trừ lại tiền nạp + khuyến mãi bằng bút toán đảo, bank tx về "chưa khớp".
     * Thiếu số dư → từ chối toàn bộ. Không tự hạ cấp.
     */
    public function unassign(int $bankTxId, User $admin, string $reason): BankTransaction
    {
        if (blank($reason)) {
            throw new InvalidArgumentException(__('admin.bank_transactions.errors.unassign_reason_required'));
        }

        return DB::transaction(function () use ($bankTxId, $admin, $reason) {
            $tx = BankTransaction::whereKey($bankTxId)->lockForUpdate()->firstOrFail();

            if ($tx->status !== BankTransaction::STATUS_CREDITED || ! $tx->user_id) {
                throw new DepositAlreadyProcessed;
            }

            $n = $tx->assignment_no;
            $user = User::findOrFail($tx->user_id);
            $before = $tx->only(['status', 'user_id', 'bonus_amount']);

            $this->wallet->debit($user, $tx->transfer_amount, TxType::TopupReversal, "sepay-reversal:{$tx->sepay_id}:{$n}", $tx, $admin, $reason);
            if ($tx->bonus_amount > 0) {
                $this->wallet->debit($user, $tx->bonus_amount, TxType::BonusReversal, "sepay-bonus-reversal:{$tx->sepay_id}:{$n}", $tx, $admin, $reason);
            }

            $tx->update(['status' => BankTransaction::STATUS_UNMATCHED, 'user_id' => null, 'credited_at' => null, 'bonus_amount' => 0, 'note' => $this->unassignNote($reason)]);
            AuditLog::record($admin, 'deposit.unassign', $tx, ['before' => $before, 'after' => $tx->only(['status', 'user_id']), 'reason' => $reason]);

            return $tx;
        });
    }

    /** Ghi chú huỷ gán vừa cột note (255 ký tự) kể cả khi lý do chứa ký tự bị escape trong JSON. */
    private function unassignNote(string $reason): string
    {
        $reason = mb_substr($reason, 0, 200);

        do {
            $note = BankTransaction::noteKey('deposit.notes.unassigned', ['reason' => $reason]);
            $reason = mb_substr($reason, 0, -10);
        } while (mb_strlen($note) > 255);

        return $note;
    }

    /** Đánh dấu bỏ qua giao dịch chưa khớp (không phải tiền nạp của khách). */
    public function ignore(int $bankTxId, User $admin, string $reason): BankTransaction
    {
        return DB::transaction(function () use ($bankTxId, $admin, $reason) {
            $tx = BankTransaction::whereKey($bankTxId)->lockForUpdate()->firstOrFail();

            if ($tx->status !== BankTransaction::STATUS_UNMATCHED) {
                throw new DepositAlreadyProcessed;
            }

            $tx->update(['status' => BankTransaction::STATUS_IGNORED, 'note' => mb_substr($reason, 0, 255)]);
            AuditLog::record($admin, 'deposit.ignore', $tx, ['before' => ['status' => 'unmatched'], 'after' => ['status' => 'ignored'], 'reason' => $reason]);

            return $tx;
        });
    }

    /**
     * Nhập tay 1 giao dịch (khi User API không khả dụng). Đi chung handleWebhook → trùng sepay_id thì bỏ qua.
     *
     * @param  array{sepay_id: int, amount: int, content: string, transaction_date: string, reference_code: string}  $data
     */
    public function importManual(array $data, User $admin): string
    {
        return DB::transaction(function () use ($data, $admin) {
            $result = $this->handleWebhook([
                'id' => (int) $data['sepay_id'],
                'transferType' => 'in',
                'transferAmount' => (int) $data['amount'],
                'content' => (string) $data['content'],
                'code' => null,
                'referenceCode' => $data['reference_code'] ?? null,
                'transactionDate' => $data['transaction_date'],
                'accountNumber' => app(DepositSettings::class)->account_number,
                'manual_by' => $admin->id,
            ], 'manual');

            $tx = BankTransaction::firstWhere('sepay_id', (int) $data['sepay_id']);
            AuditLog::record($admin, 'deposit.manual_import', $tx, ['result' => $result, 'data' => $data]);
            $this->notifier->send(__('telegram.deposit_manual_import', [
                'id' => $data['sepay_id'],
                'amount' => $data['amount'],
                'email' => $admin->email,
                'result' => $result,
            ], config('app.default_locale')));

            return $result;
        });
    }

    private function creditFor(BankTransaction $tx, User $user, ?User $admin): void
    {
        $n = $tx->assignment_no + 1;   // key mới mỗi lần gán → gán lại sau huỷ không đụng key cũ

        $this->wallet->credit($user, $tx->transfer_amount, TxType::Topup, "sepay:{$tx->sepay_id}:{$n}", $tx, actor: $admin);

        $bonus = intdiv($tx->transfer_amount * $tx->bonus_percent, 100);
        if ($bonus > 0) {
            $this->wallet->credit($user, $bonus, TxType::Bonus, "sepay-bonus:{$tx->sepay_id}:{$n}", $tx, actor: $admin);
        }

        $this->tiers->promoteIfEligible($user->id);

        $tx->update([
            'status' => BankTransaction::STATUS_CREDITED,
            'user_id' => $user->id,
            'assigned_by' => $admin?->id,
            'credited_at' => now(),
            'bonus_amount' => $bonus,
            'assignment_no' => $n,
        ]);
    }

    /**
     * Giao dịch khác (khác sepay_id) cùng mã tham chiếu + số tiền + loại. Khoá advisory theo mã tham chiếu
     * để 2 bản về song song (khác id) không cùng lọt qua bước kiểm này.
     */
    private function sameBankReference(BankTransaction $tx): ?BankTransaction
    {
        if (blank($tx->reference_code)) {
            return null;
        }

        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['bank-ref:'.$tx->reference_code]);

        return BankTransaction::where('reference_code', $tx->reference_code)
            ->where('transfer_amount', $tx->transfer_amount)
            ->where('transfer_type', $tx->transfer_type)
            ->where('id', '!=', $tx->id)
            ->first();
    }

    /** Có cấu hình STK → giao dịch phải về đúng STK (hoặc VA). */
    private function accountMatches(BankTransaction $tx): bool
    {
        $expected = trim((string) app(DepositSettings::class)->account_number);

        return $expected === '' || $tx->account_number === $expected || $tx->sub_account === $expected;
    }

    private function parseDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value, 'Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');
        } catch (Throwable) {
            Log::warning('SePay transactionDate không đọc được', ['value' => $value]);

            return null;
        }
    }

    private function str(mixed $value, int $max): ?string
    {
        return filled($value) ? mb_substr((string) $value, 0, $max) : null;
    }
}
