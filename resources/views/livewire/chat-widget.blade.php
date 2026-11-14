<div x-data x-on:keydown.escape.window="if ($wire.open) $wire.toggle()">
    {{-- Poll: mở 5s / đóng 20s (badge); khoá div theo trạng thái để Livewire khởi tạo lại poll --}}
    @if ($open)
        <div wire:key="poll-open" wire:poll.5s.visible="refreshIfNew"></div>
    @else
        <div wire:key="poll-closed" wire:poll.20s.visible="refreshIfNew"></div>
    @endif

    @if ($open)
        <div class="chat-box" role="dialog" aria-label="{{ __('chat.dialog_label') }}" aria-modal="false">
            <div class="box-h">
                <b><i class="dot"></i>{{ __('chat.admin') }}</b>
                <span class="small muted">{{ __('chat.hint') }}</span>
            </div>
            <div class="msgs" x-init="$el.scrollTop = $el.scrollHeight" x-effect="$wire.lastSeenId; $nextTick(() => $el.scrollTop = $el.scrollHeight)">
                @forelse ($messages as $m)
                    <div wire:key="cm-{{ $m->id }}" @class(['msg', 'me' => $m->sender_type === 'customer'])>
                        {!! nl2br(e($m->body)) !!}
                        <time>{{ $m->created_at->format('H:i d/m') }}</time>
                    </div>
                @empty
                    <p class="small muted">{{ __('chat.empty') }}</p>
                @endforelse
            </div>
            @error('body')<p class="err" style="padding:0 16px">{{ $message }}</p>@enderror
            <form class="send" wire:submit="send">
                <input class="inp" wire:model="body" placeholder="{{ __('chat.placeholder') }}" autocomplete="off" maxlength="2000" aria-label="{{ __('chat.message_label') }}" x-init="$el.focus()">
                <button class="btn pri" type="submit" wire:loading.attr="disabled" wire:target="send">{{ __('chat.send') }}</button>
            </form>
        </div>
    @endif

    <button type="button" class="chat-fab" wire:click="toggle" aria-expanded="{{ $open ? 'true' : 'false' }}">
        <x-svg-icon name="chat" /> {{ $open ? __('chat.close') : __('chat.open') }}
        @if ($unread > 0)<span class="badge">{{ $unread }}</span>@endif
    </button>
</div>
