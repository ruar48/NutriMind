@props([
    'level' => 1,
    'current' => 0,
    'needed' => 500,
    'progress' => null,
    'maxLevel' => null,
    'size' => 'md',
    'showLabels' => true,
])

@php
    if (is_array($progress)) {
        $level = $progress['level'] ?? $level;
        $current = $progress['into'] ?? $current;
        $needed = $progress['needed'] ?? $needed;
    }

    $level = max(1, (int) $level);
    $current = max(0, (int) $current);
    $needed = max(0, (int) $needed);
    $maxLevel = (int) ($maxLevel ?? config('nutrimind.settings.max_level', 50));
    $isMaxed = $level >= $maxLevel;
    $percent = $isMaxed ? 100 : ($needed > 0 ? min(100, $current / $needed * 100) : 0);

    $heights = [
        'sm' => 'h-2',
        'md' => 'h-3',
        'lg' => 'h-4',
    ];

    $valueText = $isMaxed
        ? 'Maximum level reached'
        : number_format($current).' of '.number_format($needed).' XP toward level '.($level + 1);
@endphp

<div {{ $attributes->class('w-full') }}>
    @if ($showLabels)
        <div class="mb-1.5 flex items-center justify-between gap-3 text-xs font-semibold">
            <span class="inline-flex items-center gap-1.5 text-ink-800 dark:text-ink-100">
                <x-level-badge :level="$level" size="xs" />
                Level {{ $level }}
            </span>
            <span class="text-ink-500 tabular-nums dark:text-ink-400">
                @if ($isMaxed)
                    Max level
                @else
                    {{ number_format($current) }} / {{ number_format($needed) }} XP
                @endif
            </span>
        </div>
    @endif

    <div
        role="progressbar"
        aria-label="Experience points"
        aria-valuemin="0"
        aria-valuemax="{{ $isMaxed ? 100 : $needed }}"
        aria-valuenow="{{ $isMaxed ? 100 : min($current, $needed) }}"
        aria-valuetext="{{ $valueText }}"
        @class(['relative overflow-hidden rounded-full bg-sun-100 ring-1 ring-sun-600/15 ring-inset dark:bg-sun-400/10 dark:ring-sun-400/20', $heights[$size] ?? $heights['md']])
    >
        <div
            data-progress-fill
            class="relative h-full overflow-hidden rounded-full bg-linear-to-r from-sun-400 via-sun-500 to-carrot-500 shadow-[inset_0_-2px_0_rgb(0_0_0/0.12),inset_0_1px_0_rgb(255_255_255/0.4)]"
            style="width: {{ round($percent, 2) }}%"
        >
            <span aria-hidden="true" class="absolute inset-y-0 left-0 w-1/3 animate-sheen bg-linear-to-r from-transparent via-white/60 to-transparent"></span>
        </div>
        <span aria-hidden="true" class="xp-ticks pointer-events-none absolute inset-0"></span>
    </div>

    @if ($showLabels && ! $isMaxed)
        <p class="mt-1 text-right text-[11px] text-ink-500 dark:text-ink-400">
            {{ number_format(max(0, $needed - $current)) }} XP to level {{ $level + 1 }}
        </p>
    @endif
</div>
