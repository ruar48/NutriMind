@props([
    'href' => null,
    'icon' => null,
    'type' => 'button',
    'danger' => false,
    'disabled' => false,
    'active' => false,
])

@php
    $classes = [
        'flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-sm font-medium transition outline-none pointer-coarse:py-3',
        'text-berry-700 hover:bg-berry-50 focus-visible:bg-berry-50 dark:text-berry-300 dark:hover:bg-berry-400/10 dark:focus-visible:bg-berry-400/10' => $danger,
        'text-ink-700 hover:bg-ink-900/5 hover:text-ink-950 focus-visible:bg-ink-900/5 focus-visible:text-ink-950 dark:text-ink-200 dark:hover:bg-white/8 dark:hover:text-white dark:focus-visible:bg-white/8' => ! $danger,
        'bg-leaf-50 text-leaf-800 dark:bg-leaf-400/10 dark:text-leaf-200' => $active,
        'pointer-events-none opacity-50' => $disabled,
    ];
    $iconClasses = [
        'size-4 shrink-0',
        'text-berry-500 dark:text-berry-400' => $danger,
        'text-ink-400 dark:text-ink-500' => ! $danger,
    ];
@endphp

@if ($href !== null && ! $disabled)
    <a href="{{ $href }}" data-dropdown-item @if ($active) aria-current="page" @endif {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-icon :name="$icon" @class($iconClasses) />
        @endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    </a>
@else
    <button type="{{ $type }}" data-dropdown-item @disabled($disabled) {{ $attributes->class($classes) }}>
        @if ($icon)
            <x-icon :name="$icon" @class($iconClasses) />
        @endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    </button>
@endif
