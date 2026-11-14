<x-filament-panels::page>
    <div wire:poll.5s.visible="refreshIfNew"
         class="grid min-h-[480px] overflow-hidden rounded-xl bg-white ring-1 ring-gray-200 md:grid-cols-[280px_minmax(0,1fr)]">
        <div class="flex max-h-[640px] flex-col overflow-y-auto border-b border-gray-200 md:border-b-0 md:border-r">
            @forelse ($threads as $t)
                @php $unread = $unreadCounts[$t->id] ?? 0; $last = $lastMessages[$t->id] ?? null; @endphp
                <button type="button" wire:key="th-{{ $t->id }}" wire:click="selectThread({{ $t->id }})"
                        aria-pressed="{{ $t->id === $threadId ? 'true' : 'false' }}"
                        @class(['flex flex-col gap-0.5 border-b border-gray-200 px-4 py-3 text-left', 'bg-primary-50' => $t->id === $threadId])>
                    <span class="flex items-center gap-2 font-semibold">
                        {{ $t->user?->name }}
                        @if ($unread > 0)<span class="ml-auto rounded-full bg-danger-600 px-2 text-xs font-semibold text-white">{{ $unread }}</span>@endif
                    </span>
                    <span class="truncate text-xs text-gray-500">
                        @if ($last){{ $last->sender_type === 'admin' ? __('admin.chat.you') : '' }}{{ \Illuminate\Support\Str::limit($last->body, 60) }}@endif
                    </span>
                </button>
            @empty
                <p class="p-4 text-sm text-gray-500">{{ __('admin.chat.empty') }}</p>
            @endforelse
        </div>

        <div class="flex min-w-0 flex-col">
            @if ($thread)
                <div class="border-b border-gray-200 px-4 py-3 font-semibold">
                    {{ $thread->user?->name }} <span class="text-sm font-normal text-gray-500">· {{ $thread->user?->email }} · {{ $thread->user?->deposit_code }}</span>
                </div>
                <div class="flex max-h-[460px] flex-1 flex-col gap-2 overflow-y-auto p-4"
                     x-init="$el.scrollTop = $el.scrollHeight" x-effect="$wire.lastSeenId; $nextTick(() => $el.scrollTop = $el.scrollHeight)">
                    @foreach ($messages as $m)
                        <div wire:key="am-{{ $m->id }}" @class([
                            'max-w-[78%] rounded-2xl px-3 py-2 text-sm break-words',
                            'self-end bg-primary-600 text-white' => $m->sender_type === 'admin',
                            'self-start bg-gray-100' => $m->sender_type !== 'admin',
                        ])>
                            {!! nl2br(e($m->body)) !!}
                            <time class="mt-0.5 block text-[10.5px] opacity-70">{{ $m->created_at->format('H:i d/m') }}</time>
                        </div>
                    @endforeach
                </div>
                <form wire:submit="reply" class="flex gap-2 border-t border-gray-200 p-3">
                    <input type="text" wire:model="body" maxlength="2000" placeholder="{{ __('admin.chat.placeholder') }}" aria-label="{{ __('admin.chat.reply_aria') }}" autocomplete="off"
                           x-on:focus="if (document.visibilityState === 'visible') $wire.replyFocused()"
                           class="block w-full rounded-lg border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="reply">{{ __('admin.chat.send') }}</x-filament::button>
                </form>
                @error('body')<p class="px-3 pb-3 text-sm text-danger-600">{{ $message }}</p>@enderror
            @else
                <div class="grid flex-1 place-items-center p-8 text-sm text-gray-500">{{ __('admin.chat.select') }}</div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
