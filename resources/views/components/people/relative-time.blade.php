@props([
    'at' => null,
    'empty' => 'Never',
])

@if ($at)
    <time datetime="{{ $at->toIso8601String() }}" title="{{ $at->format('M j, Y g:i A') }}" {{ $attributes->class('whitespace-nowrap') }}>{{ $at->diffForHumans() }}</time>
@else
    <span {{ $attributes->class('whitespace-nowrap text-ink-400 dark:text-ink-500') }}>{{ $empty }}</span>
@endif
