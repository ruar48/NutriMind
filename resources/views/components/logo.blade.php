@props([
    'size' => 'md',
    'markOnly' => false,
    'inverted' => false,
])

@php
    $sizes = [
        'sm' => ['mark' => 'size-7', 'text' => 'text-lg', 'gap' => 'gap-2'],
        'md' => ['mark' => 'size-9', 'text' => 'text-xl', 'gap' => 'gap-2.5'],
        'lg' => ['mark' => 'size-12', 'text' => 'text-3xl', 'gap' => 'gap-3'],
        'xl' => ['mark' => 'size-16', 'text' => 'text-4xl', 'gap' => 'gap-3.5'],
    ];

    $dimensions = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->class(['inline-flex items-center', $dimensions['gap']]) }}>
    <svg
        viewBox="0 0 40 40"
        @class([$dimensions['mark'], 'shrink-0'])
        @if ($markOnly) role="img" aria-label="NutriMind" @else aria-hidden="true" @endif
    >
        <rect width="40" height="40" rx="11" class="fill-leaf-600" />
        <path d="M0 11C0 4.9 4.9 0 11 0h18c6.1 0 11 4.9 11 11v3C27 9 13 9 0 21Z" class="fill-white/10" />
        <path d="M9.6 30.4C9.2 19.5 16.4 10.6 31.2 9.2c1 14.6-7.4 22.2-21.6 21.2Z" class="fill-leaf-50" />
        <polyline points="11.2,28.8 16.9,23.4 15.7,19.6 21.9,17.6 20.9,14.3 27.6,12.2" fill="none" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" class="stroke-leaf-600" />
        <path d="M7.4 32.6 11.4 28.6" fill="none" stroke-width="2.3" stroke-linecap="round" class="stroke-leaf-50" />
        <path d="M31.6 24.2l.95 2.25 2.25.95-2.25.95-.95 2.25-.95-2.25-2.25-.95 2.25-.95Z" class="fill-sun-300" />
    </svg>
    @unless ($markOnly)
        <span @class(['font-display leading-none font-extrabold tracking-tight', $dimensions['text'], 'text-white' => $inverted, 'text-ink-950 dark:text-white' => ! $inverted])>Nutri<span @class(['text-leaf-300' => $inverted, 'text-leaf-600 dark:text-leaf-400' => ! $inverted])>Mind</span></span>
    @endunless
</span>
