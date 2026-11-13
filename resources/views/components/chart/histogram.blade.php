@props([
    'buckets' => [],
    'format' => 'number',
    'seriesName' => 'Learners',
    'valueLabels' => 'max',
    'color' => null,
    'height' => 'md',
    'label' => 'Distribution',
    'xLabel' => 'Range',
    'emptyText' => 'No results to show yet.',
])

@php
    $heights = ['sm' => 'h-40', 'md' => 'h-56', 'lg' => 'h-72'];
    $heightClass = $heights[$height] ?? $heights['md'];
    $paletteKeys = ['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate'];
    $defaultViz = $color !== null && in_array($color, $paletteKeys, true) ? $color : '1';

    $items = collect($buckets)->values()->map(fn (array $bucket) => [
        'label' => (string) ($bucket['label'] ?? ''),
        'value' => isset($bucket['value']) && is_numeric($bucket['value']) ? max(0.0, (float) $bucket['value']) : 0.0,
        'viz' => isset($bucket['color']) && in_array($bucket['color'], $paletteKeys, true) ? $bucket['color'] : $defaultViz,
    ]);

    $count = $items->count();
    $isEmpty = $count === 0 || $items->sum('value') <= 0;

    $formatValue = function (?float $value) use ($format): string {
        if ($value === null) {
            return '—';
        }
        $text = number_format($value, 1);
        $text = str_ends_with($text, '.0') ? substr($text, 0, -2) : $text;

        return $format === 'percent' ? $text.'%' : $text;
    };

    $ticks = [];
    $rows = [];
    $payload = [];
    $columnsData = [];

    if (! $isEmpty) {
        $high = (float) $items->max('value');
        $integers = $items->every(fn (array $item) => floor($item['value']) === $item['value']);
        $rough = max($high / 4, $integers ? 0.25 : 0.000001);
        $magnitude = 10 ** floor(log10($rough));
        $fraction = $rough / $magnitude;
        $step = match (true) {
            $fraction <= 1 => 1,
            $fraction <= 2 => 2,
            $fraction <= 2.5 => 2.5,
            $fraction <= 5 => 5,
            default => 10,
        } * $magnitude;
        if ($integers) {
            $step = max(1.0, round($step));
        }
        $domainMax = max($step, ceil($high / $step) * $step);

        for ($tick = 0.0; $tick <= $domainMax + $step / 2; $tick += $step) {
            $ticks[] = ['y' => round(100 - $tick / $domainMax * 100, 3), 'text' => $formatValue(round($tick, 4)), 'isBase' => $tick < 1e-9];
        }

        $labelledMax = false;
        foreach ($items as $index => $item) {
            $showLabel = match ($valueLabels) {
                'all' => $item['value'] > 0,
                'max' => ! $labelledMax && $item['value'] === $high,
                default => false,
            };
            if ($showLabel && $valueLabels === 'max') {
                $labelledMax = true;
            }
            $columnsData[] = $item + [
                'height' => round(min(100, $item['value'] / $domainMax * 100), 3),
                'text' => $formatValue($item['value']),
                'showLabel' => $showLabel,
            ];
            $rows[] = [$item['label'], $formatValue($item['value'])];
        }

        $payload = [
            'format' => $format,
            'labels' => $items->pluck('label')->all(),
            'series' => [['name' => $seriesName, 'viz' => $defaultViz, 'values' => $items->pluck('value')->all()]],
        ];
    }
@endphp

<x-chart.frame
    type="histogram"
    :label="$label"
    :payload="$payload"
    :columns="[$xLabel, $seriesName]"
    :rows="$rows"
    :empty="$isEmpty"
    :empty-text="$emptyText"
    :empty-height="$heightClass"
    {{ $attributes }}
>
    <div class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-2">
        <div @class(['relative w-9 text-right text-[11px] text-(--chart-muted) tabular-nums', $heightClass]) aria-hidden="true">
            @foreach ($ticks as $tick)
                <span class="absolute right-0 -translate-y-1/2 leading-none" style="top: {{ $tick['y'] }}%">{{ $tick['text'] }}</span>
            @endforeach
        </div>

        <div
            data-chart-plot
            tabindex="0"
            role="group"
            aria-roledescription="chart"
            aria-label="{{ $label }}. Use the left and right arrow keys to read each range."
            @class(['relative rounded-sm focus-visible:outline-offset-4', $heightClass])
        >
            @foreach ($ticks as $tick)
                <div aria-hidden="true" @class(['pointer-events-none absolute inset-x-0 h-px', 'bg-(--chart-axis)' => $tick['isBase'], 'bg-(--chart-grid)' => ! $tick['isBase']]) style="top: {{ $tick['y'] }}%"></div>
            @endforeach

            <div class="absolute inset-0 flex items-end gap-[2px]">
                @foreach ($columnsData as $index => $column)
                    <div data-chart-hit data-index="{{ $index }}" class="relative flex h-full min-w-0 flex-1 items-end">
                        <div
                            data-chart-mark
                            data-index="{{ $index }}"
                            data-viz="{{ $column['viz'] }}"
                            data-bar-fill
                            class="relative w-full rounded-t-[4px] bg-(--viz)"
                            style="height: {{ $column['height'] > 0 ? 'max(2px, '.$column['height'].'%)' : '0' }}"
                        >
                            @if ($column['showLabel'])
                                <span class="absolute bottom-full left-1/2 mb-1 -translate-x-1/2 text-[11px] leading-none font-semibold whitespace-nowrap text-ink-800 tabular-nums dark:text-ink-100">{{ $column['text'] }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div data-chart-tooltip hidden role="status" aria-live="polite" class="absolute z-10 min-w-32 rounded-xl bg-white px-3 py-2 shadow-lift ring-1 ring-ink-900/8 dark:bg-ink-800 dark:ring-white/10"></div>
        </div>

        <div></div>
        <div class="flex gap-[2px] text-[11px] text-(--chart-muted)" aria-hidden="true">
            @foreach ($columnsData as $index => $column)
                <span @class(['min-w-0 flex-1 truncate pt-1.5 text-center leading-none', '@max-md:invisible' => $count > 6 && $index % 2 === 1]) title="{{ $column['label'] }}">{{ $column['label'] }}</span>
            @endforeach
        </div>
    </div>
</x-chart.frame>
