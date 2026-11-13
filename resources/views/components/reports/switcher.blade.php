@props([
    'reportTypes',
    'current',
    'portal',
])

<x-tabs label="Reports" {{ $attributes }} data-print-hidden>
    @foreach ($reportTypes as $type)
        <x-tab :href="route($portal.'.reports.show', $type)" :active="$type === $current" :icon="$type->icon()">{{ $type->label() }}</x-tab>
    @endforeach
</x-tabs>
