<?php

namespace App\Enums;

enum ServiceUnit: string
{
    case Per1000 = 'per_1000';
    case PerUnit = 'per_unit';

    public function label(): string
    {
        return __('enums.service_unit.'.$this->value);
    }

    /** Hậu tố giá hiển thị: "/ 1.000" hoặc "/ đơn vị". */
    public function priceSuffix(): string
    {
        return __('enums.price_suffix.'.$this->value);
    }

    public function divisor(): int
    {
        return $this === self::Per1000 ? 1000 : 1;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $u) => [$u->value => $u->label()])->all();
    }
}
