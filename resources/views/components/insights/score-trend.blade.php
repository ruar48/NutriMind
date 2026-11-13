{{--
    Weekly average quiz score with the passing mark (LearningAnalytics::weeklyTrend()).
--}}
@props([
    'weeks' => [],
    'title' => 'Weekly average score',
    'description' => 'Mean of every quiz attempt submitted each week',
])

@php
    $attempts = collect($weeks)->sum('attempts');
@endphp

<x-section :title="$title" :description="$description" icon="chart-line" color="leaf" {{ $attributes }}>
    <x-chart.line
        label="Weekly average score"
        format="percent"
        :labels="array_column($weeks, 'label')"
        :series="[['name' => 'Average score', 'data' => array_column($weeks, 'averageScore')]]"
        :target="75"
        target-label="Passing mark (75%)"
        x-label="Week of"
        empty-text="Scores appear here once learners submit quiz attempts in this period."
    />
    <p class="mt-3 text-xs text-ink-500 dark:text-ink-400">
        {{ trans_choice(':count quiz attempt|:count quiz attempts', $attempts) }} across {{ trans_choice(':count week|:count weeks', count($weeks)) }}.
    </p>
</x-section>
