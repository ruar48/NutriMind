@props([
    'items' => [],
    'shape' => 'rect',
    'interactive' => false,
])

@php
    $paletteKeys = ['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate'];
    $shapes = [
        'rect' => 'size-2.5 rounded-[3px] bg-(--viz)',
        'line' => 'h-0.5 w-3.5 rounded-full bg-(--viz)',
        'dot' => 'size-2.5 rounded-full bg-(--viz)',
        'dash' => 'w-4 border-t-2 border-dashed border-(--chart-target)',
    ];

    $entries = collect($items)->values()->map(function (array $item, int $index) use ($paletteKeys, $shape) {
        $viz = $item['viz'] ?? null;

        if ($viz === null && isset($item['color']) && in_array($item['color'], $paletteKeys, true)) {
            $viz = $item['color'];
        }

        if ($viz === null) {
            $slot = (int) ($item['slot'] ?? $index + 1);
            $viz = $slot >= 1 && $slot <= 8 ? (string) $slot : 'other';
        }

        return [
            'label' => (string) ($item['label'] ?? ''),
            'viz' => (string) $viz,
            'value' => $item['value'] ?? null,
            'shape' => $item['shape'] ?? $shape,
        ];
    });
@endphp

<ul {{ $attributes->class('flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-ink-600 dark:text-ink-300') }}>
    @foreach ($entries as $index => $entry)
        <li class="inline-flex items-center gap-1.5 rounded-md data-active:text-ink-950 dark:data-active:text-white" @if ($interactive) data-chart-legend-item data-index="{{ $index }}" @endif>
            <span aria-hidden="true" @if ($entry['shape'] !== 'dash') data-viz="{{ $entry['viz'] }}" @endif @class(['shrink-0', $shapes[$entry['shape']] ?? $shapes['rect']])></span>
            <span>{{ $entry['label'] }}</span>
            @if ($entry['value'] !== null)
                <span class="font-semibold text-ink-900 tabular-nums dark:text-white">{{ $entry['value'] }}</span>
            @endif
        </li>
    @endforeach
</ul>
