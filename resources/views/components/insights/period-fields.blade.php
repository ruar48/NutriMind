{{--
    The period select and the custom date range of an analytics filter bar (place inside x-filter-bar).
    Props: options (PeriodOptions::options()), filters (array with period, from, to).
--}}
@props([
    'options' => [],
    'filters' => [],
])

@php
    $isCustom = ($filters['period'] ?? null) === \App\Services\Analytics\PeriodOptions::CUSTOM;
    $today = now()->toDateString();
@endphp

<x-form.select
    name="period"
    label="Period"
    hide-label
    :options="$options"
    :value="$filters['period'] ?? null"
    :old="false"
    class="sm:w-64"
/>

<div data-show-when="period:{{ \App\Services\Analytics\PeriodOptions::CUSTOM }}" @if (! $isCustom) hidden @endif class="flex flex-col gap-3 sm:flex-row sm:items-end">
    <x-form.input
        type="date"
        name="from"
        label="From"
        :value="$isCustom ? ($filters['from'] ?? null) : null"
        :old="false"
        max="{{ $today }}"
        class="sm:w-44"
    />
    <x-form.input
        type="date"
        name="to"
        label="To"
        :value="$isCustom ? ($filters['to'] ?? null) : null"
        :old="false"
        max="{{ $today }}"
        class="sm:w-44"
    />
</div>
