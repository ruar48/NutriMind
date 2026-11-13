@props([
    'reportTypes',
    'portal',
    'canExport' => true,
])

@php
    $filtersByType = [
        \App\Enums\ReportType::UserAccounts->value => ['Role', 'Status', 'Created date'],
    ];
    $classroomFilters = $portal === 'admin'
        ? ['Classroom', 'Subject', 'Grade', 'Teacher', 'Period']
        : ['Classroom', 'Subject', 'Grade', 'Period'];
@endphp

<div {{ $attributes->class('grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3') }} data-report-catalog>
    @foreach ($reportTypes as $type)
        <x-section
            :title="$type->label()"
            :description="$type->description()"
            :icon="$type->icon()"
            :color="$type->color()"
            class="flex flex-col"
            data-report-card="{{ $type->value }}"
            data-animate
            style="--stagger: {{ $loop->iteration }}"
        >
            <p class="text-xs font-medium tracking-wide text-ink-500 uppercase dark:text-ink-400">Filter by</p>
            <ul class="mt-2 flex flex-wrap gap-1.5" aria-label="Filters of the {{ $type->label() }} report">
                @foreach ($filtersByType[$type->value] ?? $classroomFilters as $filter)
                    <li><x-badge color="slate" size="sm">{{ $filter }}</x-badge></li>
                @endforeach
            </ul>

            <x-slot:footer>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <x-button size="sm" icon-trailing="arrow-right" :href="route($portal.'.reports.show', $type)">Open report</x-button>
                    @if ($canExport)
                        <div class="flex flex-wrap items-center gap-1">
                            <x-button variant="ghost" size="sm" icon="download" :href="route($portal.'.reports.export', $type)" :aria-label="'Export '.$type->label().' as CSV'">CSV</x-button>
                            <x-button variant="ghost" size="sm" icon="printer" :href="route($portal.'.reports.print', $type)" :aria-label="'Print '.$type->label()">Print</x-button>
                        </div>
                    @endif
                </div>
            </x-slot:footer>
        </x-section>
    @endforeach
</div>
