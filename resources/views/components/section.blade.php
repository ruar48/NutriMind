@props([
    'title' => null,
    'description' => null,
    'icon' => null,
    'color' => 'leaf',
    'padding' => 'md',
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

    $paddings = [
        'none' => '',
        'sm' => 'px-4 pb-4',
        'md' => 'px-5 pb-5 sm:px-6 sm:pb-6',
        'lg' => 'px-6 pb-6 sm:px-8 sm:pb-8',
    ];

    $color = array_key_exists($color, $tones) ? $color : 'leaf';
    $padding = array_key_exists($padding, $paddings) ? $padding : 'md';
    $hasHeader = $title !== null || isset($actions);

    $bodyTopPaddings = [
        'none' => '',
        'sm' => 'pt-4',
        'md' => 'pt-5 sm:pt-6',
        'lg' => 'pt-6 sm:pt-8',
    ];
    $bodyTop = $hasHeader ? ($padding === 'none' ? '' : 'pt-4') : $bodyTopPaddings[$padding];
@endphp

<section {{ $attributes->class('relative min-w-0 rounded-2xl bg-white shadow-card ring-1 ring-ink-900/5 dark:bg-ink-900 dark:shadow-none dark:ring-white/8') }} data-print-avoid-break>
    @if ($hasHeader)
        <header @class([
            'flex flex-wrap items-start justify-between gap-x-4 gap-y-3',
            'px-4 pt-4' => $padding === 'sm',
            'px-5 pt-5 sm:px-6 sm:pt-6' => $padding === 'md' || $padding === 'none',
            'px-6 pt-6 sm:px-8 sm:pt-8' => $padding === 'lg',
            'pb-4 border-b border-ink-900/5 dark:border-white/8' => $padding === 'none',
        ])>
            <div class="flex min-w-0 items-start gap-3">
                @if ($icon)
                    <span @class(['grid size-9 shrink-0 place-items-center rounded-xl ring-1 ring-inset', $tones[$color]])>
                        <x-icon :name="$icon" class="size-[1.15rem]" />
                    </span>
                @endif
                <div class="min-w-0">
                    @if ($title !== null)
                        <h2 class="font-display text-base font-semibold tracking-tight text-ink-950 dark:text-white">{{ $title }}</h2>
                    @endif
                    @if ($description)
                        <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">{{ $description }}</p>
                    @endif
                </div>
            </div>
            @isset($actions)
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </header>
    @endif

    <div @class([$paddings[$padding], $bodyTop])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="rounded-b-2xl border-t border-ink-900/5 bg-ink-50/60 px-5 py-3 sm:px-6 dark:border-white/8 dark:bg-white/3">
            {{ $footer }}
        </div>
    @endisset
</section>
