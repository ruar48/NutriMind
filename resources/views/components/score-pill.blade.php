@props([
    'value' => null,
    'size' => 'md',
    'showBand' => false,
])

@php
    $score = is_numeric($value) ? (float) $value : null;
    $band = \App\Enums\PerformanceBand::fromScore($score);

    $tones = [
        'leaf' => 'bg-leaf-50 text-leaf-800 ring-leaf-600/25 dark:bg-leaf-400/10 dark:text-leaf-200 dark:ring-leaf-400/30',
        'teal' => 'bg-teal-50 text-teal-800 ring-teal-600/25 dark:bg-teal-400/10 dark:text-teal-200 dark:ring-teal-400/30',
        'sky' => 'bg-sky-50 text-sky-800 ring-sky-600/25 dark:bg-sky-400/10 dark:text-sky-200 dark:ring-sky-400/30',
        'sun' => 'bg-sun-50 text-sun-800 ring-sun-600/30 dark:bg-sun-400/10 dark:text-sun-200 dark:ring-sun-400/30',
        'berry' => 'bg-berry-50 text-berry-800 ring-berry-600/25 dark:bg-berry-400/10 dark:text-berry-200 dark:ring-berry-400/30',
        'slate' => 'bg-ink-900/5 text-ink-500 ring-ink-900/10 dark:bg-white/5 dark:text-ink-400 dark:ring-white/10',
        'carrot' => 'bg-carrot-50 text-carrot-800 ring-carrot-600/25 dark:bg-carrot-400/10 dark:text-carrot-200 dark:ring-carrot-400/30',
        'grape' => 'bg-grape-50 text-grape-800 ring-grape-600/25 dark:bg-grape-400/10 dark:text-grape-200 dark:ring-grape-400/30',
        'lime' => 'bg-lime-50 text-lime-800 ring-lime-600/25 dark:bg-lime-400/10 dark:text-lime-200 dark:ring-lime-400/30',
        'pink' => 'bg-pink-50 text-pink-800 ring-pink-600/25 dark:bg-pink-400/10 dark:text-pink-200 dark:ring-pink-400/30',
        'indigo' => 'bg-indigo-50 text-indigo-800 ring-indigo-600/25 dark:bg-indigo-400/10 dark:text-indigo-200 dark:ring-indigo-400/30',
    ];

    $sizes = [
        'sm' => 'h-5 px-1.5 text-[11px]',
        'md' => 'h-6 px-2 text-xs',
        'lg' => 'h-8 px-3 text-sm',
    ];

    $size = array_key_exists($size, $sizes) ? $size : 'md';
    $color = array_key_exists($band->color(), $tones) ? $band->color() : 'slate';

    if ($score === null) {
        $formatted = '—';
    } else {
        $formatted = number_format($score, 1);
        $formatted = (str_ends_with($formatted, '.0') ? substr($formatted, 0, -2) : $formatted).'%';
    }
@endphp

<span
    {{ $attributes->class(['inline-flex w-fit shrink-0 items-center gap-1 rounded-full font-semibold whitespace-nowrap tabular-nums ring-1 ring-inset', $tones[$color], $sizes[$size]]) }}
    title="{{ $band->label() }}"
    data-band="{{ $band->value }}"
>
    {{ $formatted }}
    @if ($showBand && $score !== null)
        <span class="font-medium opacity-80">· {{ $band->shortLabel() }}</span>
    @endif
    <span class="sr-only">({{ $band->label() }})</span>
</span>
