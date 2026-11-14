<?php

namespace App\Enums;

enum Tier: string
{
    case Member = 'member';
    case Agency = 'agency';

    public function label(): string
    {
        return __('enums.tier.'.$this->value);
    }

    public function color(): string
    {
        return $this === self::Agency ? 'success' : 'gray';
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
