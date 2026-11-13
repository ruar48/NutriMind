@props([
    'subject',
    'size' => 'md',
])

@php
    $tones = [
        'leaf' => 'bg-leaf-500 text-white',
        'carrot' => 'bg-carrot-500 text-white',
        'sun' => 'bg-sun-400 text-sun-950',
        'berry' => 'bg-berry-500 text-white',
        'grape' => 'bg-grape-500 text-white',
        'sky' => 'bg-sky-600 text-white dark:bg-sky-500',
        'teal' => 'bg-teal-600 text-white dark:bg-teal-500',
        'lime' => 'bg-lime-500 text-lime-950',
        'pink' => 'bg-pink-500 text-white',
        'indigo' => 'bg-indigo-500 text-white',
        'slate' => 'bg-slate-500 text-white',
    ];

    $sizes = [
        'sm' => ['box' => 'size-9 rounded-xl', 'icon' => 'size-5'],
        'md' => ['box' => 'size-11 rounded-xl', 'icon' => 'size-6'],
        'lg' => ['box' => 'size-14 rounded-2xl', 'icon' => 'size-7'],
    ];

    $color = array_key_exists((string) $subject?->color, $tones) ? $subject->color : 'leaf';
    $size = array_key_exists($size, $sizes) ? $size : 'md';
@endphp

<span aria-hidden="true" {{ $attributes->class(['grid shrink-0 place-items-center shadow-card ring-1 ring-inset ring-black/5 dark:shadow-none dark:ring-white/10', $tones[$color], $sizes[$size]['box']]) }}>
    <x-icon :name="$subject?->icon ?? 'book-open'" :class="$sizes[$size]['icon']" />
</span>
