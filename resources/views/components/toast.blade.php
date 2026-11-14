{{-- Toast đáy giữa, tự ẩn sau 2.6s. Kích hoạt: window event "toast" {message} (Livewire $this->dispatch('toast', message: ...)) hoặc flash session('toast'). --}}
<div x-data="{ show: false, message: '', timer: null,
        open(m) { this.message = m; this.show = true; clearTimeout(this.timer); this.timer = setTimeout(() => this.show = false, 2600) } }"
     x-init="@if (session('toast')) open(@js(session('toast'))) @endif"
     x-on:toast.window="open($event.detail.message ?? $event.detail[0]?.message ?? '')">
    <div class="toast" role="status" aria-live="polite" x-show="show" x-cloak x-text="message"></div>
</div>
