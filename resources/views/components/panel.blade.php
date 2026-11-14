@props(['title' => null, 'tag' => 'section'])
<{{ $tag }} {{ $attributes->class('panel') }}>
    @if ($title || isset($actions))
        <div class="panel-h">
            @if ($title)<h2>{{ $title }}</h2>@endif
            {{ $actions ?? '' }}
        </div>
    @endif
    {{ $slot }}
</{{ $tag }}>
