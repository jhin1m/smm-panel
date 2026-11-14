@php use App\Support\Money; @endphp
<div class="view">
    <div class="head">
        <div><h1>{{ __('orders.place.title') }}</h1><p>{{ __('orders.place.intro') }}</p></div>
    </div>

    @if ($this->categories->isEmpty())
        <x-panel><p class="muted">{{ __('orders.place.no_services') }}</p></x-panel>
    @else
    <div class="g2">
        <div class="grid-auto" style="align-content:start">
            <x-panel :title="__('orders.place.platform')">
                <div class="chips">
                    @foreach ($this->categories as $cat)
                        <button type="button" class="chip" wire:click="selectCategory({{ $cat->id }})" wire:key="cat-{{ $cat->id }}"
                                aria-pressed="{{ $cat->id === $categoryId ? 'true' : 'false' }}">
                            <span class="pl" style="background:{{ $cat->color }}" aria-hidden="true">{{ $cat->badge }}</span>{{ $cat->name }}
                        </button>
                    @endforeach
                </div>
            </x-panel>
            <x-panel :title="__('orders.place.services_of', ['category' => $this->categories->firstWhere('id', $categoryId)?->name ?? ''])">
                <div class="svclist">
                    @forelse ($this->services as $svc)
                        <button type="button" class="svc" wire:click="selectService({{ $svc->id }})" wire:key="svc-{{ $svc->id }}"
                                aria-pressed="{{ $svc->id === $serviceId ? 'true' : 'false' }}">
                            <span class="id">{{ $svc->id }}</span>
                            <span class="nm">{{ $svc->name }}</span>
                            <span class="pr">{{ Money::vnd($svc->price) }}<small>{{ $svc->unit->priceSuffix() }}</small></span>
                            <span class="meta">
                                @if ($svc->speed) {{ __('orders.place.speed', ['speed' => $svc->speed]) }} · @endif
                                @if ($svc->start_time) {{ __('orders.place.start_after', ['time' => $svc->start_time]) }} · @endif
                                {{ Money::number($svc->min_quantity) }}–{{ Money::number($svc->max_quantity) }}
                                @if ($svc->description)<br>{{ \Illuminate\Support\Str::limit($svc->description, 140) }}@endif
                            </span>
                        </button>
                    @empty
                        <p class="muted">{{ __('orders.place.category_paused') }}</p>
                    @endforelse
                </div>
            </x-panel>
        </div>

        <form class="panel sum" wire:submit="submit" novalidate>
            <h2>{{ __('orders.place.order_info') }}</h2>
            @error('service')<p class="err">{{ $message }}</p>@enderror
            @php $svc = $this->service; @endphp
            <label class="f">{{ __('orders.place.link') }}
                <input class="inp" type="url" wire:model="link" placeholder="https://..." maxlength="500" autocomplete="off">
            </label>
            @error('link')<p class="err">{{ $message }}</p>@enderror
            <label class="f">{{ __('orders.place.quantity') }} @if ($svc)({{ Money::number($svc->min_quantity) }}–{{ Money::number($svc->max_quantity) }})@endif
                <input class="inp num" type="number" wire:model.live.debounce.300ms="quantity"
                       @if ($svc) min="{{ $svc->min_quantity }}" max="{{ $svc->max_quantity }}" @endif step="1" inputmode="numeric">
            </label>
            @error('quantity')<p class="err">{{ $message }}</p>@enderror
            <label class="f">{{ __('orders.place.note') }}
                <textarea class="inp" wire:model="note" maxlength="500" placeholder="{{ __('orders.place.note_placeholder') }}"></textarea>
            </label>
            @error('note')<p class="err">{{ $message }}</p>@enderror

            @if ($svc)
                @php $after = $balance - $this->total; @endphp
                <div style="display:flex;flex-direction:column;gap:8px">
                    <div class="rowkv"><span class="muted">{{ __('orders.place.unit_price') }}</span><b>{{ Money::vnd($svc->price) }} {{ $svc->unit->priceSuffix() }}</b></div>
                    @if ($this->discountPercent > 0)
                        <div class="rowkv"><span class="muted">{{ __('orders.place.tier_discount') }}</span><b class="up">−{{ $this->discountPercent }}%</b></div>
                    @endif
                    <div class="rowkv"><span class="muted">{{ __('orders.place.quantity') }}</span><b>{{ Money::number((int) filter_var($quantity, FILTER_VALIDATE_INT)) }}</b></div>
                    <div class="rowkv total"><span>{{ __('orders.place.total') }}</span><b>{{ Money::vnd($this->total) }}</b></div>
                    <div class="rowkv"><span class="muted">{{ __('orders.place.balance_after') }}</span><b style="color:var(--color-{{ $after < 0 ? 'bad' : 'fg' }})">{{ Money::vnd($after) }}</b></div>
                    @if ($after < 0)
                        <p class="err">{{ __('orders.place.shortfall', ['amount' => Money::vnd(-$after)]) }} @if (Route::has('deposit'))<a class="link" href="{{ route('deposit') }}" wire:navigate>{{ __('orders.place.top_up_more') }}</a>@endif</p>
                    @endif
                </div>
            @endif
            @error('balance')<p class="err">{{ $message }}</p>@enderror

            <button class="btn pri block" type="submit" wire:loading.attr="disabled" @disabled(! $svc)>
                <span wire:loading.remove wire:target="submit">{{ __('orders.place.submit') }}</span>
                <span wire:loading wire:target="submit">{{ __('orders.place.submitting') }}</span>
            </button>
        </form>
    </div>
    @endif
</div>
