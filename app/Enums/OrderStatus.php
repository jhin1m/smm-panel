<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return __('enums.order_status.'.$this->value);
    }

    /** Màu tag trang khách: warn | accent | ok | bad. */
    public function tag(): string
    {
        return match ($this) {
            self::Pending => 'warn',
            self::Processing => 'accent',
            self::Completed => 'ok',
            self::Refunded => 'bad',
        };
    }

    /** Màu badge Filament. */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Processing => 'info',
            self::Completed => 'success',
            self::Refunded => 'danger',
        };
    }

    /**
     * Bảng chuyển trạng thái. refunded là trạng thái cuối duy nhất; completed chỉ còn đường sang refunded.
     */
    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Pending => in_array($to, [self::Processing, self::Completed, self::Refunded], true),
            self::Processing => in_array($to, [self::Completed, self::Refunded], true),
            self::Completed => $to === self::Refunded,
            self::Refunded => false,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
