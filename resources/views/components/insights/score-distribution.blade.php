{{--
    Histogram of quiz results (LearningAnalytics::scoreDistribution()).
--}}
@props([
    'buckets' => [],
    'title' => 'Score distribution',
    'description' => 'Each learner’s best score per quiz',
])

<x-section :title="$title" :description="$description" icon="chart-column" color="sky" {{ $attributes }}>
    <x-chart.histogram
        label="Score distribution"
        series-name="Quiz results"
        x-label="Score range (%)"
        :buckets="$buckets"
        empty-text="No quiz results in this period yet."
    />
</x-section>
