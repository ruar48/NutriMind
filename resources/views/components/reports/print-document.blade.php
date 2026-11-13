@props([
    'report',
    'school',
    'backUrl',
    'preparedBy',
])

<x-layouts.print
    :title="$report->title"
    :subtitle="$report->description"
    :school-name="$school['name'] ?? null"
    :school-address="$school['address'] ?? null"
    :school-id="$school['id'] ?? null"
    :meta="$report->filters + ['Rows' => number_format($report->rows->count())]"
    :back-url="$backUrl"
>
    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4" data-report-print-summary>
        @foreach ($report->summary as $stat)
            <div class="rounded-lg border border-ink-900/15 px-3 py-2" data-print-avoid-break>
                <dt class="text-[11px] font-medium text-ink-600">{{ $stat->label }}</dt>
                <dd class="font-display text-lg leading-tight font-bold text-ink-950">{{ $stat->value }}</dd>
                @if ($stat->hint)
                    <dd class="mt-0.5 text-[10px] leading-snug text-ink-500">{{ $stat->hint }}</dd>
                @endif
            </div>
        @endforeach
    </dl>

    <div class="mt-6">
        @if ($report->isEmpty())
            <p class="rounded-lg border border-dashed border-ink-900/20 px-4 py-6 text-center text-sm text-ink-600" data-report-print-empty>
                No rows match these filters.
            </p>
        @else
            <x-reports.print-table :report="$report" />
        @endif
    </div>

    @if ($report->notes !== [])
        <ul class="mt-4 flex flex-col gap-0.5 text-[11px] leading-snug text-ink-600">
            @foreach ($report->notes as $note)
                <li>{{ $note }}</li>
            @endforeach
        </ul>
    @endif

    <x-reports.signatures
        :prepared-by="$preparedBy->name"
        :prepared-role="$preparedBy->role->label()"
        :school-head="$school['head'] ?? null"
    />
</x-layouts.print>
