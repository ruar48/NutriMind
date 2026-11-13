@props([
    'color' => 'slate',
    'size' => 'md',
    'dot' => false,
    'icon' => null,
])

@php
    $tones = [
        'leaf' => 'bg-leaf-50 text-leaf-700 ring-leaf-600/20 dark:bg-leaf-400/10 dark:text-leaf-300 dark:ring-leaf-400/25',
        'carrot' => 'bg-carrot-50 text-carrot-700 ring-carrot-600/20 dark:bg-carrot-400/10 dark:text-carrot-300 dark:ring-carrot-400/25',
        'sun' => 'bg-sun-50 text-sun-800 ring-sun-600/25 dark:bg-sun-400/10 dark:text-sun-300 dark:ring-sun-400/25',
        'berry' => 'bg-berry-50 text-berry-700 ring-berry-600/20 dark:bg-berry-400/10 dark:text-berry-300 dark:ring-berry-400/25',
        'grape' => 'bg-grape-50 text-grape-700 ring-grape-600/20 dark:bg-grape-400/10 dark:text-grape-300 dark:ring-grape-400/25',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/25',
        'teal' => 'bg-teal-50 text-teal-700 ring-teal-600/20 dark:bg-teal-400/10 dark:text-teal-300 dark:ring-teal-400/25',
        'lime' => 'bg-lime-50 text-lime-800 ring-lime-600/25 dark:bg-lime-400/10 dark:text-lime-300 dark:ring-lime-400/25',
        'pink' => 'bg-pink-50 text-pink-700 ring-pink-600/20 dark:bg-pink-400/10 dark:text-pink-300 dark:ring-pink-400/25',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-400/10 dark:text-indigo-300 dark:ring-indigo-400/25',
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-400/10 dark:text-slate-300 dark:ring-slate-400/25',
    ];

    $dots = [
        'leaf' => 'bg-leaf-500 dark:bg-leaf-400',
        'carrot' => 'bg-carrot-500 dark:bg-carrot-400',
        'sun' => 'bg-sun-500 dark:bg-sun-400',
        'berry' => 'bg-berry-500 dark:bg-berry-400',
        'grape' => 'bg-grape-500 dark:bg-grape-400',
        'sky' => 'bg-sky-500 dark:bg-sky-400',
        'teal' => 'bg-teal-500 dark:bg-teal-400',
        'lime' => 'bg-lime-500 dark:bg-lime-400',
        'pink' => 'bg-pink-500 dark:bg-pink-400',
        'indigo' => 'bg-indigo-500 dark:bg-indigo-400',
        'slate' => 'bg-slate-400 dark:bg-slate-500',
    ];

    $sizes = [
        'sm' => 'h-5 gap-1 px-1.5 text-[11px]',
        'md' => 'h-6 gap-1.5 px-2 text-xs',
        'lg' => 'h-7 gap-1.5 px-2.5 text-sm',
    ];

    $iconSizes = [
        'sm' => 'size-3',
        'md' => 'size-3.5',
        'lg' => 'size-4',
    ];

    $color = array_key_exists($color, $tones) ? $color : 'slate';
    $size = array_key_exists($size, $sizes) ? $size : 'md';
@endphp

<span {{ $attributes->class(['inline-flex w-fit shrink-0 items-center rounded-full font-semibold whitespace-nowrap ring-1 ring-inset', $tones[$color], $sizes[$size]]) }} data-color="{{ $color }}">
    @if ($icon)
        <x-icon :name="$icon" :class="$iconSizes[$size]" stroke-width="2" />
    @elseif ($dot)
        <span aria-hidden="true" @class(['size-1.5 rounded-full', $dots[$color]])></span>
    @endif
    {{ $slot }}
</span>
