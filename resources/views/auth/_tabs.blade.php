{{-- Segmented đăng nhập / đăng ký + nút Google (dùng chung 2 màn) --}}
@php
    $access = \App\Support\Branding::access();
    $canRegister = $access?->registration_enabled ?? true;
    $canGoogle = $access?->google_login_enabled ?? true;
@endphp
@if ($canRegister)
    <div class="seg" style="align-self:start">
        <a href="{{ route('login') }}" @if ($active === 'login') aria-current="page" @endif>{{ __('account.login.title') }}</a>
        <a href="{{ route('register') }}" @if ($active === 'register') aria-current="page" @endif>{{ __('account.register.title') }}</a>
    </div>
@endif
@if ($canGoogle)
    <a class="btn block" href="{{ route('auth.google.redirect') }}">{{ __('account.tabs.google') }}</a>
    <div class="or">{{ __('account.tabs.or') }}</div>
@endif
