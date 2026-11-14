<?php

namespace App\Enums;

use InvalidArgumentException;

/** Loại bút toán sổ cái. Dấu số tiền cố định theo loại (adjust: ±). */
enum TxType: string
{
    case Topup = 'topup';
    case Bonus = 'bonus';
    case Order = 'order';
    case Refund = 'refund';
    case Adjust = 'adjust';
    case TopupReversal = 'topup_reversal';
    case BonusReversal = 'bonus_reversal';

    public function label(): string
    {
        return __('enums.tx_type.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Topup, self::Bonus, self::Refund => 'success',
            self::Order => 'info',
            self::Adjust => 'warning',
            self::TopupReversal, self::BonusReversal => 'danger',
        };
    }

    /** Ném InvalidArgumentException nếu số tiền = 0 hoặc sai dấu theo loại. */
    public function assertSign(int $amount): void
    {
        $ok = match ($this) {
            self::Topup, self::Bonus, self::Refund => $amount > 0,
            self::Order, self::TopupReversal, self::BonusReversal => $amount < 0,
            self::Adjust => $amount !== 0,
        };

        if (! $ok) {
            throw new InvalidArgumentException(__('exceptions.invalid_tx_amount', ['amount' => $amount, 'type' => $this->value]));
        }
    }

    /** Loại làm thay đổi tổng nạp tích luỹ. */
    public function affectsTotalDeposited(): bool
    {
        return $this === self::Topup || $this === self::TopupReversal;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
