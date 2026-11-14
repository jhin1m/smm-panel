{{-- Bảng đơn của khách (dùng ở trang đơn hàng + tổng quan). $orders: iterable<Order> --}}
@php use App\Support\Money; @endphp
<div class="scroll">
    <table class="tbl">
        <thead>
        <tr><th>{{ __('orders.table.id') }}</th><th>{{ __('orders.table.service') }}</th><th class="r">{{ __('orders.table.progress') }}</th><th class="r">{{ __('orders.table.total') }}</th><th>{{ __('orders.table.status') }}</th><th>{{ __('orders.table.time') }}</th></tr>
        </thead>
        <tbody>
        @forelse ($orders as $o)
            <tr wire:key="order-{{ $o->id }}">
                <td class="num">#{{ $o->id }}</td>
                <td>
                    <b>{{ $o->service_name }}</b>
                    <div class="small muted trunc" title="{{ $o->link }}">{{ $o->link }}</div>
                </td>
                <td class="r num">
                    {{ Money::number($o->done) }} / {{ Money::number($o->quantity) }}
                    <div class="bar"><i style="width:{{ $o->progressPercent() }}%"></i></div>
                </td>
                <td class="r num">
                    {{ Money::vnd($o->total) }}
                    @if ($o->refunded_amount)
                        <div class="small up">{{ __('orders.table.refunded', ['amount' => Money::vnd($o->refunded_amount)]) }}</div>
                    @endif
                </td>
                <td><x-tag :color="$o->status->tag()">{{ $o->status->label() }}</x-tag></td>
                <td class="num small muted">{{ $o->created_at->format('d/m H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">{{ __('orders.table.empty') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
