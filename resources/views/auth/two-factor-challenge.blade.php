<x-layouts::guest title="{{ __('security.challenge.title') }}">
    <form class="panel" method="POST" action="{{ route('two-factor.login.store') }}">
        @csrf
        <h1>{{ __('security.challenge.title') }}</h1>
        @include('auth._messages')
        <p class="small muted">{{ __('security.challenge.intro') }}</p>
        <label class="f">{{ __('security.challenge.code') }}<input class="inp num" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus></label>
        {{-- Trang khách không nạp JS: dùng <details> để chọn mã khôi phục. --}}
        <details @if ($errors->has('recovery_code')) open @endif>
            <summary class="link">{{ __('security.challenge.use_recovery') }}</summary>
            <p class="small muted">{{ __('security.challenge.recovery_intro') }}</p>
            <label class="f">{{ __('security.challenge.recovery_code') }}<input class="inp num" name="recovery_code" type="text" autocomplete="off"></label>
        </details>
        <button class="btn pri block" type="submit">{{ __('security.challenge.submit') }}</button>
        <a class="link" href="{{ route('login') }}">{{ __('security.challenge.back') }}</a>
    </form>
</x-layouts::guest>
