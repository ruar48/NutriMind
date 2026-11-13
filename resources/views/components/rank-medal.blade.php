@props([
    'rank',
    'size' => 'md',
])

@php
    $rank = max(1, (int) $rank);

    $medals = [
        1 => ['ribbon' => 'fill-berry-500', 'ribbonAlt' => 'fill-berry-700', 'rim' => 'fill-sun-600', 'face' => 'fill-sun-400', 'text' => 'text-sun-950', 'name' => 'Gold'],
        2 => ['ribbon' => 'fill-sky-500', 'ribbonAlt' => 'fill-sky-700', 'rim' => 'fill-slate-400', 'face' => 'fill-slate-200', 'text' => 'text-slate-800', 'name' => 'Silver'],
        3 => ['ribbon' => 'fill-leaf-500', 'ribbonAlt' => 'fill-leaf-700', 'rim' => 'fill-carrot-800', 'face' => 'fill-carrot-400', 'text' => 'text-carrot-950', 'name' => 'Bronze'],
    ];

    $sizes = [
        'sm' => ['box' => 'h-8 w-7', 'text' => 'text-[11px]', 'plain' => 'size-7 text-xs'],
        'md' => ['box' => 'h-10 w-9', 'text' => 'text-sm', 'plain' => 'size-8 text-sm'],
        'lg' => ['box' => 'h-14 w-12', 'text' => 'text-lg', 'plain' => 'size-11 text-base'],
    ];

    $dimensions = $sizes[$size] ?? $sizes['md'];
    $ordinal = $rank.match (true) {
        in_array($rank % 100, [11, 12, 13], true) => 'th',
        $rank % 10 === 1 => 'st',
        $rank % 10 === 2 => 'nd',
        $rank % 10 === 3 => 'rd',
        default => 'th',
    };
    $medal = $medals[$rank] ?? null;
@endphp

@if ($medal)
    <span {{ $attributes->class(['relative inline-grid shrink-0 place-items-end justify-items-center', $dimensions['box']]) }} role="img" aria-label="{{ $ordinal }} place, {{ $medal['name'] }} medal" data-medal="{{ strtolower($medal['name']) }}">
        <svg viewBox="0 0 32 36" class="absolute inset-0 size-full drop-shadow-[0_2px_2px_rgb(13_20_16/0.2)]" aria-hidden="true">
            <polygon points="7,0 14,0 18,13 11,14" @class([$medal['ribbonAlt']]) />
            <polygon points="18,0 25,0 21,14 14,13" @class([$medal['ribbon']]) />
            <circle cx="16" cy="23" r="12" @class([$medal['rim']]) />
            <circle cx="16" cy="23" r="9.2" @class([$medal['face']]) />
            <path d="M8.5 19.5a9 9 0 0 1 15-4.4" class="fill-none stroke-white/50" stroke-width="1.6" stroke-linecap="round" />
        </svg>
        <span @class(['relative mb-[18%] font-display leading-none font-extrabold', $dimensions['text'], $medal['text']]) aria-hidden="true">{{ $rank }}</span>
    </span>
@else
    <span {{ $attributes->class(['inline-grid shrink-0 place-items-center rounded-full bg-ink-900/5 font-display font-bold text-ink-600 tabular-nums ring-1 ring-ink-900/8 ring-inset dark:bg-white/8 dark:text-ink-300 dark:ring-white/10', $dimensions['plain']]) }} role="img" aria-label="{{ $ordinal }} place">
        <span aria-hidden="true">{{ $rank }}</span>
    </span>
@endif
