@php($logo = \App\Support\Branding::logoUrl())
@if ($logo)
    <img src="{{ $logo }}" alt="" width="28" height="28" style="object-fit:contain" {{ $attributes }}>
@else
    <svg width="28" height="28" viewBox="0 0 28 28" aria-hidden="true" {{ $attributes }}><rect width="28" height="28" rx="8" fill="var(--color-accent)"/><path d="M14 5.5c3 2.6 4.2 6 3.6 10.2H10.4C9.8 11.5 11 8.1 14 5.5Z" fill="var(--color-accent-fg)"/><path d="M10.2 17.5h7.6l1.8 3.3H8.4Z" fill="var(--color-accent-fg)" opacity=".7"/></svg>
@endif
