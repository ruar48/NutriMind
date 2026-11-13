@props([
    'columns' => 1,
])

<div {{ $attributes->class('@container min-w-0') }}>
    <dl @class([
        'divide-y divide-ink-900/5 dark:divide-white/8' => (int) $columns === 1,
        'grid grid-cols-1 gap-x-8 gap-y-5 @lg:grid-cols-2' => (int) $columns === 2,
        'grid grid-cols-1 gap-x-8 gap-y-5 @lg:grid-cols-2 @3xl:grid-cols-3' => (int) $columns >= 3,
    ])>
        {{ $slot }}
    </dl>
</div>
