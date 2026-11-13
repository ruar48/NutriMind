{{--
    A compact inclusive date range: "Jun 15 – Aug 28, 2026" within one year, "Jun 15, 2026 – Apr 16, 2027" across years.
    Props: from, to (DateTimeInterface).
--}}
@props([
    'from',
    'to',
])

@php
    $sameYear = $from->format('Y') === $to->format('Y');
@endphp

<span {{ $attributes->class('whitespace-nowrap tabular-nums') }}><time datetime="{{ $from->format('Y-m-d') }}">{{ $from->format($sameYear ? 'M j' : 'M j, Y') }}</time> – <time datetime="{{ $to->format('Y-m-d') }}">{{ $to->format('M j, Y') }}</time></span>
