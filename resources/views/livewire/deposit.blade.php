@php use App\Support\Money; @endphp
<div class="view" @if ($configured) wire:poll.10s.visible="checkBalance" @endif>
    <div class="head">
        <div><h1>{{ __('deposit.title') }}</h1><p>{{ __('deposit.intro') }}</p></div>
        <span class="pill num">{{ __('deposit.balance', ['amount' => Money::vnd($lastBalance)]) }}</span>
    </div>

    @if (! $configured)
        <x-panel><p class="muted">{{ __('deposit.not_open') }}</p></x-panel>
    @else
    <div class="g2">
        <x-panel>
            <h2>{{ __('deposit.amount') }}</h2>
            <div class="chips">
                @foreach (\App\Livewire\Deposit::PRESETS as $preset)
                    <button type="button" class="chip plain num" wire:click="pick({{ $preset }})" aria-pressed="{{ (int) $amount === $preset ? 'true' : 'false' }}">{{ Money::number($preset) }}</button>
                @endforeach
            </div>
            <label class="f" style="max-width:240px">{{ __('deposit.custom_amount') }}
                <input class="inp num" type="number" min="{{ \App\Livewire\Deposit::MIN_AMOUNT }}" step="10000" wire:model.live.debounce.400ms="amount" inputmode="numeric">
            </label>
            @if (! $validAmount)
                <p class="small muted">{{ __('deposit.min_hint', ['amount' => Money::vnd(\App\Livewire\Deposit::MIN_AMOUNT)]) }}</p>
            @endif

            <div class="qrbox">
                <div class="qr"><img src="{{ $qrUrl }}" alt="{{ __('deposit.qr_alt', ['code' => $code]) }}" width="180" height="180" loading="lazy"></div>
                <div style="display:flex;flex-direction:column;gap:14px;min-width:0">
                    <dl class="kv">
                        <dt>{{ __('deposit.bank') }}</dt><dd>{{ $settings->bank_name ?: $settings->bank_bin }}</dd>
                        <dt>{{ __('deposit.account_number') }}</dt>
                        <dd class="num">{{ $settings->account_number }} <button type="button" class="btn sm" onclick="copyText(@js($settings->account_number))">{{ __('common.copy') }}</button></dd>
                        <dt>{{ __('deposit.account_name') }}</dt><dd>{{ $settings->account_name }}</dd>
                        <dt>{{ __('deposit.amount') }}</dt><dd class="num">{{ $validAmount ? Money::vnd($validAmount) : __('deposit.any_amount') }}</dd>
                        <dt>{{ __('deposit.content') }}</dt>
                        <dd class="num">{{ $code }} <button type="button" class="btn sm" onclick="copyText(@js($code))">{{ __('common.copy') }}</button></dd>
                        @if ($bonus > 0)
                            <dt>{{ __('deposit.bonus') }}</dt><dd class="num up">+{{ Money::vnd($bonus) }}</dd>
                        @endif
                    </dl>
                    <div class="wait"><i class="pulse"></i>{{ __('deposit.waiting') }}</div>
                    <p class="small muted">{!! __('deposit.note', ['code' => '<b class="num">'.e($code).'</b>']) !!}</p>
                </div>
            </div>
        </x-panel>

        <x-panel :title="__('deposit.history')">
            <div class="scroll">
                <table class="tbl">
                    <thead><tr><th>{{ __('deposit.time') }}</th><th>{{ __('deposit.type') }}</th><th class="r">{{ __('deposit.amount') }}</th></tr></thead>
                    <tbody>
                    @forelse ($history as $tx)
                        <tr wire:key="tx-{{ $tx->id }}">
                            <td class="num small">{{ $tx->created_at->format('d/m H:i') }}</td>
                            <td>{{ $tx->type->label() }}</td>
                            <td class="r num {{ $tx->amount > 0 ? 'up' : '' }}">{{ $tx->amount > 0 ? '+' : '' }}{{ Money::vnd($tx->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">{{ __('deposit.empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
    </div>
    @endif
</div>
