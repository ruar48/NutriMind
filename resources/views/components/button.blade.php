@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'iconTrailing' => null,
    'disabled' => false,
    'loading' => false,
])

@php
    $variants = [
        'primary' => 'bg-leaf-600 text-white shadow-[inset_0_1px_0_rgb(255_255_255/0.18),0_1px_2px_rgb(8_38_26/0.28)] hover:bg-leaf-700 active:bg-leaf-800 focus-visible:outline-leaf-600 dark:hover:bg-leaf-500 dark:active:bg-leaf-600',
        'secondary' => 'bg-white text-ink-800 shadow-xs ring-1 ring-inset ring-ink-900/12 hover:bg-ink-50 hover:text-ink-950 active:bg-ink-100 dark:bg-white/5 dark:text-ink-100 dark:ring-white/12 dark:hover:bg-white/10 dark:hover:text-white dark:active:bg-white/15',
        'ghost' => 'text-ink-700 hover:bg-ink-900/5 hover:text-ink-950 active:bg-ink-900/10 dark:text-ink-200 dark:hover:bg-white/8 dark:hover:text-white dark:active:bg-white/12',
        'soft' => 'bg-leaf-50 text-leaf-800 ring-1 ring-inset ring-leaf-600/15 hover:bg-leaf-100 active:bg-leaf-200/70 dark:bg-leaf-400/10 dark:text-leaf-200 dark:ring-leaf-400/20 dark:hover:bg-leaf-400/15 dark:active:bg-leaf-400/20',
        'danger' => 'bg-berry-600 text-white shadow-[inset_0_1px_0_rgb(255_255_255/0.16),0_1px_2px_rgb(77_2_26/0.28)] hover:bg-berry-700 active:bg-berry-800 focus-visible:outline-berry-600',
        'link' => 'text-leaf-700 underline-offset-4 hover:text-leaf-800 hover:underline dark:text-leaf-300 dark:hover:text-leaf-200',
    ];

    $sizes = [
        'xs' => 'h-8 gap-1.5 rounded-lg px-2.5 text-xs pointer-coarse:h-11',
        'sm' => 'h-9 gap-1.5 rounded-xl px-3 text-sm pointer-coarse:h-11',
        'md' => 'h-10 gap-2 rounded-xl px-4 text-sm pointer-coarse:h-11',
        'lg' => 'h-12 gap-2.5 rounded-xl px-5 text-base',
    ];

    $linkSizes = [
        'xs' => 'gap-1 text-xs',
        'sm' => 'gap-1.5 text-sm',
        'md' => 'gap-1.5 text-sm',
        'lg' => 'gap-2 text-base',
    ];

    $iconSizes = [
        'xs' => 'size-3.5',
        'sm' => 'size-4',
        'md' => 'size-4',
        'lg' => 'size-5',
    ];

    $variant = array_key_exists($variant, $variants) ? $variant : 'primary';
    $size = array_key_exists($size, $sizes) ? $size : 'md';
    $isInactive = $disabled || $loading;

    $classes = [
        'group/button relative inline-flex shrink-0 items-center justify-center font-semibold whitespace-nowrap transition duration-150 ease-out select-none focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-55 aria-disabled:cursor-not-allowed aria-disabled:opacity-55',
        'active:scale-[0.98]' => $variant !== 'link',
        $variants[$variant],
        $variant === 'link' ? $linkSizes[$size] : $sizes[$size],
    ];
@endphp

@if ($href !== null && ! $isInactive)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-icon :name="$icon" @class([$iconSizes[$size], '-ml-0.5' => $variant !== 'link']) />
        @endif
        {{ $slot }}
        @if ($iconTrailing)
            <x-icon :name="$iconTrailing" @class([$iconSizes[$size], '-mr-0.5' => $variant !== 'link']) />
        @endif
    </a>
@elseif ($href !== null)
    <a role="link" aria-disabled="true" {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-icon :name="$icon" @class([$iconSizes[$size], '-ml-0.5' => $variant !== 'link']) />
        @endif
        {{ $slot }}
        @if ($iconTrailing)
            <x-icon :name="$iconTrailing" @class([$iconSizes[$size], '-mr-0.5' => $variant !== 'link']) />
        @endif
    </a>
@else
    <button
        type="{{ $type }}"
        @disabled($isInactive)
        @if ($loading) data-loading aria-busy="true" @endif
        {{ $attributes->class($classes) }}
    >
        <svg data-spinner aria-hidden="true" viewBox="0 0 24 24" fill="none" @class(['hidden group-data-loading/button:block', $iconSizes[$size]])>
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.25" stroke-width="3" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
        @if ($icon)
            <x-icon :name="$icon" @class([$iconSizes[$size], 'group-data-loading/button:hidden', '-ml-0.5' => $variant !== 'link']) />
        @endif
        {{ $slot }}
        @if ($iconTrailing)
            <x-icon :name="$iconTrailing" @class([$iconSizes[$size], '-mr-0.5' => $variant !== 'link']) />
        @endif
    </button>
@endif
