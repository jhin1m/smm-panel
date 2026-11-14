<x-layouts::guest title="{{ __('account.forgot.title') }}">
    <form class="panel" method="POST" action="{{ route('password.email') }}">
        @csrf
        <h2>{{ __('account.forgot.title') }}</h2>
        <p class="muted small">{{ __('account.forgot.intro') }}</p>
        @include('auth._messages')
        <label class="f">{{ __('account.forgot.email') }}<input class="inp" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
        <button class="btn pri block" type="submit">{{ __('account.forgot.submit') }}</button>
        <a class="link" href="{{ route('login') }}">{{ __('account.forgot.back') }}</a>
    </form>
</x-layouts::guest>
