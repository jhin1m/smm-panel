<div class="view">
    <div class="head">
        <div><h1>{{ __('security.title') }}</h1><p>{{ __('security.intro') }}</p></div>
        <span class="pill">{{ __('security.status.'.($enabled ? 'enabled' : ($pending && $canEnable ? 'pending' : 'off'))) }}</span>
    </div>

    @if ($enabled)
        @if ($recoveryCodes)
            <x-panel :title="__('security.recovery.title')">
                <p class="small muted">{{ __('security.recovery.hint') }}</p>
                <ul class="num" style="columns:2;list-style:none;padding:0;margin:12px 0">
                    @foreach ($recoveryCodes as $recovery)
                        <li>{{ $recovery }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn pri" wire:click="dismissRecoveryCodes">{{ __('security.recovery.saved') }}</button>
            </x-panel>
        @endif

        <x-panel :title="__('security.manage.title')">
            <p class="small muted">{{ __('security.manage.hint') }}</p>
            <label class="f" style="max-width:320px">{{ __('security.manage.code') }}
                <input class="inp num" type="text" wire:model="code" autocomplete="one-time-code" inputmode="text">
                @error('code')<span class="flash bad" role="alert">{{ $message }}</span>@enderror
            </label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                <button type="button" class="btn" wire:click="regenerateRecoveryCodes">{{ __('security.manage.regenerate') }}</button>
                <button type="button" class="btn" wire:click="disable">{{ __('security.manage.disable') }}</button>
            </div>
        </x-panel>
    @elseif ($pending && $canEnable)
        <x-panel :title="__('security.enable')">
            <p class="small muted">{{ __('security.setup.scan') }}</p>
            <div class="qr" role="img" aria-label="{{ __('security.setup.qr_alt') }}">{!! $qrSvg !!}</div>
            <p class="small">{{ __('security.setup.secret') }} <span class="num">{{ $secret }}</span></p>
            <form wire:submit="confirm">
                <label class="f" style="max-width:240px">{{ __('security.setup.code') }}
                    <input class="inp num" type="text" wire:model="code" autocomplete="one-time-code" inputmode="numeric" maxlength="10">
                    @error('code')<span class="flash bad" role="alert">{{ $message }}</span>@enderror
                </label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                    <button type="submit" class="btn pri">{{ __('security.setup.confirm') }}</button>
                    <button type="button" class="btn" wire:click="enable">{{ __('security.regenerate_qr') }}</button>
                    <button type="button" class="btn" wire:click="cancelSetup">{{ __('security.cancel_setup') }}</button>
                </div>
            </form>
        </x-panel>
    @else
        <x-panel>
            @if ($canEnable)
                <button type="button" class="btn pri" wire:click="enable">{{ __('security.enable') }}</button>
            @else
                <p class="muted">{{ __('security.feature_off') }}</p>
                @if ($pending)
                    <button type="button" class="btn" wire:click="cancelSetup">{{ __('security.cancel_setup') }}</button>
                @endif
            @endif
        </x-panel>
    @endif
</div>
