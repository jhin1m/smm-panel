@props(['label', 'sans' => false])
<div {{ $attributes->class('stat') }}>
    <span class="k">{{ $label }}</span>
    <span @class(['v', 'sans' => $sans])>{{ $value }}</span>
    {{ $slot }}
</div>
