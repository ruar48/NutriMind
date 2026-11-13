@props([
    'label' => null,
    'icon' => null,
    'variant' => 'secondary',
    'align' => 'right',
    'width' => 'md',
])

@php
    $widths = [
        'sm' => 'w-48',
        'md' => 'w-56',
        'lg' => 'w-72',
    ];

    $triggerVariants = [
        'secondary' => 'h-10 gap-2 rounded-xl bg-white px-3.5 text-sm font-semibold text-ink-800 shadow-xs ring-1 ring-inset ring-ink-900/12 hover:bg-ink-50 pointer-coarse:h-11 dark:bg-white/5 dark:text-ink-100 dark:ring-white/12 dark:hover:bg-white/10',
        'ghost' => 'h-10 gap-2 rounded-xl px-3 text-sm font-semibold text-ink-700 hover:bg-ink-900/5 pointer-coarse:h-11 dark:text-ink-200 dark:hover:bg-white/8',
        'icon' => 'size-10 justify-center rounded-xl text-ink-500 hover:bg-ink-900/5 hover:text-ink-900 pointer-coarse:size-11 dark:text-ink-400 dark:hover:bg-white/8 dark:hover:text-white',
    ];

    $variant = array_key_exists($variant, $triggerVariants) ? $variant : 'secondary';
@endphp

<details data-dropdown {{ $attributes->class('group/dropdown relative inline-block text-left') }}>
    @isset($trigger)
        <summary class="list-none rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2" @if ($label) aria-label="{{ $label }}" @endif>
            {{ $trigger }}
        </summary>
    @else
        <summary
            @class(['inline-flex list-none items-center transition select-none focus-visible:outline-2 focus-visible:outline-offset-2', $triggerVariants[$variant]])
            @if ($variant === 'icon') aria-label="{{ $label ?? 'More actions' }}" title="{{ $label ?? 'More actions' }}" @endif
        >
            @if ($icon)
                <x-icon :name="$icon" @class(['size-5' => $variant === 'icon', 'size-4 -ml-0.5' => $variant !== 'icon']) />
            @endif
            @if ($variant !== 'icon')
                <span>{{ $label }}</span>
                <x-icon name="chevron-down" class="-mr-1 size-4 text-ink-400 transition group-open/dropdown:rotate-180" />
            @endif
        </summary>
    @endisset

    <div @class([
        'absolute z-40 mt-2 max-h-[min(28rem,70vh)] overflow-y-auto rounded-2xl bg-white p-1.5 shadow-lift ring-1 ring-ink-900/8 transition-[opacity,scale] duration-150 ease-out starting:scale-95 starting:opacity-0 dark:bg-ink-900 dark:ring-white/10',
        'right-0 origin-top-right' => $align === 'right',
        'left-0 origin-top-left' => $align !== 'right',
        $widths[$width] ?? $widths['md'],
    ])>
        {{ $slot }}
    </div>
</details>
