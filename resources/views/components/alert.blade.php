@props([
    'variant' => 'info',
    'title' => null,
    'icon' => null,
    'dismissible' => false,
])

@php
    $variants = [
        'info' => [
            'box' => 'bg-sky-50 ring-sky-600/15 dark:bg-sky-400/10 dark:ring-sky-400/20',
            'icon' => 'text-sky-600 dark:text-sky-300',
            'title' => 'text-sky-950 dark:text-sky-100',
            'body' => 'text-sky-900/85 dark:text-sky-100/80',
            'close' => 'text-sky-700 hover:bg-sky-600/10 dark:text-sky-200 dark:hover:bg-sky-400/15',
            'glyph' => 'info',
            'role' => 'status',
        ],
        'success' => [
            'box' => 'bg-leaf-50 ring-leaf-600/15 dark:bg-leaf-400/10 dark:ring-leaf-400/20',
            'icon' => 'text-leaf-600 dark:text-leaf-300',
            'title' => 'text-leaf-950 dark:text-leaf-100',
            'body' => 'text-leaf-900/85 dark:text-leaf-100/80',
            'close' => 'text-leaf-700 hover:bg-leaf-600/10 dark:text-leaf-200 dark:hover:bg-leaf-400/15',
            'glyph' => 'circle-check',
            'role' => 'status',
        ],
        'warning' => [
            'box' => 'bg-sun-50 ring-sun-600/20 dark:bg-sun-400/10 dark:ring-sun-400/20',
            'icon' => 'text-sun-700 dark:text-sun-300',
            'title' => 'text-sun-950 dark:text-sun-100',
            'body' => 'text-sun-900/90 dark:text-sun-100/80',
            'close' => 'text-sun-800 hover:bg-sun-600/10 dark:text-sun-200 dark:hover:bg-sun-400/15',
            'glyph' => 'triangle-alert',
            'role' => 'alert',
        ],
        'danger' => [
            'box' => 'bg-berry-50 ring-berry-600/15 dark:bg-berry-400/10 dark:ring-berry-400/20',
            'icon' => 'text-berry-600 dark:text-berry-300',
            'title' => 'text-berry-950 dark:text-berry-100',
            'body' => 'text-berry-900/85 dark:text-berry-100/80',
            'close' => 'text-berry-700 hover:bg-berry-600/10 dark:text-berry-200 dark:hover:bg-berry-400/15',
            'glyph' => 'circle-alert',
            'role' => 'alert',
        ],
    ];

    $tone = $variants[$variant] ?? $variants['info'];
@endphp

<div
    role="{{ $tone['role'] }}"
    {{ $attributes->class(['flex min-w-0 gap-3 rounded-2xl p-4 ring-1 ring-inset', $tone['box']]) }}
    @if ($dismissible) data-dismissible @endif
>
    <x-icon :name="$icon ?? $tone['glyph']" @class(['mt-0.5 size-5', $tone['icon']]) />
    <div class="min-w-0 flex-1 text-sm">
        @if ($title)
            <p @class(['font-semibold', $tone['title']])>{{ $title }}</p>
        @endif
        @if ($slot->hasActualContent())
            <div @class(['[&_a]:font-semibold [&_a]:underline [&_a]:underline-offset-2', $tone['body'], 'mt-1' => $title])>{{ $slot }}</div>
        @endif
    </div>
    @if ($dismissible)
        <button type="button" data-dismiss data-js-only @class(['-m-1.5 grid size-8 shrink-0 place-items-center rounded-lg transition pointer-coarse:size-11', $tone['close']])>
            <x-icon name="x" class="size-4" />
            <span class="sr-only">Dismiss</span>
        </button>
    @endif
</div>
