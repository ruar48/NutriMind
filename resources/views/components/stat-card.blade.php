@props([
    'label',
    'value',
    'icon' => null,
    'color' => 'leaf',
    'hint' => null,
    'trend' => null,
    'trendLabel' => null,
    'trendFormat' => 'number',
    'goodWhen' => 'up',
    'sparkline' => [],
    'href' => null,
    'countUp' => null,
    'countDecimals' => 0,
    'countPrefix' => '',
    'countSuffix' => '',
])

@php
    $tones = [
        'leaf' => 'bg-leaf-50 text-leaf-700 ring-leaf-600/15 dark:bg-leaf-400/10 dark:text-leaf-300 dark:ring-leaf-400/20',
        'carrot' => 'bg-carrot-50 text-carrot-700 ring-carrot-600/15 dark:bg-carrot-400/10 dark:text-carrot-300 dark:ring-carrot-400/20',
        'sun' => 'bg-sun-50 text-sun-800 ring-sun-600/20 dark:bg-sun-400/10 dark:text-sun-300 dark:ring-sun-400/20',
        'berry' => 'bg-berry-50 text-berry-700 ring-berry-600/15 dark:bg-berry-400/10 dark:text-berry-300 dark:ring-berry-400/20',
        'grape' => 'bg-grape-50 text-grape-700 ring-grape-600/15 dark:bg-grape-400/10 dark:text-grape-300 dark:ring-grape-400/20',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/15 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/20',
        'teal' => 'bg-teal-50 text-teal-700 ring-teal-600/15 dark:bg-teal-400/10 dark:text-teal-300 dark:ring-teal-400/20',
        'lime' => 'bg-lime-50 text-lime-800 ring-lime-600/20 dark:bg-lime-400/10 dark:text-lime-300 dark:ring-lime-400/20',
        'pink' => 'bg-pink-50 text-pink-700 ring-pink-600/15 dark:bg-pink-400/10 dark:text-pink-300 dark:ring-pink-400/20',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/15 dark:bg-indigo-400/10 dark:text-indigo-300 dark:ring-indigo-400/20',
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-500/15 dark:bg-slate-400/10 dark:text-slate-300 dark:ring-slate-400/20',
    ];

    $color = array_key_exists($color, $tones) ? $color : 'leaf';
    $tag = $href ? 'a' : 'div';
    $series = array_values(array_filter((array) $sparkline, fn ($point) => is_numeric($point)));
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'group relative flex min-w-0 flex-col rounded-2xl bg-white p-5 shadow-card ring-1 ring-ink-900/5 dark:bg-ink-900 dark:shadow-none dark:ring-white/8',
        'transition duration-200 hover:-translate-y-px hover:shadow-lift hover:ring-ink-900/10 focus-visible:outline-2 focus-visible:outline-offset-2 dark:hover:ring-white/15' => $href,
    ]) }}
    data-print-avoid-break
>
    <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-ink-600 dark:text-ink-300">{{ $label }}</p>
        @if ($icon)
            <span @class(['-mt-1 -mr-1 grid size-10 shrink-0 place-items-center rounded-xl ring-1 ring-inset transition group-hover:scale-105', $tones[$color]])>
                <x-icon :name="$icon" class="size-5" />
            </span>
        @endif
    </div>

    <p class="mt-1 font-display text-[1.75rem] leading-tight font-bold tracking-tight text-ink-950 sm:text-3xl dark:text-white">
        <span
            @if (is_numeric($countUp))
                data-count-up="{{ $countUp }}"
                data-count-decimals="{{ (int) $countDecimals }}"
                data-count-prefix="{{ $countPrefix }}"
                data-count-suffix="{{ $countSuffix }}"
            @endif
        >{{ $value }}</span>
    </p>

    @if ($trend !== null || $hint)
        <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
            @if ($trend !== null)
                <x-kpi-delta :value="$trend" :format="$trendFormat" :good-when="$goodWhen" :label="$trendLabel" />
            @endif
            @if ($hint)
                <p class="text-xs text-ink-500 dark:text-ink-400">{{ $hint }}</p>
            @endif
        </div>
    @endif

    @if (count($series) > 1)
        <x-chart.sparkline :data="$series" :color="$color" :label="$label.' trend'" class="mt-4" />
    @endif

    @if ($slot->hasActualContent())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</{{ $tag }}>
