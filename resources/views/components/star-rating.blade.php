@props([
    'value' => 0,
    'max' => 3,
    'size' => 'md',
])

@php
    $max = max(1, (int) $max);
    $value = max(0, min($max, (int) round((float) $value)));
    $sizes = [
        'sm' => 'size-3.5',
        'md' => 'size-5',
        'lg' => 'size-7',
    ];
    $iconSize = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->class('inline-flex items-center gap-0.5') }} role="img" aria-label="{{ $value }} of {{ $max }} stars">
    @for ($star = 1; $star <= $max; $star++)
        <x-icon name="star" @class([
            $iconSize,
            'fill-sun-400 text-sun-500 drop-shadow-[0_1px_0_rgb(183_88_4/0.35)]' => $star <= $value,
            'fill-ink-100 text-ink-300 dark:fill-white/5 dark:text-ink-600' => $star > $value,
        ]) stroke-width="1.5" />
    @endfor
</span>
