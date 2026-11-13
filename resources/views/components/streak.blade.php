@props([
    'days' => 0,
    'size' => 'md',
])

@php
    $days = max(0, (int) $days);
    $isActive = $days > 0;

    $sizes = [
        'sm' => ['box' => 'h-6 gap-1 px-2 text-xs', 'icon' => 'size-3.5'],
        'md' => ['box' => 'h-7 gap-1.5 px-2.5 text-sm', 'icon' => 'size-4'],
        'lg' => ['box' => 'h-9 gap-2 px-3 text-base', 'icon' => 'size-5'],
    ];

    $dimensions = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->class([
    'inline-flex w-fit items-center rounded-full font-semibold whitespace-nowrap ring-1 ring-inset',
    $dimensions['box'],
    'bg-carrot-50 text-carrot-800 ring-carrot-500/25 dark:bg-carrot-400/10 dark:text-carrot-200 dark:ring-carrot-400/25' => $isActive,
    'bg-ink-900/5 text-ink-500 ring-ink-900/8 dark:bg-white/5 dark:text-ink-400 dark:ring-white/10' => ! $isActive,
]) }}>
    <x-icon name="flame" @class([
        $dimensions['icon'],
        'animate-flicker fill-sun-300 text-carrot-500 dark:fill-sun-400/60 dark:text-carrot-400' => $isActive,
        'text-ink-400 dark:text-ink-500' => ! $isActive,
    ]) stroke-width="2" />
    <span class="tabular-nums">{{ $days }}</span>
    <span class="font-medium">{{ \Illuminate\Support\Str::plural('day', $days) }}</span>
    <span class="sr-only">streak</span>
</span>
