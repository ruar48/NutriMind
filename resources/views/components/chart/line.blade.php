@props([
    'labels' => [],
    'series' => [],
    'format' => 'number',
    'min' => null,
    'max' => null,
    'target' => null,
    'targetLabel' => 'Target',
    'area' => null,
    'height' => 'md',
    'label' => 'Line chart',
    'xLabel' => 'Period',
    'emptyText' => 'No data to show yet.',
])

@php
    $heights = ['sm' => 'h-40', 'md' => 'h-56', 'lg' => 'h-72'];
    $heightClass = $heights[$height] ?? $heights['md'];
    $paletteKeys = ['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate'];

    $labels = array_values(array_map(fn ($text) => (string) $text, (array) $labels));
    $count = count($labels);

    $lines = collect($series)->values()->map(function (array $item, int $index) use ($count, $paletteKeys) {
        $raw = array_values((array) ($item['data'] ?? []));
        $values = [];
        for ($i = 0; $i < $count; $i++) {
            $values[] = isset($raw[$i]) && is_numeric($raw[$i]) ? (float) $raw[$i] : null;
        }

        $viz = isset($item['color']) && in_array($item['color'], $paletteKeys, true) ? $item['color'] : null;
        if ($viz === null) {
            $slot = (int) ($item['slot'] ?? $index + 1);
            $viz = $slot >= 1 && $slot <= 8 ? (string) $slot : 'other';
        }

        return ['name' => (string) ($item['name'] ?? 'Series '.($index + 1)), 'values' => $values, 'viz' => $viz];
    });

    $numbers = $lines->flatMap(fn (array $line) => $line['values'])->filter(fn ($value) => $value !== null)->values();
    $isEmpty = $count === 0 || $numbers->isEmpty();

    $formatValue = function (?float $value) use ($format): string {
        if ($value === null) {
            return '—';
        }
        $text = number_format($value, 1);
        $text = str_ends_with($text, '.0') ? substr($text, 0, -2) : $text;

        return $format === 'percent' ? $text.'%' : $text;
    };

    $payload = [];
    $ticks = [];
    $rows = [];
    $paths = [];
    $endPoints = [];
    $isolated = [];
    $xLabels = [];
    $directLabels = false;
    $targetY = null;

    if (! $isEmpty) {
        $high = (float) $numbers->max();
        if (is_numeric($target)) {
            $high = max($high, (float) $target);
        }
        $low = $min !== null ? (float) $min : min(0.0, (float) $numbers->min());

        if ($max !== null || $format === 'percent') {
            $domainMin = $low;
            $domainMax = $max !== null ? (float) $max : max(100.0, $high);
            if ($domainMax <= $domainMin) {
                $domainMax = $domainMin + 4;
            }
            $step = ($domainMax - $domainMin) / 4;
        } else {
            $integers = $numbers->every(fn ($value) => floor($value) === $value);
            $rough = max(($high - $low) / 4, $integers ? 0.25 : 0.000001);
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
            $domainMin = floor($low / $step) * $step;
            $domainMax = ceil($high / $step) * $step;
            if ($domainMax <= $domainMin) {
                $domainMax = $domainMin + $step * 4;
            }
        }

        $range = $domainMax - $domainMin ?: 1;
        $xFor = fn (int $index): float => $count === 1 ? 50.0 : round($index / ($count - 1) * 100, 3);
        $yFor = fn (float $value): float => round(max(0, min(100, 100 - (($value - $domainMin) / $range) * 100)), 3);
        $baseline = $yFor(max($domainMin, min(0.0, $domainMax)));

        for ($tick = $domainMin; $tick <= $domainMax + $step / 2; $tick += $step) {
            $ticks[] = ['y' => $yFor($tick), 'text' => $formatValue(round($tick, 4)), 'isBase' => abs($tick - $domainMin) < 1e-9];
        }

        $useArea = $area ?? $lines->count() === 1;

        foreach ($lines as $line) {
            $segments = [];
            $current = [];
            foreach ($line['values'] as $index => $value) {
                if ($value === null) {
                    if ($current !== []) {
                        $segments[] = $current;
                        $current = [];
                    }

                    continue;
                }
                $current[] = [$xFor($index), $yFor($value)];
            }
            if ($current !== []) {
                $segments[] = $current;
            }

            $lineD = '';
            $areaD = '';
            foreach ($segments as $points) {
                if (count($points) === 1) {
                    $isolated[] = ['x' => $points[0][0], 'y' => $points[0][1], 'viz' => $line['viz']];

                    continue;
                }
                $joined = implode(' L', array_map(fn (array $point) => $point[0].' '.$point[1], $points));
                $lineD .= 'M'.$joined.' ';
                $first = $points[0];
                $last = $points[count($points) - 1];
                $areaD .= 'M'.$first[0].' '.$baseline.' L'.$joined.' L'.$last[0].' '.$baseline.' Z ';
            }
            $paths[] = ['line' => trim($lineD), 'area' => $useArea ? trim($areaD) : '', 'viz' => $line['viz']];

            $lastIndex = null;
            foreach ($line['values'] as $index => $value) {
                if ($value !== null) {
                    $lastIndex = $index;
                }
            }
            if ($lastIndex !== null) {
                $endPoints[] = [
                    'x' => $xFor($lastIndex),
                    'y' => $yFor($line['values'][$lastIndex]),
                    'viz' => $line['viz'],
                    'name' => $line['name'],
                    'value' => $formatValue($line['values'][$lastIndex]),
                ];
            }
        }

        if ($lines->count() <= 4 && $endPoints !== []) {
            $positions = collect($endPoints)->pluck('y')->sort()->values();
            $directLabels = $positions->count() === 1 || $positions->sliding(2)->every(fn ($pair) => $pair->last() - $pair->first() >= 10);
        }

        if (is_numeric($target)) {
            $targetY = $yFor((float) $target);
        }

        $evenlySpaced = function (int $wanted) use ($count): array {
            if ($count <= $wanted) {
                return range(0, $count - 1);
            }

            $indices = [];
            for ($i = 0; $i < $wanted; $i++) {
                $indices[] = (int) round($i * ($count - 1) / ($wanted - 1));
            }

            return array_values(array_unique($indices));
        };
        $onDesktop = $evenlySpaced(8);
        $onMobile = $evenlySpaced(4);

        foreach ($labels as $index => $text) {
            $desktop = in_array($index, $onDesktop, true);
            $mobile = in_array($index, $onMobile, true);
            $xLabels[] = [
                'x' => $xFor($index),
                'text' => $text,
                'visibility' => match (true) {
                    $desktop && $mobile => '',
                    $desktop => '@max-lg:hidden',
                    $mobile => '@lg:hidden',
                    default => 'hidden',
                },
                'edge' => $count === 1 ? '-translate-x-1/2' : ($index === 0 ? '' : ($index === $count - 1 ? '-translate-x-full' : '-translate-x-1/2')),
            ];
        }

        foreach ($labels as $index => $text) {
            $rows[] = array_merge([$text], $lines->map(fn (array $line) => $formatValue($line['values'][$index]))->all());
        }

        $payload = [
            'format' => $format,
            'labels' => $labels,
            'series' => $lines->map(fn (array $line) => ['name' => $line['name'], 'viz' => $line['viz'], 'values' => $line['values']])->all(),
            'min' => $domainMin,
            'max' => $domainMax,
        ];
    }

    $legend = $lines->count() >= 2 ? $lines->map(fn (array $line) => ['label' => $line['name'], 'viz' => $line['viz']])->all() : [];
    if (! $isEmpty && is_numeric($target)) {
        $legend[] = ['label' => $targetLabel.' '.$formatValue((float) $target), 'shape' => 'dash'];
    }
    $columns = array_merge([$xLabel], $lines->pluck('name')->all());
