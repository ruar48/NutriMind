@props([
    'level' => 1,
    'size' => 'md',
    'color' => null,
])

@php
    $tones = [
        'leaf' => ['rim' => 'fill-leaf-800', 'face' => 'fill-leaf-600', 'text' => 'text-white'],
        'carrot' => ['rim' => 'fill-carrot-900', 'face' => 'fill-carrot-700', 'text' => 'text-white'],
        'sun' => ['rim' => 'fill-sun-600', 'face' => 'fill-sun-400', 'text' => 'text-sun-950'],
        'berry' => ['rim' => 'fill-berry-900', 'face' => 'fill-berry-700', 'text' => 'text-white'],
        'grape' => ['rim' => 'fill-grape-800', 'face' => 'fill-grape-600', 'text' => 'text-white'],
        'sky' => ['rim' => 'fill-sky-900', 'face' => 'fill-sky-700', 'text' => 'text-white'],
        'teal' => ['rim' => 'fill-teal-900', 'face' => 'fill-teal-700', 'text' => 'text-white'],
        'lime' => ['rim' => 'fill-lime-600', 'face' => 'fill-lime-400', 'text' => 'text-lime-950'],
        'pink' => ['rim' => 'fill-pink-900', 'face' => 'fill-pink-700', 'text' => 'text-white'],
        'indigo' => ['rim' => 'fill-indigo-900', 'face' => 'fill-indigo-700', 'text' => 'text-white'],
        'slate' => ['rim' => 'fill-slate-700', 'face' => 'fill-slate-500', 'text' => 'text-white'],
    ];

    $sizes = [
        'xs' => 'h-6 w-[1.375rem] text-[10px]',
        'sm' => 'h-8 w-[1.8rem] text-xs',
        'md' => 'h-11 w-10 text-base',
        'lg' => 'h-16 w-[3.65rem] text-2xl',
        'xl' => 'h-24 w-[5.45rem] text-4xl',
    ];

    $level = max(1, (int) $level);
    $tier = match (true) {
        $level >= 40 => 'sun',
        $level >= 30 => 'berry',
        $level >= 20 => 'grape',
        $level >= 10 => 'sky',
        default => 'leaf',
    };
    $tone = $tones[$color ?? $tier] ?? $tones[$tier];
    $size = array_key_exists($size, $sizes) ? $size : 'md';
@endphp

<span
    {{ $attributes->class(['relative inline-grid shrink-0 place-items-center', $sizes[$size]]) }}
    role="img"
    aria-label="Level {{ $level }}"
    data-tier="{{ $color ?? $tier }}"
>
    <svg viewBox="0 0 40 44" class="absolute inset-0 size-full drop-shadow-[0_2px_3px_rgb(13_20_16/0.22)]" aria-hidden="true">
        <polygon points="20,1 38.5,11.5 38.5,32.5 20,43 1.5,32.5 1.5,11.5" @class([$tone['rim']]) />
        <polygon points="20,4.6 35.4,13.4 35.4,30.6 20,39.4 4.6,30.6 4.6,13.4" @class([$tone['face']]) />
        <polygon points="20,4.6 35.4,13.4 35.4,21.4 4.6,21.4 4.6,13.4" class="fill-white/16" />
        <polygon points="20,1 38.5,11.5 35.4,13.4 20,4.6 4.6,13.4 1.5,11.5" class="fill-white/22" />
    </svg>
    <span @class(['relative font-display leading-none font-extrabold tabular-nums [text-shadow:0_1px_0_rgb(0_0_0/0.22)]', $tone['text']]) aria-hidden="true">{{ $level }}</span>
</span>
