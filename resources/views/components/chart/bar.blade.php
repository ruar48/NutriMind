@props([
    'labels' => [],
    'series' => [],
    'format' => 'number',
    'max' => null,
    'valueLabels' => 'max',
    'height' => 'md',
    'label' => 'Bar chart',
    'xLabel' => 'Category',
    'emptyText' => 'No data to show yet.',
])

@php
    $heights = ['sm' => 'h-40', 'md' => 'h-56', 'lg' => 'h-72'];
    $heightClass = $heights[$height] ?? $heights['md'];
    $paletteKeys = ['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate'];

    $labels = array_values(array_map(fn ($text) => (string) $text, (array) $labels));
    $count = count($labels);

    $groups = collect($series)->values()->take(3)->map(function (array $item, int $index) use ($count, $paletteKeys) {
        $raw = array_values((array) ($item['data'] ?? []));
        $values = [];
        for ($i = 0; $i < $count; $i++) {
            $values[] = isset($raw[$i]) && is_numeric($raw[$i]) ? max(0.0, (float) $raw[$i]) : null;
        }

        $viz = isset($item['color']) && in_array($item['color'], $paletteKeys, true) ? $item['color'] : null;
        if ($viz === null) {
            $slot = (int) ($item['slot'] ?? $index + 1);
            $viz = $slot >= 1 && $slot <= 8 ? (string) $slot : 'other';
        }

        return ['name' => (string) ($item['name'] ?? 'Series '.($index + 1)), 'values' => $values, 'viz' => $viz];
    });

    $numbers = $groups->flatMap(fn (array $group) => $group['values'])->filter(fn ($value) => $value !== null)->values();
    $isEmpty = $count === 0 || $numbers->isEmpty();

    $formatValue = function (?float $value) use ($format): string {
        if ($value === null) {
            return '—';
        }
        $text = number_format($value, 1);
        $text = str_ends_with($text, '.0') ? substr($text, 0, -2) : $text;

        return $format === 'percent' ? $text.'%' : $text;
    };

    $ticks = [];
    $bars = [];
    $rows = [];
    $payload = [];

    if (! $isEmpty) {
        $high = (float) $numbers->max();

        if ($max !== null || $format === 'percent') {
            $domainMax = $max !== null ? max((float) $max, 0.000001) : max(100.0, $high);
            $step = $domainMax / 4;
        } else {
            $integers = $numbers->every(fn ($value) => floor($value) === $value);
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
        }

        for ($tick = 0.0; $tick <= $domainMax + $step / 2; $tick += $step) {
            $ticks[] = ['y' => round(100 - $tick / $domainMax * 100, 3), 'text' => $formatValue(round($tick, 4)), 'isBase' => $tick < 1e-9];
        }

        $maxValue = (float) $numbers->max();
        $labelledMax = false;

        foreach ($labels as $index => $text) {
            $cells = [];
            foreach ($groups as $group) {
                $value = $group['values'][$index];
                $showLabel = match ($valueLabels) {
                    'all' => $value !== null,
                    'max' => $groups->count() === 1 && ! $labelledMax && $value !== null && $value === $maxValue,
                    default => false,
                };
                if ($showLabel && $valueLabels === 'max') {
                    $labelledMax = true;
                }
                $cells[] = [
                    'height' => $value === null ? 0 : round(min(100, $value / $domainMax * 100), 3),
                    'viz' => $group['viz'],
                    'text' => $formatValue($value),
                    'showLabel' => $showLabel,
                    'isMissing' => $value === null,
                ];
            }
            $bars[] = ['label' => $text, 'cells' => $cells];
            $rows[] = array_merge([$text], $groups->map(fn (array $group) => $formatValue($group['values'][$index]))->all());
        }

        $desktopStep = max(1, (int) ceil($count / 12));
        $mobileStep = max(1, (int) ceil($count / 6));

        $payload = [
            'format' => $format,
            'labels' => $labels,
            'series' => $groups->map(fn (array $group) => ['name' => $group['name'], 'viz' => $group['viz'], 'values' => $group['values']])->all(),
        ];
    }

    $legend = $groups->count() >= 2 ? $groups->map(fn (array $group) => ['label' => $group['name'], 'viz' => $group['viz']])->all() : [];
    $columns = array_merge([$xLabel], $groups->pluck('name')->all());
@endphp

<x-chart.frame
    type="bar"
    :label="$label"
    :payload="$payload"
    :legend="$legend"
    :columns="$columns"
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
            aria-label="{{ $label }}. Use the left and right arrow keys to read each value."
            @class(['relative rounded-sm focus-visible:outline-offset-4', $heightClass])
        >
            @foreach ($ticks as $tick)
                <div aria-hidden="true" @class(['pointer-events-none absolute inset-x-0 h-px', 'bg-(--chart-axis)' => $tick['isBase'], 'bg-(--chart-grid)' => ! $tick['isBase']]) style="top: {{ $tick['y'] }}%"></div>
            @endforeach

            <div class="absolute inset-0 flex items-end">
                @foreach ($bars as $index => $bar)
                    <div data-chart-hit data-index="{{ $index }}" class="relative flex h-full min-w-0 flex-1 items-end justify-center gap-[2px] px-1">
                        @foreach ($bar['cells'] as $cell)
                            <div
                                data-chart-mark
                                data-index="{{ $index }}"
                                data-viz="{{ $cell['viz'] }}"
                                data-bar-fill
                                @class(['relative w-full max-w-6 rounded-t-[4px] bg-(--viz)', 'opacity-0' => $cell['isMissing']])
                                style="height: {{ $cell['height'] > 0 ? 'max(2px, '.$cell['height'].'%)' : '0' }}"
                            >
                                @if ($cell['showLabel'])
                                    <span class="absolute bottom-full left-1/2 mb-1 -translate-x-1/2 text-[11px] leading-none font-semibold whitespace-nowrap text-ink-800 tabular-nums dark:text-ink-100">{{ $cell['text'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div data-chart-tooltip hidden role="status" aria-live="polite" class="absolute z-10 min-w-32 rounded-xl bg-white px-3 py-2 shadow-lift ring-1 ring-ink-900/8 dark:bg-ink-800 dark:ring-white/10"></div>
        </div>

        <div></div>
        <div class="flex text-[11px] text-(--chart-muted)" aria-hidden="true">
            @foreach ($bars as $index => $bar)
                <span @class([
                    'min-w-0 flex-1 truncate px-0.5 pt-1.5 text-center leading-none',
                    '@max-lg:invisible' => $index % $mobileStep !== 0,
                    '@lg:invisible' => $index % $desktopStep !== 0,
                ]) title="{{ $bar['label'] }}">{{ $bar['label'] }}</span>
            @endforeach
        </div>
    </div>
</x-chart.frame>