@endphp

<x-chart.frame
    type="line"
    :label="$label"
    :payload="$payload"
    :legend="$legend"
    legend-shape="line"
    :columns="$columns"
    :rows="$rows"
    :empty="$isEmpty"
    :empty-text="$emptyText"
    :empty-height="$heightClass"
    {{ $attributes }}
>
    <div @class(['grid gap-x-2', 'grid-cols-[auto_minmax(0,1fr)]' => ! $directLabels, 'grid-cols-[auto_minmax(0,1fr)] @lg:grid-cols-[auto_minmax(0,1fr)_auto]' => $directLabels])>
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
            @class(['relative touch-pan-y rounded-sm focus-visible:outline-offset-4', $heightClass])
        >
            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 size-full overflow-visible" aria-hidden="true">
                @foreach ($ticks as $tick)
                    <line x1="0" x2="100" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}" vector-effect="non-scaling-stroke" stroke-width="1" @class(['stroke-(--chart-axis)' => $tick['isBase'], 'stroke-(--chart-grid)' => ! $tick['isBase']]) />
                @endforeach
                @if ($targetY !== null)
                    <line x1="0" x2="100" y1="{{ $targetY }}" y2="{{ $targetY }}" vector-effect="non-scaling-stroke" stroke-width="1.5" stroke-dasharray="5 4" class="stroke-(--chart-target)" />
                @endif
            </svg>

            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 size-full overflow-visible" aria-hidden="true" data-line-reveal>
                @foreach ($paths as $path)
                    @if ($path['area'] !== '')
                        <path d="{{ $path['area'] }}" data-viz="{{ $path['viz'] }}" class="fill-(--viz)" fill-opacity="0.1" stroke="none" />
                    @endif
                @endforeach
                @foreach ($paths as $path)
                    @if ($path['line'] !== '')
                        <path d="{{ $path['line'] }}" data-viz="{{ $path['viz'] }}" class="stroke-(--viz)" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                    @endif
                @endforeach
            </svg>

            @foreach ($isolated as $point)
                <span aria-hidden="true" data-viz="{{ $point['viz'] }}" class="pointer-events-none absolute size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-(--viz) ring-2 ring-(--chart-surface)" style="left: {{ $point['x'] }}%; top: {{ $point['y'] }}%"></span>
            @endforeach

            @foreach ($endPoints as $point)
                <span aria-hidden="true" data-viz="{{ $point['viz'] }}" class="pointer-events-none absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-(--viz) ring-2 ring-(--chart-surface)" style="left: {{ $point['x'] }}%; top: {{ $point['y'] }}%"></span>
            @endforeach

            <div data-chart-crosshair hidden class="pointer-events-none absolute inset-y-0 w-px -translate-x-1/2 bg-(--chart-axis)"></div>
            <div data-chart-tooltip hidden role="status" aria-live="polite" class="absolute z-10 min-w-32 rounded-xl bg-white px-3 py-2 shadow-lift ring-1 ring-ink-900/8 dark:bg-ink-800 dark:ring-white/10"></div>
        </div>

        @if ($directLabels)
            <div @class(['relative hidden w-24 @lg:block', $heightClass]) aria-hidden="true">
                @foreach ($endPoints as $point)
                    <span class="absolute left-1 flex -translate-y-1/2 items-center gap-1.5 text-[11px] leading-tight" style="top: {{ $point['y'] }}%">
                        <span data-viz="{{ $point['viz'] }}" class="h-0.5 w-2 shrink-0 rounded-full bg-(--viz)"></span>
                        <span class="min-w-0">
                            <span class="block font-semibold text-ink-900 tabular-nums dark:text-white">{{ $point['value'] }}</span>
                            @if (count($endPoints) > 1)
                                <span class="block max-w-20 truncate text-(--chart-muted)">{{ $point['name'] }}</span>
                            @endif
                        </span>
                    </span>
                @endforeach
            </div>
        @endif

        <div></div>
        <div class="relative h-6 text-[11px] text-(--chart-muted)" aria-hidden="true">
            @foreach ($xLabels as $xLabelItem)
                <span @class(['absolute top-1.5 leading-none whitespace-nowrap', $xLabelItem['edge'], $xLabelItem['visibility']]) style="left: {{ $xLabelItem['x'] }}%">{{ $xLabelItem['text'] }}</span>
            @endforeach
        </div>
    </div>
</x-chart.frame>
