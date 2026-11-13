@props([
    'items' => [],
    'format' => 'number',
    'centerValue' => null,
    'centerLabel' => 'Total',
    'size' => 'md',
    'label' => 'Breakdown',
    'labelHeading' => 'Part',
    'valueHeading' => 'Value',
    'emptyText' => 'No data to show yet.',
])

@php
    $paletteKeys = ['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate'];
    $sizes = ['sm' => 'size-36', 'md' => 'size-44', 'lg' => 'size-52'];
    $sizeClass = $sizes[$size] ?? $sizes['md'];

    $parts = collect($items)->values()->map(function (array $item, int $index) use ($paletteKeys) {
        $viz = isset($item['color']) && in_array($item['color'], $paletteKeys, true) ? $item['color'] : null;
        if ($viz === null) {
            $slot = (int) ($item['slot'] ?? $index + 1);
            $viz = $slot >= 1 && $slot <= 8 ? (string) $slot : 'other';
        }

        return [
            'label' => (string) ($item['label'] ?? ''),
            'value' => isset($item['value']) && is_numeric($item['value']) ? max(0.0, (float) $item['value']) : 0.0,
            'viz' => $viz,
        ];
    });

    if ($parts->count() > 5) {
        $parts = $parts->take(4)->push([
            'label' => 'Other',
            'value' => (float) $parts->slice(4)->sum('value'),
            'viz' => 'other',
        ]);
    }

    $total = (float) $parts->sum('value');
    $isEmpty = $parts->isEmpty() || $total <= 0;

    $formatValue = function (?float $value) use ($format): string {
        if ($value === null) {
            return '—';
        }
        $text = number_format($value, 1);
        $text = str_ends_with($text, '.0') ? substr($text, 0, -2) : $text;

        return $format === 'percent' ? $text.'%' : $text;
    };
    $formatShare = function (float $share): string {
        $text = number_format($share, 1);

        return (str_ends_with($text, '.0') ? substr($text, 0, -2) : $text).'%';
    };

    $radius = 48;
    $circumference = 2 * M_PI * $radius;
    $gap = $parts->filter(fn (array $part) => $part['value'] > 0)->count() > 1 ? 1.6 : 0;
    $segments = [];
    $offset = 0.0;

    if (! $isEmpty) {
        foreach ($parts as $index => $part) {
            if ($part['value'] <= 0) {
                continue;
            }
            $length = $part['value'] / $total * $circumference;
            $visible = max(0.6, $length - $gap);
            $middle = $offset + $length / 2;
            $angle = $middle / $circumference * 2 * M_PI - M_PI / 2;
            $segments[] = [
                'index' => $index,
                'viz' => $part['viz'],
                'dash' => round($visible, 3).' '.round($circumference, 3),
                'offset' => round(-$offset, 3),
                'anchorX' => round((60 + $radius * cos($angle)) / 120 * 100, 2),
                'anchorY' => round((60 + $radius * sin($angle)) / 120 * 100, 2),
            ];
            $offset += $length;
        }
    }

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
@endphp

<x-chart.frame
    type="donut"
    :label="$label"
    :payload="$payload"
    :columns="[$labelHeading, $valueHeading, 'Share']"
    :rows="$tableRows"
    :empty="$isEmpty"
    :empty-text="$emptyText"
    empty-height="h-44"
    {{ $attributes }}
>
    <div class="flex flex-col items-center gap-6 @md:flex-row">
        <div
            data-chart-plot
            tabindex="0"
            role="group"
            aria-roledescription="chart"
            aria-label="{{ $label }}. Use the arrow keys to read each part."
            @class(['relative shrink-0 rounded-full focus-visible:outline-offset-4', $sizeClass])
        >
            <svg viewBox="0 0 120 120" class="size-full -rotate-90" aria-hidden="true">
                <circle cx="60" cy="60" r="{{ $radius }}" fill="none" stroke-width="16" class="stroke-(--chart-grid)" />
                @foreach ($segments as $segment)
                    <circle
                        data-chart-mark
                        data-index="{{ $segment['index'] }}"
                        data-viz="{{ $segment['viz'] }}"
                        data-anchor-x="{{ $segment['anchorX'] }}"
                        data-anchor-y="{{ $segment['anchorY'] }}"
                        cx="60"
                        cy="60"
                        r="{{ $radius }}"
                        fill="none"
                        stroke-width="16"
                        stroke-dasharray="{{ $segment['dash'] }}"
                        stroke-dashoffset="{{ $segment['offset'] }}"
                        class="stroke-(--viz)"
                    />
                @endforeach
            </svg>
            <div class="pointer-events-none absolute inset-0 grid place-items-center text-center">
                <div>
                    <p class="font-display text-2xl leading-none font-bold text-ink-950 dark:text-white">{{ $centerValue ?? $formatValue($total) }}</p>
                    <p class="mt-1 text-[11px] font-medium text-ink-500 dark:text-ink-400">{{ $centerLabel }}</p>
                </div>
            </div>
            <div data-chart-tooltip hidden role="status" aria-live="polite" class="absolute z-10 min-w-32 rounded-xl bg-white px-3 py-2 shadow-lift ring-1 ring-ink-900/8 dark:bg-ink-800 dark:ring-white/10"></div>
        </div>

        <ul class="w-full min-w-0 flex-1 space-y-2">
            @foreach ($parts as $index => $part)
                <li data-chart-legend-item data-index="{{ $index }}" class="flex items-center gap-2.5 rounded-lg px-2 py-1 text-sm transition data-active:bg-ink-900/5 dark:data-active:bg-white/5">
                    <span aria-hidden="true" data-viz="{{ $part['viz'] }}" class="size-2.5 shrink-0 rounded-[3px] bg-(--viz)"></span>
                    <span class="min-w-0 flex-1 truncate text-ink-700 dark:text-ink-200">{{ $part['label'] }}</span>
                    <span class="font-semibold text-ink-900 tabular-nums dark:text-white">{{ $formatValue($part['value']) }}</span>
                    <span class="w-12 text-right text-xs text-ink-500 tabular-nums dark:text-ink-400">{{ $total > 0 ? $formatShare($part['value'] / $total * 100) : '—' }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</x-chart.frame>
