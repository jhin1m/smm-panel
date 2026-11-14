@props(['color' => 'accent'])
{{-- color: warn | accent | ok | bad --}}
<span {{ $attributes->class(['tag', $color]) }}>{{ $slot }}</span>
