@props([
    'highlight' => false,
])

<tr {{ $attributes->class([
    'transition-colors hover:bg-ink-50/70 dark:hover:bg-white/3',
    'bg-sun-50/60 dark:bg-sun-400/5' => $highlight,
]) }}>
    {{ $slot }}
</tr>
