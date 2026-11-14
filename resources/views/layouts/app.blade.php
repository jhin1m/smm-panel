@props(['title' => null])
@php
    // Nav khách: chỉ hiện mục có route (thêm dần theo tính năng, không link chết).
    $navItems = collect([
        ['home', __('common.nav.overview'), 'home'],
        ['orders.create', __('common.nav.place_order'), 'cart'],
        ['orders.index', __('common.nav.orders'), 'list'],
        ['deposit', __('common.nav.deposit'), 'wallet'],
        ['security', __('common.nav.security'), 'shield'],
    ])->filter(fn ($i) => Route::has($i[0]) && ($i[0] !== 'security' || ! auth()->user()?->is_admin));
    $user = auth()->user();
@endphp
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
    @livewireStyles
</head>
<body>
<div class="app">
    <aside class="side">
        <a class="brand" href="{{ Route::has('home') ? route('home') : url('/') }}" aria-label="{{ config('app.name') }}">
            <x-brand-logo /> {{ config('app.name') }}
        </a>
        <div>
            <div class="navlabel">{{ __('common.nav.services') }}</div>
            <nav class="nav" aria-label="{{ __('common.nav.aria') }}">
                @foreach ($navItems as [$routeName, $label, $icon])
                    <a class="navitem" href="{{ route($routeName) }}" @if (request()->routeIs($routeName)) aria-current="page" @endif wire:navigate>
                        <x-svg-icon :name="$icon" /> {{ $label }}
                    </a>
                @endforeach
                @if ($user && Route::has('logout'))
                    <form method="POST" action="{{ route('logout') }}" style="display:contents">
                        @csrf
                        <button type="submit" class="navitem"><x-svg-icon name="out" /> {{ __('common.nav.logout') }}</button>
                    </form>
                @endif
            </nav>
        </div>
        <div class="side-foot">
            <span><i class="dot"></i>{{ __('common.system_ok') }}</span>
        </div>
    </aside>
    <div class="main">
        <header class="top">
            <span class="sp"></span>
            <x-locale-switcher />
            @if ($user && isset($user->balance) && Route::has('deposit'))
                <a class="pill num" href="{{ route('deposit') }}" wire:navigate><x-svg-icon name="wallet" />{{ \App\Support\Money::vnd((int) $user->balance) }}</a>
            @endif
            @if ($user)
                <span class="avatar" title="{{ $user->name }}">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            @endif
        </header>
        <main class="view">
            {{ $slot }}
        </main>
    </div>
</div>
@if ($user && ! $user->is_admin && class_exists(\App\Livewire\ChatWidget::class))
    <livewire:chat-widget />
@endif
<x-toast />
@livewireScripts
<x-theme-body />
</body>
</html>
