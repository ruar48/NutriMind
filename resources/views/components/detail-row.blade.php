@aware([
    'columns' => 1,
])

@props([
    'label',
    'value' => null,
    'icon' => null,
])

@php
    $stacked = (int) $columns > 1;
    $hasSlot = $slot->hasActualContent();
    $isBlank = ! $hasSlot && blank($value);
@endphp

<div {{ $attributes->class([
    'flex flex-col gap-1',
    'py-3 first:pt-0 last:pb-0 @md:flex-row @md:items-baseline @md:gap-6' => ! $stacked,
]) }}>
    <dt @class(['flex items-center gap-1.5 text-sm text-ink-500 dark:text-ink-400', '@md:w-44 @md:shrink-0' => ! $stacked])>
        @if ($icon)
            <x-icon :name="$icon" class="size-4 text-ink-400 dark:text-ink-500" />
        @endif
        {{ $label }}
    </dt>
    <dd class="min-w-0 text-sm font-medium break-words text-ink-900 dark:text-ink-100">
        @if ($isBlank)
            <span class="font-normal text-ink-400 dark:text-ink-500">—</span>
        @elseif ($hasSlot)
            {{ $slot }}
        @else
            {{ $value }}
        @endif
    </dd>
</div>
