@props([
    'data' => [],
    'labels' => [],
    'color' => 'leaf',
    'format' => 'number',
    'label' => 'Trend',
    'emptyText' => 'No trend yet',
])

@php
    $paletteKeys = ['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate'];
    $accent = in_array($color, $paletteKeys, true) ? $color : 'leaf';
    $values = array_values(array_map(fn ($value) => (float) $value, array_filter((array) $data, fn ($value) => is_numeric($value))));
    $count = count($values);
    $labels = array_values(array_map(fn ($text) => (string) $text, (array) $labels));
    $labels = count($labels) === $count ? $labels : array_fill(0, $count, '');

    $formatValue = function (float $value) use ($format): string {
        $text = number_format($value, 1);
        $text = str_ends_with($text, '.0') ? substr($text, 0, -2) : $text;

        return $format === 'percent' ? $text.'%' : $text;
    };

    $path = '';
    $end = null;
    $payload = [];

    if ($count >= 2) {
        $low = min($values);
        $high = max($values);
        $padding = ($high - $low) * 0.12 ?: 1;
        $domainMin = $low - $padding;
        $domainMax = $high + $padding;
        $xFor = fn (int $index): float => round($index / ($count - 1) * 100, 3);
        $yFor = fn (float $value): float => round(100 - ($value - $domainMin) / ($domainMax - $domainMin) * 100, 3);

        $path = 'M'.implode(' L', array_map(fn (int $index) => $xFor($index).' '.$yFor($values[$index]), array_keys($values)));
        $end = ['x' => $xFor($count - 1), 'y' => $yFor($values[$count - 1])];
        $payload = [
            'format' => $format,
            'labels' => $labels,
            'series' => [['name' => $label, 'viz' => $accent, 'values' => $values]],
            'min' => $domainMin,
            'max' => $domainMax,
        ];
    }
@endphp

@if ($count < 2)
    <p {{ $attributes->class('text-xs text-ink-400 dark:text-ink-500') }} data-sparkline-empty>{{ $emptyText }}</p>
@else
    <div {{ $attributes->class('relative') }} data-chart="sparkline">
        <script type="application/json" data-chart-data>@json($payload)</script>
        <div data-chart-plot tabindex="0" role="group" aria-roledescription="sparkline" aria-label="{{ $label }}: from {{ $formatValue($values[0]) }} to {{ $formatValue($values[$count - 1]) }}" class="relative h-10 touch-pan-y rounded-sm focus-visible:outline-offset-4">
            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 size-full overflow-visible" aria-hidden="true">
                <path d="{{ $path }}" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" class="stroke-ink-300 dark:stroke-ink-600" />
            </svg>
            <span aria-hidden="true" data-viz="{{ $accent }}" class="pointer-events-none absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-(--viz) ring-2 ring-(--chart-surface)" style="left: {{ $end['x'] }}%; top: {{ $end['y'] }}%"></span>
            <div data-chart-crosshair hidden class="pointer-events-none absolute inset-y-0 w-px -translate-x-1/2 bg-(--chart-axis)"></div>
            <div data-chart-tooltip hidden role="status" aria-live="polite" class="absolute z-10 min-w-24 rounded-xl bg-white px-3 py-2 shadow-lift ring-1 ring-ink-900/8 dark:bg-ink-800 dark:ring-white/10"></div>
        </div>
        <span class="sr-only">Values: {{ implode(', ', array_map($formatValue, $values)) }}</span>
    </div>
@endif
