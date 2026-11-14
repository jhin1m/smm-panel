{{-- Màu chính, favicon, SEO + custom CSS/HTML đầu trang (custom_* bỏ qua khi admin đang xem) --}}
@php
    $brand = \App\Support\Branding::class;
    $general = $brand::general();
    $custom = $brand::appearanceForViewer();
    $favicon = $brand::faviconUrl();
@endphp
@if ($favicon)
    <link rel="icon" href="{{ $favicon }}">
@endif
@if (filled($general?->seo_description))
    <meta name="description" content="{{ $general->seo_description }}">
@endif
<style>:root{--color-accent:{{ $brand::primaryColor() }};--color-accent-fg:{{ $brand::onPrimaryColor() }};--color-accent-soft:{{ $brand::softColor() }}}</style>
@if (filled($custom?->custom_css))
    <style>{!! str_ireplace('</style', '<\/style', $custom->custom_css) !!}</style>
@endif
@if (filled($custom?->custom_head_html))
    {!! $custom->custom_head_html !!}
@endif
