@props([
    'weeks' => [],
    'title' => 'Weekly quiz scores',
    'description' => 'Average of the quiz attempts submitted each week',
])

@php
    $weeks = array_values((array) $weeks);
    $attemptCount = array_sum(array_column($weeks, 'attempts'));
@endphp

<x-section :title="$title" :description="$description" icon="chart-line" color="sky" {{ $attributes }}>
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset

    <x-chart.line
        label="Weekly average quiz score"
        format="percent"
        :labels="array_column($weeks, 'label')"
        :series="[['name' => 'Average score', 'data' => array_column($weeks, 'average')]]"
        :target="75"
        target-label="Passing mark"
        x-label="Week of"
        empty-text="Scores appear here once the learner submits quizzes."
    />

    <p class="mt-3 text-xs text-ink-500 dark:text-ink-400">
        {{ trans_choice(':count quiz attempt|:count quiz attempts', $attemptCount) }} in the last {{ count($weeks) }} weeks
    </p>
</x-section>
