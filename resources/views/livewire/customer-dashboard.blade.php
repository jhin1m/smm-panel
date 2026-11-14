@php use App\Support\Money; @endphp
<div class="view">
    <div class="head">
        <div><h1>{{ __('dashboard.greeting', ['name' => $user->name]) }}</h1><p>{{ __('dashboard.subtitle') }}</p></div>
        <a class="btn pri" href="{{ route('orders.create') }}" wire:navigate><x-svg-icon name="cart" /> {{ __('dashboard.new_order') }}</a>
    </div>

    <div class="g4">
        <x-stat :label="__('dashboard.balance')">
            <x-slot:value>{{ Money::vnd($user->balance) }}</x-slot:value>
            <a class="link small" href="{{ route('deposit') }}" wire:navigate>{{ __('dashboard.top_up') }}</a>
        </x-stat>
        <x-stat :label="__('dashboard.total_deposited')">
            <x-slot:value>{{ Money::vnd($user->total_deposited) }}</x-slot:value>
            @unless ($isAgency)
                <span class="d">{{ __('dashboard.remaining_to_agency', ['amount' => Money::vnd($remainingToAgency)]) }}</span>
            @endunless
        </x-stat>
        <x-stat :label="__('dashboard.active_orders')">
            <x-slot:value>{{ $activeOrders }}</x-slot:value>
            <span class="d">{{ __('dashboard.orders_total', ['count' => $totalOrders]) }}</span>
        </x-stat>
        <x-stat :label="__('dashboard.tier')" :sans="true">
            <x-slot:value>{{ $user->tier->label() }}</x-slot:value>
            <span class="d">
                @if ($isAgency) {{ __('dashboard.discount_active', ['percent' => $discount]) }}
                @else {{ __('dashboard.discount_agency', ['percent' => $agencyDiscount]) }} @endif
            </span>
        </x-stat>
    </div>

    <div class="g2">
        <x-panel :title="__('dashboard.recent_orders')">
            <x-slot:actions><a class="link" href="{{ route('orders.index') }}" wire:navigate>{{ __('dashboard.view_all') }}</a></x-slot:actions>
            @include('partials.order-table', ['orders' => $recentOrders])
        </x-panel>
        <x-panel :title="__('dashboard.notices')">
            @forelse ($notices as $n)
                <div class="notice">
                    <b>{{ $n->title }}</b>
                    <p class="muted small">{!! nl2br(e($n->body)) !!}</p>
                    <time>{{ $n->published_at->format('d/m/Y') }}</time>
                </div>
            @empty
                <p class="muted small">{{ __('dashboard.no_notices') }}</p>
            @endforelse
        </x-panel>
    </div>
</div>
