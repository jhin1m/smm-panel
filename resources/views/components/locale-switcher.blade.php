{{-- Chọn ngôn ngữ: POST /locale, đổi xong về lại trang trước. Chỉ hiện khi có ≥ 2 ngôn ngữ. --}}
@php $locales = \App\Support\Locales::all(); @endphp
@if (count($locales) > 1)
    <form method="POST" action="{{ route('locale.update') }}" {{ $attributes->class('locale-switcher') }}>
        @csrf
        <select class="inp" name="locale" aria-label="{{ __('common.locale.label') }}" onchange="this.form.submit()">
            @foreach ($locales as $code => $name)
                <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $name }}</option>
            @endforeach
        </select>
        <noscript><button class="btn sm" type="submit">{{ __('common.locale.apply') }}</button></noscript>
    </form>
@endif
