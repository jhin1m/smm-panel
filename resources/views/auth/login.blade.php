<x-layouts::guest title="{{ __('account.login.title') }}">
    <form class="panel" method="POST" action="{{ route('login.store') }}">
        @csrf
        @include('auth._tabs', ['active' => 'login'])
        @include('auth._messages')
        <label class="f">{{ __('account.login.email') }}<input class="inp" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
        <label class="f">{{ __('account.login.password') }}<input class="inp" name="password" type="password" autocomplete="current-password" required></label>
        <label style="display:flex;gap:8px;align-items:center;font-size:13px"><input type="checkbox" name="remember" value="1"> {{ __('account.login.remember') }}</label>
        <a class="link" href="{{ route('password.request') }}" style="align-self:end">{{ __('account.login.forgot') }}</a>
        <button class="btn pri block" type="submit">{{ __('account.login.title') }}</button>
    </form>
</x-layouts::guest>
