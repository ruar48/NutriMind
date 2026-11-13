@props([
    'report',
])

{{-- A plain table (no scroll container) so long reports break across printed pages and repeat the header row. --}}
<table {{ $attributes->class([
    'w-full border-collapse text-left leading-snug text-ink-950',
    'text-[10px]' => count($report->columns) > 9,
    'text-xs' => count($report->columns) <= 9,
]) }} data-report-print-table>
    <caption class="sr-only">{{ $report->title }}</caption>
    <thead class="border-b-2 border-ink-900">
        <tr>
            @foreach ($report->columns as $column)
                <th scope="col" @class([
                    'px-1.5 py-1.5 align-bottom font-semibold first:pl-0 last:pr-0',
                    'text-right' => $column->align() === 'right',
                ])>{{ $column->label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody class="divide-y divide-ink-900/15">
        @foreach ($report->rows as $row)
            <tr data-report-print-row>
                @foreach ($report->columns as $column)
                    <td @class([
                        'px-1.5 py-1 align-top first:pl-0 last:pr-0',
                        'text-right tabular-nums whitespace-nowrap' => $column->isNumeric(),
                        'font-medium' => $loop->first,
                    ])>{{ $column->display($row) }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
