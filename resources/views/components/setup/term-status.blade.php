{{--
    Status badge of a quarter: Current quarter, Covers today (not current), Upcoming or Ended.
    Props: term (AcademicTerm), size (badge size).
--}}
@props([
    'term',
    'size' => 'md',
])

@php
    $today = today()->format('Y-m-d');

    [$state, $label, $color, $icon] = match (true) {
        $term->is_active => ['current', 'Current quarter', 'leaf', 'circle-check'],
        $term->starts_on->format('Y-m-d') > $today => ['upcoming', 'Upcoming', 'sky', 'calendar'],
        $term->ends_on->format('Y-m-d') < $today => ['ended', 'Ended', 'slate', 'history'],
        default => ['today', 'Covers today · not current', 'sun', 'triangle-alert'],
    };
@endphp

<x-badge :color="$color" :icon="$icon" :size="$size" data-term-status="{{ $state }}" {{ $attributes }}>{{ $label }}</x-badge>
