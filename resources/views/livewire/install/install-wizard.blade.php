<div>
    <div class="panel">
        <h1 style="font-size:22px">{{ __('install.title') }}</h1>
        <p class="muted small">{{ __('install.step_of', ['step' => $step, 'total' => \App\Livewire\Install\InstallWizard::CONFIRM_STEP]) }} · {{ __("install.steps.{$step}") }}</p>

        @if ($step === 1)
            @if ($urlMismatch)
                <p class="small" role="alert">{{ __('install.url_mismatch', ['configured' => $urlMismatch['configured'], 'actual' => $urlMismatch['actual']]) }}</p>
            @endif

            @if (! $tokenConfigured)
                <p role="alert">{{ __('install.token.not_configured') }}</p>
            @else
                <p class="muted">{{ __('install.token.intro') }}</p>
                <form wire:submit="verifyToken" style="display:flex;flex-direction:column;gap:14px">
                    <label class="f">{{ __('install.fields.token') }}
                        <input class="inp" type="password" wire:model="token" autocomplete="off" required autofocus>
                    </label>
                    @error('token') <p class="err" role="alert">{{ $message }}</p> @enderror
                    <button class="btn pri block" type="submit">{{ __('install.actions.continue') }}</button>
                </form>
            @endif
        @elseif ($step === 2)
            <form wire:submit="next" style="display:flex;flex-direction:column;gap:14px">
                <label class="f">{{ __('install.fields.site_name') }}
                    <input class="inp" type="text" wire:model="site_name" maxlength="60" required>
                </label>
                @error('site_name') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.default_locale') }}
                    <select class="inp" wire:model="default_locale">
                        @foreach ($locales as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
                @error('default_locale') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.support_email') }}
                    <input class="inp" type="email" wire:model="support_email" maxlength="255">
                </label>
                @error('support_email') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.support_zalo') }}
                    <input class="inp" type="text" wire:model="support_zalo" maxlength="100">
                </label>
                @error('support_zalo') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.support_telegram') }}
                    <input class="inp" type="text" wire:model="support_telegram" maxlength="100">
                </label>
                @error('support_telegram') <p class="err" role="alert">{{ $message }}</p> @enderror
                <button class="btn pri block" type="submit">{{ __('install.actions.next') }}</button>
            </form>
        @elseif ($step === 3)
            <p class="muted">{{ __('install.admin.intro') }}</p>
            <form wire:submit="next" style="display:flex;flex-direction:column;gap:14px">
                <label class="f">{{ __('install.fields.admin_name') }}
                    <input class="inp" type="text" wire:model="admin_name" maxlength="50" autocomplete="name" required>
                </label>
                @error('admin_name') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.admin_email') }}
                    <input class="inp" type="email" wire:model="admin_email" maxlength="255" autocomplete="email" required>
                </label>
                @error('admin_email') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.admin_password') }}
                    <input class="inp" type="password" wire:model="admin_password" autocomplete="new-password" minlength="12" required>
                </label>
                @error('admin_password') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.admin_password_confirmation') }}
                    <input class="inp" type="password" wire:model="admin_password_confirmation" autocomplete="new-password" required>
                </label>
                @error('admin_password_confirmation') <p class="err" role="alert">{{ $message }}</p> @enderror
                <div style="display:flex;gap:10px">
                    <button class="btn" type="button" wire:click="back">{{ __('install.actions.back') }}</button>
                    <button class="btn pri" type="submit" style="flex:1">{{ __('install.actions.next') }}</button>
                </div>
            </form>
        @elseif ($step === 4)
            <p class="muted">{{ __('install.optional.intro') }}</p>
            <form wire:submit="next" style="display:flex;flex-direction:column;gap:14px">
                <h2 style="font-size:16px">{{ __('install.optional.bank') }}</h2>
                <label class="f">{{ __('install.fields.bank_bin') }}
                    <input class="inp" type="text" wire:model="bank_bin" inputmode="numeric" maxlength="6" placeholder="970422">
                </label>
                @error('bank_bin') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.bank_name') }}
                    <input class="inp" type="text" wire:model="bank_name" maxlength="100">
                </label>
                @error('bank_name') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.account_number') }}
                    <input class="inp" type="text" wire:model="account_number" maxlength="30">
                </label>
                @error('account_number') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.account_name') }}
                    <input class="inp" type="text" wire:model="account_name" maxlength="100">
                </label>
                @error('account_name') <p class="err" role="alert">{{ $message }}</p> @enderror

                <h2 style="font-size:16px">{{ __('install.optional.smtp') }}</h2>
                <label class="f">{{ __('install.fields.smtp_host') }}
                    <input class="inp" type="text" wire:model="smtp_host" maxlength="255" autocomplete="off">
                </label>
                @error('smtp_host') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.smtp_port') }}
                    <input class="inp" type="text" wire:model="smtp_port" inputmode="numeric" placeholder="587">
                </label>
                @error('smtp_port') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.smtp_encryption') }}
                    <select class="inp" wire:model="smtp_encryption">
                        <option value="tls">{{ __('install.optional.encryption.tls') }}</option>
                        <option value="ssl">{{ __('install.optional.encryption.ssl') }}</option>
                        <option value="none">{{ __('install.optional.encryption.none') }}</option>
                    </select>
                </label>
                @error('smtp_encryption') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.smtp_username') }}
                    <input class="inp" type="text" wire:model="smtp_username" maxlength="255" autocomplete="off">
                </label>
                @error('smtp_username') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.smtp_password') }}
                    <input class="inp" type="password" wire:model="smtp_password" maxlength="255" autocomplete="new-password">
                </label>
                @error('smtp_password') <p class="err" role="alert">{{ $message }}</p> @enderror
                <label class="f">{{ __('install.fields.mail_from_address') }}
                    <input class="inp" type="email" wire:model="mail_from_address" maxlength="255">
                </label>
                @error('mail_from_address') <p class="err" role="alert">{{ $message }}</p> @enderror

                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <button class="btn" type="button" wire:click="back">{{ __('install.actions.back') }}</button>
                    <button class="btn" type="button" wire:click="skipOptional">{{ __('install.actions.skip') }}</button>
                    <button class="btn pri" type="submit" style="flex:1">{{ __('install.actions.next') }}</button>
                </div>
            </form>
        @else
            <p class="muted">{{ __('install.confirm.intro') }}</p>
            <dl class="kv">
                <dt>{{ __('install.fields.site_name') }}</dt><dd>{{ $site_name }}</dd>
                <dt>{{ __('install.fields.default_locale') }}</dt><dd>{{ $locales[$default_locale] ?? $default_locale }}</dd>
                <dt>{{ __('install.fields.admin_email') }}</dt><dd>{{ $admin_email }}</dd>
                <dt>{{ __('install.optional.bank') }}</dt><dd>{{ filled($bank_bin) ? __('install.confirm.configured') : __('install.confirm.skipped') }}</dd>
                <dt>{{ __('install.optional.smtp') }}</dt><dd>{{ filled($smtp_host) ? __('install.confirm.configured') : __('install.confirm.skipped') }}</dd>
            </dl>
            <p class="small muted">{{ __('install.confirm.mfa_note') }}</p>
            @error('finish') <p class="err" role="alert">{{ $message }}</p> @enderror
            <div style="display:flex;gap:10px">
                <button class="btn" type="button" wire:click="back">{{ __('install.actions.back') }}</button>
                <button class="btn pri" type="button" wire:click="finish" wire:loading.attr="disabled" style="flex:1">{{ __('install.actions.finish') }}</button>
            </div>
        @endif
    </div>
</div>
