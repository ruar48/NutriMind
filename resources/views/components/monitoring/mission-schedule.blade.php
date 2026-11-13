@props([
    'mission',
    'size' => 'sm',
])

@php
    $today = now()->startOfDay();
    $startsOn = $mission->starts_on;
    $endsOn = $mission->ends_on;

    [$label, $color, $icon] = match (true) {
        $startsOn === null && $endsOn === null => [null, null, null],
        $endsOn !== null && $endsOn->lt($today) => ['Ended '.$endsOn->format('M j, Y'), 'slate', 'calendar-check'],
        $startsOn !== null && $startsOn->gt($today) => ['Starts '.$startsOn->format('M j, Y'), 'sky', 'calendar-days'],
        $endsOn !== null => ['Running until '.$endsOn->format('M j, Y'), 'leaf', 'calendar-range'],
        default => ['Running since '.$startsOn->format('M j, Y'), 'leaf', 'calendar-range'],
    };
@endphp

@if ($label)
    <x-badge :color="$color" :icon="$icon" :size="$size" {{ $attributes }}>{{ $label }}</x-badge>
@endif
