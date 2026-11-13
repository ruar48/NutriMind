@props([
    'padding' => 'md',
    'href' => null,
    'interactive' => false,
])

@php
    $paddings = [
        'none' => '',
        'sm' => 'p-4',
        'md' => 'p-5 sm:p-6',
        'lg' => 'p-6 sm:p-8',
    ];

    $padding = array_key_exists($padding, $paddings) ? $padding : 'md';
    $isInteractive = $interactive || $href !== null;
    $tag = $href !== null ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href !== null) href="{{ $href }}" @endif
    {{ $attributes->class([
        'relative block min-w-0 rounded-2xl bg-white shadow-card ring-1 ring-ink-900/5 dark:bg-ink-900 dark:shadow-none dark:ring-white/8',
        'transition duration-200 ease-out hover:-translate-y-px hover:shadow-lift hover:ring-ink-900/10 focus-visible:outline-2 focus-visible:outline-offset-2 dark:hover:ring-white/15' => $isInteractive,
    ]) }}
    data-print-avoid-break
>
    @isset($header)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-900/5 px-5 py-4 sm:px-6 dark:border-white/8">
            {{ $header }}
        </div>
    @endisset

    <div class="{{ $paddings[$padding] }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="rounded-b-2xl border-t border-ink-900/5 bg-ink-50/60 px-5 py-3 sm:px-6 dark:border-white/8 dark:bg-white/3">
            {{ $footer }}
        </div>
    @endisset
</{{ $tag }}>
