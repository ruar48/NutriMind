@props([
    'report',
])

@php
    $showBreakdown = $report->breakdown !== null && ! $report->isEmpty();
@endphp

<div {{ $attributes->class(['grid grid-cols-1 gap-6', 'xl:grid-cols-3' => $showBreakdown]) }} data-report-summary>
    <div @class([
        'grid grid-cols-1 gap-4 sm:grid-cols-2',
        'xl:col-span-2' => $showBreakdown,
        'xl:grid-cols-4' => ! $showBreakdown,
    ])>
        @foreach ($report->summary as $stat)
            <x-stat-card
                :label="$stat->label"
                :value="$stat->value"
                :icon="$stat->icon"
                :color="$stat->color"
                :hint="$stat->hint"
                :count-up="$stat->countUp"
                :count-decimals="$stat->countDecimals"
                :count-suffix="$stat->countSuffix"
                data-report-stat="{{ $stat->label }}"
                data-animate
                style="--stagger: {{ $loop->iteration }}"
            />
        @endforeach
    </div>

    @if ($showBreakdown)
        <x-section :title="$report->breakdown['title']" :description="$report->breakdown['description']" icon="chart-bar" color="sky" data-animate style="--stagger: 5">
            <x-chart.stacked-bar
                :label="$report->breakdown['title']"
                :segments="$report->breakdown['segments']"
                label-heading="Group"
                value-heading="Count"
            />
        </x-section>
    @endif
</div>
