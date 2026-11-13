@props([
    'report',
    'rows',
])

<x-section
    title="Preview"
    :description="$report->isEmpty() ? 'Nothing to list yet.' : 'Every row is included in the CSV export and the print view.'"
    icon="table"
    color="slate"
    padding="none"
    {{ $attributes }}
    data-report-preview
>
    <x-slot:actions>
        <x-badge color="slate" icon="list" data-report-row-count="{{ $rows->total() }}">{{ trans_choice(':count row|:count rows', $rows->total(), ['count' => number_format($rows->total())]) }}</x-badge>
    </x-slot:actions>

    @if ($report->isEmpty())
        {{ $empty ?? '' }}
    @else
        <x-table flush :caption="$report->title.' preview'">
            <x-slot:head>
                @foreach ($report->columns as $column)
                    <x-table.th :align="$column->align()">{{ $column->label }}</x-table.th>
                @endforeach
            </x-slot:head>

            @foreach ($rows as $row)
                <x-table.row data-report-row="{{ $loop->iteration }}">
                    @foreach ($report->columns as $column)
                        <x-table.td
                            :align="$column->align()"
                            :numeric="$column->isNumeric()"
                            :primary="$loop->first"
                            :class="$loop->first || $column->type === \App\Services\Reports\ColumnType::List ? '' : 'whitespace-nowrap'"
                            data-column="{{ $column->key }}"
                        >
                            <x-reports.cell :column="$column" :row="$row" :primary="$loop->first" />
                        </x-table.td>
                    @endforeach
                </x-table.row>
            @endforeach
        </x-table>

        @if ($rows->hasPages())
            <div class="border-t border-ink-900/5 px-5 py-3 dark:border-white/8">{{ $rows->links() }}</div>
        @endif
    @endif

    @if ($report->notes !== [])
        <x-slot:footer>
            <ul class="flex flex-col gap-1 text-xs text-ink-600 dark:text-ink-300" data-report-notes>
                @foreach ($report->notes as $note)
                    <li class="flex items-start gap-1.5">
                        <x-icon name="info" class="mt-px size-3.5 shrink-0 text-ink-400 dark:text-ink-500" />
                        <span>{{ $note }}</span>
                    </li>
                @endforeach
            </ul>
        </x-slot:footer>
    @endif
</x-section>
