<?php

namespace App\Filament\Support;

use App\Support\Money;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;

/** Cột tiền VND: mono + tabular-nums, định dạng 16.098đ. */
final class MoneyColumn
{
    public static function make(string $name, bool $signed = false): TextColumn
    {
        return TextColumn::make($name)
            ->formatStateUsing(fn ($state) => ($signed && $state > 0 ? '+' : '').Money::vnd((int) $state))
            ->fontFamily(FontFamily::Mono)
            ->extraAttributes(['class' => 'fi-money'])
            ->alignEnd();
    }
}
