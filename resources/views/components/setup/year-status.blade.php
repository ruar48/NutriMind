{{--
    Status badge of a school year: Active, Upcoming, Ended, or "Not active" while its dates include today.
    Props: schoolYear (SchoolYear), size (badge size).
--}}
@props([
    'schoolYear',
    'size' => 'md',
])

@php
    $today = today()->format('Y-m-d');

    [$state, $label, $color, $icon] = match (true) {
        $schoolYear->is_active => ['active', 'Active', 'leaf', 'circle-check'],
        $schoolYear->starts_on->format('Y-m-d') > $today => ['upcoming', 'Upcoming', 'sky', 'calendar'],
        $schoolYear->ends_on->format('Y-m-d') < $today => ['ended', 'Ended', 'slate', 'history'],
        default => ['inactive', 'In session · not active', 'sun', 'triangle-alert'],
    };
@endphp

<x-badge :color="$color" :icon="$icon" :size="$size" data-year-status="{{ $state }}" {{ $attributes }}>{{ $label }}</x-badge>
