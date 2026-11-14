<x-layouts::guest title="{{ __('account.register.title') }}">
    <form class="panel" method="POST" action="{{ route('register.store') }}">
        @csrf
        @include('auth._tabs', ['active' => 'register'])
        @include('auth._messages')
        <label class="f">{{ __('account.register.name') }}<input class="inp" name="name" value="{{ old('name') }}" maxlength="50" autocomplete="nickname" required autofocus></label>
        <label class="f">{{ __('account.register.email') }}<input class="inp" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></label>
        <label class="f">{{ __('account.register.password') }}<input class="inp" name="password" type="password" autocomplete="new-password" minlength="8" required></label>
        <label class="f">{{ __('account.register.password_confirmation') }}<input class="inp" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></label>
        <button class="btn pri block" type="submit">{{ __('account.register.submit') }}</button>
    </form>
</x-layouts::guest>
