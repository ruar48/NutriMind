@props([
    'value' => 0,
    'format' => 'number',
    'goodWhen' => 'up',
    'label' => null,
])

@php
    $delta = (float) $value;
    $direction = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');
    $isGood = $direction === 'flat' ? null : $direction === $goodWhen;

    $magnitude = number_format(abs($delta), 1);
    $magnitude = str_ends_with($magnitude, '.0') ? substr($magnitude, 0, -2) : $magnitude;
    $suffix = match ($format) {
        'percent' => '%',
        'points' => ' pts',
        default => '',
    };
    $sign = match ($direction) {
        'up' => '+',
        'down' => '−',
        default => '±',
    };
    $text = $sign.$magnitude.$suffix;
    $spoken = match ($direction) {
        'up' => 'Up '.$magnitude.$suffix,
        'down' => 'Down '.$magnitude.$suffix,
        default => 'No change',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex w-fit items-center gap-1 rounded-full px-1.5 py-0.5 text-xs font-semibold whitespace-nowrap',
    'bg-leaf-50 text-leaf-700 dark:bg-leaf-400/10 dark:text-leaf-300' => $isGood === true,
    'bg-berry-50 text-berry-700 dark:bg-berry-400/10 dark:text-berry-300' => $isGood === false,
    'bg-ink-900/5 text-ink-600 dark:bg-white/8 dark:text-ink-300' => $isGood === null,
]) }} data-direction="{{ $direction }}">
    <x-icon :name="match ($direction) { 'up' => 'trending-up', 'down' => 'trending-down', default => 'minus' }" class="size-3.5" stroke-width="2.25" />
    <span aria-hidden="true" class="tabular-nums">{{ $text }}</span>
    <span class="sr-only">{{ $spoken }}{{ $label ? ' '.$label : '' }}</span>
    @if ($label)
        <span aria-hidden="true" class="font-medium text-ink-500 dark:text-ink-400">{{ $label }}</span>
    @endif
</span>
