@props([
    'subject' => null,
    'name' => null,
    'color' => null,
    'icon' => null,
    'href' => null,
    'size' => 'md',
])

@php
    $tones = [
        'leaf' => ['chip' => 'bg-leaf-50 text-leaf-800 ring-leaf-600/20 dark:bg-leaf-400/10 dark:text-leaf-200 dark:ring-leaf-400/25', 'tile' => 'bg-leaf-500 text-white dark:bg-leaf-500'],
        'carrot' => ['chip' => 'bg-carrot-50 text-carrot-800 ring-carrot-600/20 dark:bg-carrot-400/10 dark:text-carrot-200 dark:ring-carrot-400/25', 'tile' => 'bg-carrot-500 text-white dark:bg-carrot-500'],
        'sun' => ['chip' => 'bg-sun-50 text-sun-900 ring-sun-600/25 dark:bg-sun-400/10 dark:text-sun-200 dark:ring-sun-400/25', 'tile' => 'bg-sun-400 text-sun-950 dark:bg-sun-400'],
        'berry' => ['chip' => 'bg-berry-50 text-berry-800 ring-berry-600/20 dark:bg-berry-400/10 dark:text-berry-200 dark:ring-berry-400/25', 'tile' => 'bg-berry-500 text-white dark:bg-berry-500'],
        'grape' => ['chip' => 'bg-grape-50 text-grape-800 ring-grape-600/20 dark:bg-grape-400/10 dark:text-grape-200 dark:ring-grape-400/25', 'tile' => 'bg-grape-500 text-white dark:bg-grape-500'],
        'sky' => ['chip' => 'bg-sky-50 text-sky-800 ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-200 dark:ring-sky-400/25', 'tile' => 'bg-sky-600 text-white dark:bg-sky-500'],
        'teal' => ['chip' => 'bg-teal-50 text-teal-800 ring-teal-600/20 dark:bg-teal-400/10 dark:text-teal-200 dark:ring-teal-400/25', 'tile' => 'bg-teal-600 text-white dark:bg-teal-500'],
        'lime' => ['chip' => 'bg-lime-50 text-lime-900 ring-lime-600/25 dark:bg-lime-400/10 dark:text-lime-200 dark:ring-lime-400/25', 'tile' => 'bg-lime-500 text-lime-950 dark:bg-lime-500'],
        'pink' => ['chip' => 'bg-pink-50 text-pink-800 ring-pink-600/20 dark:bg-pink-400/10 dark:text-pink-200 dark:ring-pink-400/25', 'tile' => 'bg-pink-500 text-white dark:bg-pink-500'],
        'indigo' => ['chip' => 'bg-indigo-50 text-indigo-800 ring-indigo-600/20 dark:bg-indigo-400/10 dark:text-indigo-200 dark:ring-indigo-400/25', 'tile' => 'bg-indigo-500 text-white dark:bg-indigo-500'],
        'slate' => ['chip' => 'bg-slate-100 text-slate-800 ring-slate-500/20 dark:bg-slate-400/10 dark:text-slate-200 dark:ring-slate-400/25', 'tile' => 'bg-slate-500 text-white dark:bg-slate-500'],
    ];

    $sizes = [
        'sm' => ['chip' => 'h-6 gap-1.5 pr-2 pl-0.5 text-xs', 'tile' => 'size-5', 'icon' => 'size-3'],
        'md' => ['chip' => 'h-7 gap-2 pr-2.5 pl-1 text-xs', 'tile' => 'size-5', 'icon' => 'size-3.5'],
        'lg' => ['chip' => 'h-9 gap-2 pr-3 pl-1 text-sm', 'tile' => 'size-7', 'icon' => 'size-4'],
    ];

    $label = $name ?? $subject?->name ?? '';
    $colorKey = $color ?? $subject?->color ?? 'leaf';
    $colorKey = array_key_exists($colorKey, $tones) ? $colorKey : 'leaf';
    $iconName = $icon ?? $subject?->icon ?? 'book-open';
    $size = array_key_exists($size, $sizes) ? $size : 'md';
    $tag = $href ? 'a' : 'span';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'inline-flex w-fit max-w-full shrink-0 items-center rounded-full font-semibold ring-1 ring-inset',
        $tones[$colorKey]['chip'],
        $sizes[$size]['chip'],
        'transition hover:brightness-95 dark:hover:brightness-125' => $href,
    ]) }}
>
    <span @class(['grid shrink-0 place-items-center rounded-full', $tones[$colorKey]['tile'], $sizes[$size]['tile']])>
        <x-icon :name="$iconName" :class="$sizes[$size]['icon']" stroke-width="2.25" />
    </span>
    <span class="truncate">{{ $label }}</span>
</{{ $tag }}>
