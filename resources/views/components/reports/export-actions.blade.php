@props([
    'reportType',
    'portal',
    'canExport' => true,
    'size' => 'md',
])

@php
    $filters = collect(request()->query())
        ->except('page')
        ->filter(fn ($value) => is_string($value) && $value !== '')
        ->all();
    $parameters = ['report' => $reportType->value] + $filters;
    $deniedHint = 'Exporting is turned off for your account. Ask the school administrator for the “Export reports” permission.';
@endphp

<div {{ $attributes->class('flex flex-wrap items-center gap-2') }} data-report-export-actions>
    @if ($canExport)
        <x-button variant="secondary" :size="$size" icon="printer" :href="route($portal.'.reports.print', $parameters)">Print</x-button>
        <x-button :size="$size" icon="download" :href="route($portal.'.reports.export', $parameters)">Export CSV</x-button>
    @else
        <x-button variant="secondary" :size="$size" icon="printer" :href="route($portal.'.reports.print', $parameters)" disabled :title="$deniedHint">Print</x-button>
        <x-button :size="$size" icon="download" :href="route($portal.'.reports.export', $parameters)" disabled :title="$deniedHint">Export CSV</x-button>
    @endif
</div>
