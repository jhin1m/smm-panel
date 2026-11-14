<x-filament-panels::page>
    <div wire:poll.3s.visible class="space-y-6">
        @php
            $badge = match ($state['state']) {
                'running', 'pending' => 'bg-warning-100 text-warning-700',
                'success' => 'bg-success-100 text-success-700',
                'failed' => 'bg-danger-100 text-danger-700',
                default => 'bg-gray-100 text-gray-700',
            };
        @endphp

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                <div class="text-sm text-gray-500">{{ __('updates.current_version') }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ $current }}</div>
            </div>
            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                <div class="text-sm text-gray-500">{{ __('updates.latest_version') }}</div>
                @if ($latest)
                    <div class="mt-1 text-2xl font-semibold">{{ $latest['version'] }}</div>
                    <div class="text-sm text-gray-500">
                        {{ $target ? __('updates.new_available') : __('updates.up_to_date') }}
                    </div>
                @else
                    <div class="mt-1 text-sm text-gray-500">{{ __('updates.check_failed') }}</div>
                @endif
            </div>
            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                <div class="text-sm text-gray-500">{{ __('updates.updater') }}</div>
                <div class="mt-1 text-lg font-semibold {{ $alive ? 'text-success-700' : 'text-danger-700' }}">
                    {{ $alive ? __('updates.updater_online') : __('updates.updater_offline') }}
                </div>
                @unless ($alive)
                    <div class="text-sm text-gray-500">{{ __('updates.updater_offline_hint') }}</div>
                @endunless
            </div>
        </div>

        @if ($allowPrerelease)
            <p class="rounded-lg bg-warning-50 p-3 text-sm text-warning-700">{{ __('updates.prerelease_enabled') }}</p>
        @endif

        <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold">{{ __('updates.progress') }}</h3>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge }}">{{ __('updates.state.'.$state['state']) }}</span>
                @if ($state['from'] || $state['to'])
                    <span class="text-sm text-gray-500">{{ $state['from'] }} → {{ $state['to'] }}</span>
                @endif
            </div>

            @if ($state['state'] === 'running' && $state['step'])
                <p class="mt-2 text-sm">{{ __('updates.step_label') }}: <strong>{{ \Illuminate\Support\Facades\Lang::has('updates.steps.'.$state['step']) ? __('updates.steps.'.$state['step']) : $state['step'] }}</strong></p>
            @endif
            @if ($state['stale'])
                <p class="mt-2 text-sm text-danger-700">{{ __('updates.stale') }}</p>
            @elseif ($state['message'])
                <p class="mt-2 text-sm {{ $state['state'] === 'failed' ? 'text-danger-700' : 'text-gray-700' }}">{{ $state['message'] }}</p>
            @endif
            @if ($state['log_tail'] !== '' && $state['state'] !== 'idle')
                <pre class="mt-3 max-h-64 overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{{ $state['log_tail'] }}</pre>
            @endif
        </div>

        @if ($latest && filled($latest['body']))
            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                <div class="flex items-center gap-2">
                    <h3 class="font-semibold">{{ __('updates.release_notes', ['version' => $latest['version']]) }}</h3>
                    @if ($latest['url'] !== '')
                        <a href="{{ $latest['url'] }}" target="_blank" rel="noopener noreferrer" class="text-sm text-primary-600 underline">{{ __('updates.view_on_github') }}</a>
                    @endif
                </div>
                <div class="prose prose-sm mt-2 max-w-none">
                    {!! \Illuminate\Support\Str::markdown($latest['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </div>
            </div>
        @endif

        @if ($history !== [])
            <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200">
                <h3 class="font-semibold">{{ __('updates.history') }}</h3>
                <ul class="mt-2 divide-y divide-gray-100 text-sm">
                    @foreach ($history as $row)
                        <li class="flex flex-wrap gap-x-3 py-2" wire:key="hist-{{ $row['started_at'] ?? $loop->index }}">
                            <span class="font-medium">{{ ($row['from'] ?? '?') }} → {{ ($row['to'] ?? '?') }}</span>
                            <span class="{{ ($row['state'] ?? '') === 'success' ? 'text-success-700' : 'text-danger-700' }}">{{ __('updates.state.'.($row['state'] ?? 'failed')) }}</span>
                            @if (! empty($row['updated_at']))
                                <span class="text-gray-500">{{ \Illuminate\Support\Carbon::createFromTimestamp((int) $row['updated_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
                            @endif
                            @if (! empty($row['message']))
                                <span class="text-gray-600">{{ $row['message'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-filament-panels::page>
