<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('common.maintenance.title') }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    <x-theme-head />
</head>
<body>
<main class="view" style="max-width:520px;margin:12vh auto;text-align:center">
    <x-brand-logo style="margin:0 auto 16px" />
    <h1>{{ __('common.maintenance.title') }}</h1>
    <p style="white-space:pre-line">{{ $message }}</p>
</main>
</body>
</html>
