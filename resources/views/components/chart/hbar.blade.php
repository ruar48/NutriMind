@props([
    'items' => [],
    'format' => 'number',
    'max' => null,
    'sorted' => true,
    'color' => null,
    'track' => null,
    'limit' => null,
    'label' => 'Ranking',
    'labelHeading' => 'Item',
    'valueHeading' => 'Value',
    'emptyText' => 'Nothing to rank yet.',
])

@php
    $paletteKeys = ['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate'];
    $defaultViz = $color !== null && in_array($color, $paletteKeys, true) ? $color : '1';
    $showTrack = $track ?? $format === 'percent';

    $rowsData = collect($items)->values()->map(fn (array $item) => [
        'label' => (string) ($item['label'] ?? ''),
        'sublabel' => isset($item['sublabel']) ? (string) $item['sublabel'] : null,
        'url' => $item['url'] ?? null,
        'value' => isset($item['value']) && is_numeric($item['value']) ? max(0.0, (float) $item['value']) : null,
        'viz' => isset($item['color']) && in_array($item['color'], $paletteKeys, true) ? $item['color'] : $defaultViz,
    ]);

    if ($sorted) {
        $rowsData = $rowsData->sortByDesc(fn (array $row) => $row['value'] ?? -1)->values();
    }
    if ($limit !== null) {
        $rowsData = $rowsData->take((int) $limit)->values();
    }

    $isEmpty = $rowsData->isEmpty() || $rowsData->every(fn (array $row) => $row['value'] === null);
    $domainMax = $max !== null ? max((float) $max, 0.000001) : ($format === 'percent' ? 100.0 : max(0.000001, (float) $rowsData->max('value')));

    $formatValue = function (?float $value) use ($format): string {
        if ($value === null) {
            return '—';
        }
        $text = number_format($value, 1);
        $text = str_ends_with($text, '.0') ? substr($text, 0, -2) : $text;

        return $format === 'percent' ? $text.'%' : $text;
    };

    $total = (float) $rowsData->sum('value');
    $payload = [
        'format' => $format,
        'items' => $rowsData->map(fn (array $row) => [
            'label' => $row['label'],
            'value' => $row['value'],
            'viz' => $row['viz'],
            'share' => null,
            'sublabel' => $row['sublabel'],
        ])->all(),
    ];
    $tableRows = $rowsData->map(fn (array $row) => [$row['label'], $formatValue($row['value'])])->all();
@endphp

<x-chart.frame
    type="hbar"
    :label="$label"
    :payload="$payload"
    :columns="[$labelHeading, $valueHeading]"
    :rows="$tableRows"
    :empty="$isEmpty"
    :empty-text="$emptyText"
    empty-height="h-40"
    {{ $attributes }}
>
    <div data-chart-plot tabindex="0" role="group" aria-roledescription="chart" aria-label="{{ $label }}. Use the arrow keys to read each row." class="relative rounded-sm focus-visible:outline-offset-4">
        <ol class="space-y-3.5">
            @foreach ($rowsData as $index => $row)
                <li data-chart-hit data-index="{{ $index }}" class="grid grid-cols-[minmax(0,1fr)_auto] items-baseline gap-x-3 gap-y-1.5">
                    <div class="min-w-0">
                        @if ($row['url'])
                            <a href="{{ $row['url'] }}" class="block truncate text-sm font-medium text-ink-800 hover:text-leaf-700 hover:underline dark:text-ink-100 dark:hover:text-leaf-300">{{ $row['label'] }}</a>
                        @else
                            <span class="block truncate text-sm font-medium text-ink-800 dark:text-ink-100">{{ $row['label'] }}</span>
                        @endif
                        @if ($row['sublabel'])
                            <span class="block truncate text-xs text-ink-500 dark:text-ink-400">{{ $row['sublabel'] }}</span>
                        @endif
                    </div>
                    <span class="text-sm font-semibold text-ink-900 tabular-nums dark:text-white">{{ $formatValue($row['value']) }}</span>
                    <div data-viz="{{ $row['viz'] }}" @class(['col-span-2 h-2', 'rounded-full bg-(--viz)/15' => $showTrack])>
                        @if ($row['value'] !== null && $row['value'] > 0)
                            <div
                                data-chart-mark
                                data-index="{{ $index }}"
                                data-viz="{{ $row['viz'] }}"
                                data-progress-fill
                                @class(['h-full bg-(--viz)', 'rounded-full' => $showTrack, 'rounded-r-[4px]' => ! $showTrack])
                                style="width: max(4px, {{ round(min(100, $row['value'] / $domainMax * 100), 3) }}%)"
                            ></div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
        <div data-chart-tooltip hidden role="status" aria-live="polite" class="absolute z-10 min-w-32 rounded-xl bg-white px-3 py-2 shadow-lift ring-1 ring-ink-900/8 dark:bg-ink-800 dark:ring-white/10"></div>
    </div>
</x-chart.frame>
