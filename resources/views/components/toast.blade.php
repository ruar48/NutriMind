@props([
    'variant' => 'success',
    'message' => '',
])

@php
    $variants = [
        'success' => ['icon' => 'circle-check', 'tile' => 'bg-leaf-50 text-leaf-600 dark:bg-leaf-400/15 dark:text-leaf-300', 'role' => 'status', 'bar' => 'bg-leaf-500'],
        'danger' => ['icon' => 'circle-alert', 'tile' => 'bg-berry-50 text-berry-600 dark:bg-berry-400/15 dark:text-berry-300', 'role' => 'alert', 'bar' => 'bg-berry-500'],
    ];

    $tone = $variants[$variant] ?? $variants['success'];
@endphp

<div
    data-toast
    role="{{ $tone['role'] }}"
    {{ $attributes->class('pointer-events-auto relative flex animate-toast-in items-start gap-3 overflow-hidden rounded-2xl bg-white p-4 pl-5 shadow-lift ring-1 ring-ink-900/8 transition duration-200 data-leaving:translate-x-4 data-leaving:opacity-0 dark:bg-ink-800 dark:ring-white/10') }}
>
    <span aria-hidden="true" @class(['absolute inset-y-0 left-0 w-1', $tone['bar']])></span>
    <span @class(['grid size-8 shrink-0 place-items-center rounded-full', $tone['tile']])>
        <x-icon :name="$tone['icon']" class="size-5" />
    </span>
    <p data-toast-message class="min-w-0 flex-1 pt-1.5 text-sm font-medium text-ink-800 dark:text-ink-100">{{ $message }}</p>
    <button type="button" data-toast-close data-js-only class="-m-1 grid size-8 shrink-0 place-items-center rounded-lg text-ink-400 transition hover:bg-ink-900/5 hover:text-ink-700 pointer-coarse:size-11 dark:hover:bg-white/8 dark:hover:text-ink-200">
        <x-icon name="x" class="size-4" />
        <span class="sr-only">Dismiss notification</span>
    </button>
</div>
