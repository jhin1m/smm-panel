<div class="view" wire:poll.30s.visible>
    <div class="head">
        <div><h1>{{ __('orders.title') }}</h1><p>{{ __('orders.list.intro') }}</p></div>
        <a class="btn pri" href="{{ route('orders.create') }}" wire:navigate><x-svg-icon name="cart" /> {{ __('orders.list.new_order') }}</a>
    </div>
    <x-panel>
        @include('partials.order-table', ['orders' => $orders])
        @if ($orders->hasPages())
            <div>{{ $orders->links() }}</div>
        @endif
    </x-panel>
</div>
