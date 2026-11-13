@props([
    'value' => 0,
    'max' => 100,
    'color' => 'leaf',
    'size' => 'md',
    'label' => null,
    'showValue' => false,
    'valueText' => null,
    'striped' => false,
])

@php
    $tones = [
        'leaf' => ['track' => 'bg-leaf-100 dark:bg-leaf-400/15', 'fill' => 'bg-leaf-500 dark:bg-leaf-400'],
        'carrot' => ['track' => 'bg-carrot-100 dark:bg-carrot-400/15', 'fill' => 'bg-carrot-500 dark:bg-carrot-400'],
        'sun' => ['track' => 'bg-sun-100 dark:bg-sun-400/15', 'fill' => 'bg-sun-500 dark:bg-sun-400'],
        'berry' => ['track' => 'bg-berry-100 dark:bg-berry-400/15', 'fill' => 'bg-berry-500 dark:bg-berry-400'],
        'grape' => ['track' => 'bg-grape-100 dark:bg-grape-400/15', 'fill' => 'bg-grape-500 dark:bg-grape-400'],
        'sky' => ['track' => 'bg-sky-100 dark:bg-sky-400/15', 'fill' => 'bg-sky-500 dark:bg-sky-400'],
        'teal' => ['track' => 'bg-teal-100 dark:bg-teal-400/15', 'fill' => 'bg-teal-500 dark:bg-teal-400'],
        'lime' => ['track' => 'bg-lime-100 dark:bg-lime-400/15', 'fill' => 'bg-lime-500 dark:bg-lime-400'],
        'pink' => ['track' => 'bg-pink-100 dark:bg-pink-400/15', 'fill' => 'bg-pink-500 dark:bg-pink-400'],
        'indigo' => ['track' => 'bg-indigo-100 dark:bg-indigo-400/15', 'fill' => 'bg-indigo-500 dark:bg-indigo-400'],
        'slate' => ['track' => 'bg-slate-200 dark:bg-slate-400/15', 'fill' => 'bg-slate-500 dark:bg-slate-400'],
    ];

    $sizes = [
        'xs' => 'h-1',
        'sm' => 'h-1.5',
        'md' => 'h-2.5',
        'lg' => 'h-3.5',
    ];

    $color = array_key_exists($color, $tones) ? $color : 'leaf';
    $size = array_key_exists($size, $sizes) ? $size : 'md';
    $current = max(0, (float) $value);
    $maximum = max(0, (float) $max);
    $percent = $maximum > 0 ? min(100, $current / $maximum * 100) : 0;
    $display = $valueText ?? rtrim(rtrim(number_format($percent, 1), '0'), '.').'%';
@endphp

<div {{ $attributes->class('w-full') }}>
    @if ($label || $showValue)
        <div class="mb-1.5 flex items-baseline justify-between gap-3 text-sm">
            @if ($label)
                <span class="min-w-0 truncate font-medium text-ink-700 dark:text-ink-200">{{ $label }}</span>
            @endif
            @if ($showValue)
                <span class="ml-auto shrink-0 font-semibold text-ink-900 tabular-nums dark:text-white">{{ $display }}</span>
            @endif
        </div>
    @endif
    <div
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="{{ $maximum + 0 }}"
        aria-valuenow="{{ min($current, $maximum) + 0 }}"
        aria-valuetext="{{ $display }}"
        aria-label="{{ $label ?? 'Progress' }}"
        @class(['overflow-hidden rounded-full', $tones[$color]['track'], $sizes[$size]])
    >
        <div
            data-progress-fill
            @class([
                'h-full rounded-full',
                $tones[$color]['fill'],
                'bg-[linear-gradient(45deg,rgb(255_255_255/0.28)_25%,transparent_25%,transparent_50%,rgb(255_255_255/0.28)_50%,rgb(255_255_255/0.28)_75%,transparent_75%,transparent)] bg-size-[14px_14px]' => $striped,
            ])
            style="width: {{ round($percent, 2) }}%"
        ></div>
    </div>
</div>
