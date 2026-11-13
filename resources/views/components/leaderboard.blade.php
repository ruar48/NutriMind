@props([
    'entries' => [],
    'podium' => true,
    'unit' => 'XP',
    'emptyText' => 'No learners have earned points yet.',
])

@php
    $rows = collect($entries)->values()->map(fn (array $entry, int $index) => [
        'rank' => (int) ($entry['rank'] ?? $index + 1),
        'name' => (string) ($entry['name'] ?? ''),
        'points' => (int) ($entry['points'] ?? 0),
        'level' => isset($entry['level']) ? (int) $entry['level'] : null,
        'meta' => $entry['meta'] ?? null,
        'url' => $entry['url'] ?? null,
    ]);
    $showPodium = $podium && $rows->count() >= 3;
    $podiumRows = $showPodium ? $rows->take(3) : collect();
    $listRows = $showPodium ? $rows->slice(3) : $rows;
    $podiumOrder = [1, 0, 2];
    $podiumHeights = [0 => 'h-24', 1 => 'h-16', 2 => 'h-12'];
    $podiumTones = [
        0 => 'bg-linear-to-b from-sun-200 to-sun-100 ring-sun-500/30 dark:from-sun-400/25 dark:to-sun-400/10 dark:ring-sun-400/25',
        1 => 'bg-linear-to-b from-slate-200 to-slate-100 ring-slate-400/30 dark:from-slate-400/25 dark:to-slate-400/10 dark:ring-slate-400/25',
        2 => 'bg-linear-to-b from-carrot-200 to-carrot-100 ring-carrot-500/30 dark:from-carrot-400/25 dark:to-carrot-400/10 dark:ring-carrot-400/25',
    ];
@endphp

@if ($rows->isEmpty())
    <x-empty-state compact icon="trophy" title="Leaderboard is empty" :description="$emptyText" {{ $attributes }} />
@else
    <div {{ $attributes->class('min-w-0') }}>
        @if ($showPodium)
            <ol class="mb-4 grid grid-cols-3 items-end gap-2 sm:gap-3" aria-label="Top three">
                @foreach ($podiumOrder as $position)
                    @php $row = $podiumRows[$position]; @endphp
                    <li class="flex min-w-0 flex-col items-center text-center">
                        <div class="relative">
                            <x-avatar :name="$row['name']" :size="$position === 0 ? 'lg' : 'md'" class="ring-4 ring-white dark:ring-ink-900" />
                            <x-rank-medal :rank="$row['rank']" size="sm" class="absolute -right-2 -bottom-2" />
                        </div>
                        <p class="mt-3 w-full truncate text-sm font-semibold text-ink-900 dark:text-ink-100">
                            @if ($row['url'])
                                <a href="{{ $row['url'] }}" class="hover:underline">{{ $row['name'] }}</a>
                            @else
                                {{ $row['name'] }}
                            @endif
                        </p>
                        <p class="text-xs font-medium text-ink-500 tabular-nums dark:text-ink-400">{{ number_format($row['points']) }} {{ $unit }}</p>
                        <div @class(['mt-2 grid w-full place-items-center rounded-t-xl ring-1 ring-inset', $podiumHeights[$position], $podiumTones[$position]])>
                            <span class="font-display text-2xl font-extrabold text-ink-900/70 dark:text-white/70" aria-hidden="true">{{ $row['rank'] }}</span>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($listRows->isNotEmpty())
            <ol class="divide-y divide-ink-900/5 dark:divide-white/8" @if ($showPodium) start="4" @endif>
                @foreach ($listRows as $row)
                    <li class="flex items-center gap-3 py-2.5">
                        <x-rank-medal :rank="$row['rank']" size="sm" />
                        <x-avatar :name="$row['name']" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink-900 dark:text-ink-100">
                                @if ($row['url'])
                                    <a href="{{ $row['url'] }}" class="hover:underline">{{ $row['name'] }}</a>
                                @else
                                    {{ $row['name'] }}
                                @endif
                            </p>
                            @if ($row['meta'])
                                <p class="truncate text-xs text-ink-500 dark:text-ink-400">{{ $row['meta'] }}</p>
                            @endif
                        </div>
                        @if ($row['level'] !== null)
                            <x-level-badge :level="$row['level']" size="sm" />
                        @endif
                        <span class="w-20 shrink-0 text-right text-sm font-semibold text-ink-900 tabular-nums dark:text-white">{{ number_format($row['points']) }} <span class="text-xs font-medium text-ink-500 dark:text-ink-400">{{ $unit }}</span></span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
@endif
