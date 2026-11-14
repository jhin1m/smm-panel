@props(['amount', 'signed' => false])
<span {{ $attributes->class('num') }}>{{ $signed && $amount > 0 ? '+' : '' }}{{ \App\Support\Money::vnd((int) $amount) }}</span>
