@props([
    'segments' => [],
    'variant' => 'tall',
    'format' => 'number',
    'legend' => true,
    'legendValues' => null,
    'label' => 'Distribution',
    'labelHeading' => 'Part',
    'valueHeading' => 'Value',
    'emptyText' => 'No data to show yet.',
])

@php
    $paletteKeys = ['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate'];
    $isTall = $variant !== 'thin';
    $showLegendValues = $legendValues ?? ! $isTall;

    $parts = collect($segments)->values()->map(function (array $segment, int $index) use ($paletteKeys) {
        $viz = isset($segment['color']) && in_array($segment['color'], $paletteKeys, true) ? $segment['color'] : null;
        if ($viz === null) {
            $slot = (int) ($segment['slot'] ?? $index + 1);
            $viz = $slot >= 1 && $slot <= 8 ? (string) $slot : 'other';
        }

        return [
            'label' => (string) ($segment['label'] ?? ''),
            'value' => isset($segment['value']) && is_numeric($segment['value']) ? max(0.0, (float) $segment['value']) : 0.0,
            'viz' => $viz,
        ];
    });

    $total = (float) $parts->sum('value');
    $isEmpty = $parts->isEmpty() || $total <= 0;

    $formatValue = function (float $value) use ($format): string {
        $text = number_format($value, 1);
        $text = str_ends_with($text, '.0') ? substr($text, 0, -2) : $text;

        return $format === 'percent' ? $text.'%' : $text;
    };
    $formatShare = function (float $share): string {
        $text = number_format($share, 1);

        return (str_ends_with($text, '.0') ? substr($text, 0, -2) : $text).'%';
    };

    $summary = $parts->filter(fn (array $part) => $part['value'] > 0)
        ->map(fn (array $part) => $part['label'].' '.$formatValue($part['value']).' ('.$formatShare($total > 0 ? $part['value'] / $total * 100 : 0).')')
        ->implode(', ');

    $payload = [
        'format' => $format,
        'items' => $parts->map(fn (array $part) => [
            'label' => $part['label'],
            'value' => $part['value'],
            'viz' => $part['viz'],
            'share' => $total > 0 ? round($part['value'] / $total * 100, 1) : null,
            'sublabel' => null,
        ])->all(),
    ];
    $tableRows = $parts->map(fn (array $part) => [$part['label'], $formatValue($part['value']), $total > 0 ? $formatShare($part['value'] / $total * 100) : '—'])->all();
    $legendItems = $parts->map(fn (array $part) => [
        'label' => $part['label'],
        'viz' => $part['viz'],
        'value' => $showLegendValues ? $formatValue($part['value']).' · '.($total > 0 ? $formatShare($part['value'] / $total * 100) : '—') : null,
    ])->all();
@endphp

<x-chart.frame
    type="stacked"
    :label="$label"
    :payload="$payload"
    :columns="[$labelHeading, $valueHeading, 'Share']"
    :rows="$tableRows"
    :empty="$isEmpty"
    :empty-text="$emptyText"
    empty-height="h-20"
    {{ $attributes }}
>
    <div
        data-chart-plot
        tabindex="0"
        role="group"
        aria-roledescription="chart"
        aria-label="{{ $label }}: {{ $summary }}"
        class="relative rounded-sm focus-visible:outline-offset-4"
    >
        <div @class(['flex w-full gap-[2px]', 'h-7' => $isTall, 'h-2 overflow-hidden rounded-full' => ! $isTall]) aria-hidden="true">
            @foreach ($parts as $index => $part)
                @if ($part['value'] > 0)
                    <div
                        data-chart-mark
                        data-index="{{ $index }}"
                        data-viz="{{ $part['viz'] }}"
                        @class(['relative min-w-0 bg-(--viz)', '@container flex items-center rounded-[4px] px-2' => $isTall])
                        style="flex: {{ round($part['value'], 4) }} 1 0%"
                    >
                        @if ($isTall)
                            <span class="hidden text-[11px] leading-none font-semibold whitespace-nowrap text-(--viz-ink) tabular-nums @min-[2.75rem]:block">{{ $formatShare($part['value'] / $total * 100) }}</span>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>
        <div data-chart-tooltip hidden role="status" aria-live="polite" class="absolute z-10 min-w-32 rounded-xl bg-white px-3 py-2 shadow-lift ring-1 ring-ink-900/8 dark:bg-ink-800 dark:ring-white/10"></div>
    </div>

    @if ($legend)
        <x-chart.legend :items="$legendItems" interactive class="mt-3" />
    @endif
</x-chart.frame>
