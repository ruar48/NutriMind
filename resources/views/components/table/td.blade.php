@props([
    'align' => 'left',
    'numeric' => false,
    'primary' => false,
])

<td {{ $attributes->class([
    'px-4 py-3 align-middle first:pl-5 last:pr-5',
    'text-left' => $align === 'left',
    'text-center' => $align === 'center',
    'text-right' => $align === 'right',
    'tabular-nums' => $numeric,
    'font-medium text-ink-900 dark:text-ink-50' => $primary,
    'text-ink-600 dark:text-ink-300' => ! $primary,
]) }}>
    {{ $slot }}
</td>
