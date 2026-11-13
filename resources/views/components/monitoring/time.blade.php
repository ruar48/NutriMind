@props([
    'value' => null,
    'empty' => '—',
    'relative' => true,
])

@php
    $moment = $value instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::instance($value) : null;
@endphp

@if ($moment)
    <time
        datetime="{{ $moment->toIso8601String() }}"
        title="{{ $moment->format('M j, Y g:i A') }}"
        {{ $attributes->class('whitespace-nowrap') }}
    >{{ $relative ? $moment->diffForHumans() : $moment->format('M j, Y g:i A') }}</time>
@else
    <span {{ $attributes->class('whitespace-nowrap text-ink-400 dark:text-ink-500') }}>{{ $empty }}</span>
@endif
