@props([
    'type',
    'label',
    'payload' => [],
    'legend' => [],
    'legendShape' => 'rect',
    'legendInteractive' => false,
    'columns' => [],
    'rows' => [],
    'empty' => false,
    'emptyText' => 'No data to show yet.',
    'emptyHeight' => 'h-56',
    'table' => true,
])

<figure {{ $attributes->class('@container relative min-w-0') }} data-chart="{{ $type }}">
    <figcaption class="sr-only">{{ $label }}</figcaption>

    @if ($empty)
        <div @class(['grid place-items-center rounded-xl border border-dashed border-ink-900/12 bg-ink-50/40 px-4 text-center dark:border-white/10 dark:bg-white/3', $emptyHeight]) data-chart-empty>
            <div class="flex flex-col items-center">
                <x-icon name="chart-no-axes-combined" class="size-6 text-ink-300 dark:text-ink-600" />
                <p class="mt-2 text-sm text-ink-500 dark:text-ink-400">{{ $emptyText }}</p>
            </div>
        </div>
    @else
        <script type="application/json" data-chart-data>@json($payload)</script>

        @if (count($legend) > 0)
            <x-chart.legend :items="$legend" :shape="$legendShape" :interactive="$legendInteractive" class="mb-4" />
        @endif

        {{ $slot }}

        @if ($table && count($rows) > 0)
            <x-chart.table-toggle :columns="$columns" :rows="$rows" :caption="$label" />
        @endif
    @endif
</figure>
