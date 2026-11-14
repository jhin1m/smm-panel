<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Tài khoản nhận tiền + khuyến mãi nạp. */
class DepositSettings extends Settings
{
    public ?string $bank_bin;          // mã BIN VietQR, vd 970422 (MB)

    public ?string $bank_name;

    public ?string $account_number;

    public ?string $account_name;      // in hoa không dấu

    /** Mỗi phần tử: ['min_amount' => int, 'percent' => int]. */
    public array $bonus_rules;

    public static function group(): string
    {
        return 'deposit';
    }

    public function isConfigured(): bool
    {
        return filled($this->bank_bin) && filled($this->account_number) && filled($this->account_name);
    }

    /** % khuyến mãi cao nhất trong các mức có min_amount ≤ số tiền. */
    public function bonusPercentFor(int $amount): int
    {
        return (int) collect($this->bonus_rules)
            ->filter(fn ($r) => (int) ($r['min_amount'] ?? 0) > 0 && (int) $r['min_amount'] <= $amount)
            ->max(fn ($r) => (int) ($r['percent'] ?? 0));
    }
}
