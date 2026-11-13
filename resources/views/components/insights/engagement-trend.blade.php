{{--
    Weekly quiz attempts and active learners (LearningAnalytics::weeklyTrend()).
--}}
@props([
    'weeks' => [],
    'title' => 'Weekly engagement',
    'description' => 'Quiz attempts and learners who played each week',
])

@php
    $hasActivity = collect($weeks)->sum(fn (array $week): int => $week['attempts'] + $week['activeLearners']) > 0;
@endphp

<x-section :title="$title" :description="$description" icon="activity" color="grape" {{ $attributes }}>
    <x-chart.line
        label="Weekly engagement"
        :labels="$hasActivity ? array_column($weeks, 'label') : []"
        :series="[
            ['name' => 'Quiz attempts', 'data' => array_column($weeks, 'attempts'), 'slot' => 1],
            ['name' => 'Active learners', 'data' => array_column($weeks, 'activeLearners'), 'slot' => 2],
        ]"
        :min="0"
        x-label="Week of"
        empty-text="Engagement appears here once learners play in this period."
    />
    <p class="mt-3 text-xs text-ink-500 dark:text-ink-400">
        An active learner submitted a quiz, finished a mission or finished a learning activity that week.
    </p>
</x-section>
