<x-layouts::guest title="{{ __('account.reset.title') }}">
    <form class="panel" method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <h2>{{ __('account.reset.title') }}</h2>
        <p class="muted small">{{ __('account.reset.intro') }}</p>
        @include('auth._messages')
        <label class="f">{{ __('account.reset.email') }}<input class="inp" name="email" type="email" value="{{ old('email', $request->email) }}" autocomplete="email" required></label>
        <label class="f">{{ __('account.reset.password') }}<input class="inp" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus></label>
        <label class="f">{{ __('account.reset.password_confirmation') }}<input class="inp" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></label>
        <button class="btn pri block" type="submit">{{ __('account.reset.submit') }}</button>
    </form>
</x-layouts::guest>
