@props([
    'href',
    'active' => false,
    'icon' => null,
    'count' => null,
])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'group relative inline-flex h-11 shrink-0 items-center gap-2 rounded-t-lg px-3 text-sm font-medium whitespace-nowrap transition focus-visible:outline-offset-[-2px]',
        'text-ink-950 dark:text-white' => $active,
        'text-ink-500 hover:text-ink-900 dark:text-ink-400 dark:hover:text-ink-100' => ! $active,
    ]) }}
>
    @if ($icon)
        <x-icon :name="$icon" @class(['size-4', 'text-leaf-600 dark:text-leaf-400' => $active, 'text-ink-400 group-hover:text-ink-600 dark:text-ink-500 dark:group-hover:text-ink-300' => ! $active]) />
    @endif
    {{ $slot }}
    @if ($count !== null)
        <span @class([
            'min-w-5 rounded-full px-1.5 text-center text-[11px] leading-5 font-semibold tabular-nums',
            'bg-leaf-100 text-leaf-800 dark:bg-leaf-400/15 dark:text-leaf-200' => $active,
            'bg-ink-900/5 text-ink-600 dark:bg-white/8 dark:text-ink-300' => ! $active,
        ])>{{ $count }}</span>
    @endif
    <span aria-hidden="true" @class([
        'absolute inset-x-2 -bottom-px h-0.5 rounded-full transition',
        'bg-leaf-500 dark:bg-leaf-400' => $active,
        'bg-transparent group-hover:bg-ink-300 dark:group-hover:bg-ink-600' => ! $active,
    ])></span>
</a>
