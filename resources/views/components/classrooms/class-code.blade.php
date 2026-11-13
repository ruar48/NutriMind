@props([
    'code',
    'size' => 'sm',
])

@php
    $isLarge = $size === 'lg';
    $copyLabel = $isLarge ? 'Copy code' : 'Copy';
@endphp

<div {{ $attributes->class(['flex min-w-0 items-center', 'gap-2' => ! $isLarge, 'justify-between gap-3' => $isLarge]) }} data-class-code="{{ $code }}">
    <p class="min-w-0">
        <span @class(['block font-medium text-ink-600 dark:text-ink-300', 'sr-only' => ! $isLarge, 'text-sm' => $isLarge])>Class code</span>
        <span @class([
            'block font-display font-bold text-ink-950 tabular-nums dark:text-white',
            'text-sm tracking-[0.18em]' => ! $isLarge,
            'mt-2 text-3xl tracking-[0.18em]' => $isLarge,
        ])>{{ $code }}</span>
    </p>
    <x-copy-button
        :text="$code"
        :label="$copyLabel"
        :size="$isLarge ? 'sm' : 'xs'"
        :variant="$isLarge ? 'secondary' : 'ghost'"
        :aria-label="$copyLabel.' '.$code"
    />
</div>
