@props([
    'reportType',
    'portal',
    'canExport' => true,
])

@php
    $filters = collect(request()->query())
        ->except('page')
        ->filter(fn ($value) => is_string($value) && $value !== '')
        ->all();
    $parameters = ['report' => $reportType->value] + $filters;
    $deniedHint = $canExport ? null : 'Exporting is turned off for your account. Ask the school administrator for the “Export reports” permission.';
@endphp

<div {{ $attributes->class('flex flex-wrap items-center gap-2') }} data-report-export-actions>
    <x-button variant="secondary" icon="printer" :href="route($portal.'.reports.print', $parameters)" :disabled="! $canExport" :title="$deniedHint">Print</x-button>
    <x-button icon="download" :href="route($portal.'.reports.export', $parameters)" :disabled="! $canExport" :title="$deniedHint">Export CSV</x-button>
</div>
