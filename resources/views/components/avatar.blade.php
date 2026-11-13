@props([
    'name' => '',
    'src' => null,
    'size' => 'md',
    'status' => null,
    'decorative' => true,
])

@php
    $tones = [
        'leaf' => 'bg-leaf-100 text-leaf-800 dark:bg-leaf-400/15 dark:text-leaf-200',
        'carrot' => 'bg-carrot-100 text-carrot-800 dark:bg-carrot-400/15 dark:text-carrot-200',
        'sun' => 'bg-sun-100 text-sun-900 dark:bg-sun-400/15 dark:text-sun-200',
        'berry' => 'bg-berry-100 text-berry-800 dark:bg-berry-400/15 dark:text-berry-200',
        'grape' => 'bg-grape-100 text-grape-800 dark:bg-grape-400/15 dark:text-grape-200',
        'sky' => 'bg-sky-100 text-sky-800 dark:bg-sky-400/15 dark:text-sky-200',
        'teal' => 'bg-teal-100 text-teal-800 dark:bg-teal-400/15 dark:text-teal-200',
        'lime' => 'bg-lime-100 text-lime-900 dark:bg-lime-400/15 dark:text-lime-200',
        'pink' => 'bg-pink-100 text-pink-800 dark:bg-pink-400/15 dark:text-pink-200',
        'indigo' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-400/15 dark:text-indigo-200',
        'slate' => 'bg-slate-200 text-slate-700 dark:bg-slate-400/15 dark:text-slate-200',
    ];

    $statusDots = [
        'leaf' => 'bg-leaf-500',
        'carrot' => 'bg-carrot-500',
        'sun' => 'bg-sun-400',
        'berry' => 'bg-berry-500',
        'grape' => 'bg-grape-500',
        'sky' => 'bg-sky-500',
        'teal' => 'bg-teal-500',
        'lime' => 'bg-lime-500',
        'pink' => 'bg-pink-500',
        'indigo' => 'bg-indigo-500',
        'slate' => 'bg-slate-400',
    ];

    $sizes = [
        'xs' => 'size-6 text-[10px]',
        'sm' => 'size-8 text-xs',
        'md' => 'size-10 text-sm',
        'lg' => 'size-12 text-base',
        'xl' => 'size-16 text-xl',
        '2xl' => 'size-20 text-2xl',
    ];

    $dotSizes = [
        'xs' => 'size-1.5',
        'sm' => 'size-2',
        'md' => 'size-2.5',
        'lg' => 'size-3',
        'xl' => 'size-3.5',
        '2xl' => 'size-4',
    ];

    $size = array_key_exists($size, $sizes) ? $size : 'md';
    $cleanName = trim((string) $name);
    $words = preg_split('/\s+/u', $cleanName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = match (count($words)) {
        0 => '?',
        1 => mb_strtoupper(mb_substr($words[0], 0, 2)),
        default => mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[count($words) - 1], 0, 1)),
    };
    $toneKeys = ['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo'];
    $tone = $cleanName === '' ? 'slate' : $toneKeys[crc32(mb_strtolower($cleanName)) % count($toneKeys)];
@endphp

<span
    {{ $attributes->class(['relative inline-grid shrink-0 place-items-center rounded-full font-semibold select-none', $sizes[$size], $tones[$tone] => ! $src]) }}
    @if ($decorative) aria-hidden="true" @else role="img" aria-label="{{ $cleanName }}" @endif
    data-tone="{{ $tone }}"
>
    @if ($src)
        <img src="{{ $src }}" alt="" class="size-full rounded-full object-cover" loading="lazy">
    @else
        <span class="leading-none">{{ $initials }}</span>
    @endif

    @if ($status && array_key_exists($status, $statusDots))
        <span @class(['absolute right-0 bottom-0 rounded-full ring-2 ring-white dark:ring-ink-900', $statusDots[$status], $dotSizes[$size]])></span>
    @endif
</span>
