<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? __('install.title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<div class="authwrap" style="max-width:640px;grid-template-columns:1fr">
    {{ $slot }}
    <x-locale-switcher style="position:fixed;top:12px;right:12px;z-index:10" />
</div>
@livewireScripts
</body>
</html>
