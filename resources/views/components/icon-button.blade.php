@props([
    'icon',
    'label',
    'variant' => 'ghost',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
])

@php
    $variants = [
        'ghost' => 'text-ink-500 hover:bg-ink-900/5 hover:text-ink-900 active:bg-ink-900/10 dark:text-ink-400 dark:hover:bg-white/8 dark:hover:text-white',
        'secondary' => 'bg-white text-ink-700 shadow-xs ring-1 ring-inset ring-ink-900/12 hover:bg-ink-50 hover:text-ink-950 dark:bg-white/5 dark:text-ink-200 dark:ring-white/12 dark:hover:bg-white/10 dark:hover:text-white',
        'soft' => 'bg-leaf-50 text-leaf-700 hover:bg-leaf-100 dark:bg-leaf-400/10 dark:text-leaf-300 dark:hover:bg-leaf-400/15',
        'danger' => 'text-berry-600 hover:bg-berry-50 hover:text-berry-700 dark:text-berry-400 dark:hover:bg-berry-400/10 dark:hover:text-berry-300',
        'inverted' => 'text-white/70 hover:bg-white/10 hover:text-white active:bg-white/15',
    ];

    $sizes = [
        'sm' => 'size-8 rounded-lg pointer-coarse:size-11',
        'md' => 'size-10 rounded-xl pointer-coarse:size-11',
        'lg' => 'size-11 rounded-xl',
    ];

    $iconSizes = [
        'sm' => 'size-4',
        'md' => 'size-5',
        'lg' => 'size-5',
    ];

    $variant = array_key_exists($variant, $variants) ? $variant : 'ghost';
    $size = array_key_exists($size, $sizes) ? $size : 'md';

    $classes = [
        'inline-grid shrink-0 place-items-center transition duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50',
        $variants[$variant],
        $sizes[$size],
    ];
@endphp

@if ($href !== null)
    <a href="{{ $href }}" title="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-icon :name="$icon" :class="$iconSizes[$size]" />
        <span class="sr-only">{{ $label }}</span>
    </a>
@else
    <button type="{{ $type }}" title="{{ $label }}" @disabled($disabled) {{ $attributes->class($classes) }}>
        <x-icon :name="$icon" :class="$iconSizes[$size]" />
        <span class="sr-only">{{ $label }}</span>
    </button>
@endif
