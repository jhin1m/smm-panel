{{-- HTML cuối body do admin cấu hình; chỉ render cho khách (không phải admin) --}}
@php($custom = \App\Support\Branding::appearanceForViewer())
@if (filled($custom?->custom_body_html))
    {!! $custom->custom_body_html !!}
@endif
