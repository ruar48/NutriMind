@props([
    'value' => 0,
    'size' => 'md',
    'color' => 'leaf',
    'label' => 'Progress',
])

@php
    $tones = [
        'leaf' => ['track' => 'stroke-leaf-100 dark:stroke-leaf-400/15', 'fill' => 'stroke-leaf-500 dark:stroke-leaf-400'],
        'carrot' => ['track' => 'stroke-carrot-100 dark:stroke-carrot-400/15', 'fill' => 'stroke-carrot-500 dark:stroke-carrot-400'],
        'sun' => ['track' => 'stroke-sun-100 dark:stroke-sun-400/15', 'fill' => 'stroke-sun-500 dark:stroke-sun-400'],
        'berry' => ['track' => 'stroke-berry-100 dark:stroke-berry-400/15', 'fill' => 'stroke-berry-500 dark:stroke-berry-400'],
        'grape' => ['track' => 'stroke-grape-100 dark:stroke-grape-400/15', 'fill' => 'stroke-grape-500 dark:stroke-grape-400'],
        'sky' => ['track' => 'stroke-sky-100 dark:stroke-sky-400/15', 'fill' => 'stroke-sky-500 dark:stroke-sky-400'],
        'teal' => ['track' => 'stroke-teal-100 dark:stroke-teal-400/15', 'fill' => 'stroke-teal-500 dark:stroke-teal-400'],
        'lime' => ['track' => 'stroke-lime-100 dark:stroke-lime-400/15', 'fill' => 'stroke-lime-500 dark:stroke-lime-400'],
        'pink' => ['track' => 'stroke-pink-100 dark:stroke-pink-400/15', 'fill' => 'stroke-pink-500 dark:stroke-pink-400'],
        'indigo' => ['track' => 'stroke-indigo-100 dark:stroke-indigo-400/15', 'fill' => 'stroke-indigo-500 dark:stroke-indigo-400'],
        'slate' => ['track' => 'stroke-slate-200 dark:stroke-slate-400/15', 'fill' => 'stroke-slate-500 dark:stroke-slate-400'],
    ];

    $sizes = [
        'sm' => ['box' => 48, 'stroke' => 5, 'text' => 'text-[11px]'],
        'md' => ['box' => 72, 'stroke' => 7, 'text' => 'text-sm'],
        'lg' => ['box' => 104, 'stroke' => 9, 'text' => 'text-xl'],
        'xl' => ['box' => 140, 'stroke' => 11, 'text' => 'text-3xl'],
    ];

    $color = array_key_exists($color, $tones) ? $color : 'leaf';
    $dimensions = $sizes[$size] ?? $sizes['md'];
    $percent = max(0, min(100, (float) $value));
    $radius = ($dimensions['box'] - $dimensions['stroke']) / 2;
    $circumference = round(2 * M_PI * $radius, 3);
    $offset = round($circumference * (1 - $percent / 100), 3);
    $center = $dimensions['box'] / 2;
    $display = rtrim(rtrim(number_format($percent, 1), '0'), '.').'%';
@endphp

<div
    {{ $attributes->class('relative inline-grid shrink-0 place-items-center') }}
    style="width: {{ $dimensions['box'] }}px; height: {{ $dimensions['box'] }}px"
    role="img"
    aria-label="{{ $label }}: {{ $display }}"
>
    <svg viewBox="0 0 {{ $dimensions['box'] }} {{ $dimensions['box'] }}" class="absolute inset-0 size-full -rotate-90" aria-hidden="true">
        <circle cx="{{ $center }}" cy="{{ $center }}" r="{{ $radius }}" fill="none" stroke-width="{{ $dimensions['stroke'] }}" @class([$tones[$color]['track']]) />
        @if ($percent > 0)
            <circle
                data-ring-fill
                cx="{{ $center }}"
                cy="{{ $center }}"
                r="{{ $radius }}"
                fill="none"
                stroke-width="{{ $dimensions['stroke'] }}"
                stroke-linecap="round"
                stroke-dasharray="{{ $circumference }}"
                stroke-dashoffset="{{ $offset }}"
                style="--ring-circumference: {{ $circumference }}"
                @class([$tones[$color]['fill']])
            />
        @endif
    </svg>
    <div @class(['relative flex flex-col items-center justify-center text-center leading-tight', $dimensions['text']])>
        @if ($slot->hasActualContent())
            {{ $slot }}
        @else
            <span class="font-display font-bold text-ink-950 tabular-nums dark:text-white">{{ $display }}</span>
        @endif
    </div>
</div>
