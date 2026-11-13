@props([
    'type',
    'size' => 'md',
])

@php
    $tones = [
        'leaf' => 'bg-leaf-600 text-white dark:bg-leaf-500',
        'carrot' => 'bg-carrot-500 text-white dark:bg-carrot-500',
        'sun' => 'bg-sun-400 text-sun-950 dark:bg-sun-400',
        'berry' => 'bg-berry-500 text-white dark:bg-berry-500',
        'grape' => 'bg-grape-500 text-white dark:bg-grape-500',
        'sky' => 'bg-sky-600 text-white dark:bg-sky-500',
        'teal' => 'bg-teal-600 text-white dark:bg-teal-500',
        'lime' => 'bg-lime-500 text-lime-950 dark:bg-lime-500',
        'pink' => 'bg-pink-500 text-white dark:bg-pink-500',
        'indigo' => 'bg-indigo-500 text-white dark:bg-indigo-500',
        'slate' => 'bg-slate-500 text-white dark:bg-slate-500',
    ];

    $sizes = [
        'md' => ['tile' => 'size-11 rounded-xl', 'icon' => 'size-5'],
        'lg' => ['tile' => 'size-14 rounded-2xl', 'icon' => 'size-7'],
    ];

    $tone = $tones[$type->color()] ?? $tones['leaf'];
    $dimensions = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->class(['grid shrink-0 place-items-center shadow-card', $dimensions['tile'], $tone]) }} aria-hidden="true">
    <x-icon :name="$type->icon()" :class="$dimensions['icon']" />
</span>
