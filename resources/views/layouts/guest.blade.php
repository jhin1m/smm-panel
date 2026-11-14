@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · '.config('app.name') : (\App\Support\Branding::general()?->seo_title ?: config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-theme-head />
    @if (is_file(public_path('custom/custom.css')))
        <link rel="stylesheet" href="{{ asset('custom/custom.css') }}?v={{ filemtime(public_path('custom/custom.css')) }}">
    @endif
</head>
<body>
<div class="authwrap">
    <div class="authside">
        <a class="brand" href="{{ url('/') }}" style="padding:0"><x-brand-logo /> {{ config('app.name') }}</a>
        <h1>{{ __('common.guest.headline') }}</h1>
        <ul>
            <li>{{ __('common.guest.points.deposit') }}</li>
            <li>{{ __('common.guest.points.services') }}</li>
            <li>{{ __('common.guest.points.support') }}</li>
        </ul>
    </div>
    {{ $slot }}
    <x-locale-switcher style="position:fixed;top:12px;right:12px;z-index:10" />
</div>
<x-theme-body />
</body>
</html>
