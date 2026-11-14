@php $o = $getRecord(); @endphp
<div style="min-width:110px;text-align:right" class="px-3 py-2">
    <div style="font-family:var(--mono-font-family, 'IBM Plex Mono', monospace);font-variant-numeric:tabular-nums;font-size:13px">
        {{ number_format($o->done, 0, ',', '.') }} / {{ number_format($o->quantity, 0, ',', '.') }}
    </div>
    <div style="height:6px;border-radius:99px;background:#e9edf6;overflow:hidden;margin-top:4px">
        <i style="display:block;height:100%;width:{{ $o->progressPercent() }}%;background:#1f47e6;border-radius:99px"></i>
    </div>
</div>
