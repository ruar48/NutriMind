@props([
    'title',
    'description' => null,
    'icon' => 'sprout',
    'illustration' => null,
    'variant' => 'plain',
    'compact' => false,
])

@php
    $variants = [
        'plain' => '',
        'card' => 'rounded-2xl bg-white shadow-card ring-1 ring-ink-900/5 dark:bg-ink-900 dark:shadow-none dark:ring-white/8',
        'dashed' => 'rounded-2xl border border-dashed border-ink-900/15 bg-white/60 dark:border-white/12 dark:bg-white/3',
    ];

    $variant = array_key_exists($variant, $variants) ? $variant : 'plain';
@endphp

<div {{ $attributes->class([
    'flex min-w-0 flex-col items-center text-center',
    $variants[$variant],
    'px-6 py-12 sm:py-16' => ! $compact,
    'px-4 py-8' => $compact,
]) }}>
    @if ($illustration)
        <x-illustration :name="$illustration" @class(['w-auto text-ink-700 dark:text-ink-200', 'h-28 sm:h-32' => ! $compact, 'h-20' => $compact]) />
    @else
        <span @class([
            'relative grid place-items-center rounded-2xl bg-leaf-50 text-leaf-700 ring-1 ring-inset ring-leaf-600/15 dark:bg-leaf-400/10 dark:text-leaf-300 dark:ring-leaf-400/20',
            'size-14' => ! $compact,
            'size-11' => $compact,
        ])>
            <span aria-hidden="true" class="absolute -top-1 -right-1 size-3 rounded-full bg-sun-300 ring-2 ring-white dark:bg-sun-400 dark:ring-ink-900"></span>
            <x-icon :name="$icon" @class(['size-7' => ! $compact, 'size-5' => $compact]) />
        </span>
    @endif

    <h3 @class([
        'font-display font-semibold tracking-tight text-ink-950 dark:text-white',
        'mt-5 text-lg' => ! $compact,
        'mt-3 text-base' => $compact,
    ])>{{ $title }}</h3>

    @if ($description)
        <p class="mt-1.5 max-w-md text-sm text-ink-500 dark:text-ink-400">{{ $description }}</p>
    @endif

    @isset($actions)
        <div @class(['flex flex-wrap items-center justify-center gap-2', 'mt-6' => ! $compact, 'mt-4' => $compact])>
            {{ $actions }}
        </div>
    @endisset
</div>
