@props([
    'names' => [],
    'max' => 4,
    'size' => 'sm',
])

@php
    $names = collect($names)->map(fn ($name) => trim((string) $name))->filter()->values();
    $visible = $names->take(max(1, (int) $max));
    $remaining = $names->count() - $visible->count();
    $overflowSizes = [
        'xs' => 'size-6 text-[10px]',
        'sm' => 'size-8 text-[11px]',
        'md' => 'size-10 text-xs',
        'lg' => 'size-12 text-sm',
    ];
    $summary = $remaining > 0
        ? $visible->implode(', ').' and '.$remaining.' '.\Illuminate\Support\Str::plural('other', $remaining)
        : $visible->join(', ', ' and ');
@endphp

@if ($names->isNotEmpty())
    <div {{ $attributes->class('flex items-center -space-x-2') }} role="img" aria-label="{{ $summary }}">
        @foreach ($visible as $name)
            <x-avatar :name="$name" :size="$size" class="ring-2 ring-white dark:ring-ink-900" />
        @endforeach
        @if ($remaining > 0)
            <span aria-hidden="true" @class(['relative inline-grid shrink-0 place-items-center rounded-full bg-ink-100 font-semibold text-ink-600 tabular-nums ring-2 ring-white dark:bg-ink-800 dark:text-ink-300 dark:ring-ink-900', $overflowSizes[$size] ?? $overflowSizes['sm']])>+{{ $remaining }}</span>
        @endif
    </div>
@endif
