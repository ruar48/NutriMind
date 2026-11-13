{{--
    Game activity per weekday (LearningAnalytics::weekdayEngagement()).
--}}
@props([
    'weekdays' => [],
    'title' => 'Engagement by weekday',
    'description' => 'When learners play during the week',
])

@php
    $total = collect($weekdays)->sum(fn (array $day): int => $day['attempts'] + $day['missions'] + $day['activities']);
    $busiest = $total === 0 ? null : collect($weekdays)->sortByDesc(fn (array $day): int => $day['attempts'] + $day['missions'] + $day['activities'])->first();
    $names = ['Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday'];
@endphp

<x-section :title="$title" :description="$description" icon="calendar-days" color="indigo" {{ $attributes }}>
    <x-chart.bar
        label="Game activity by weekday"
        :labels="$total === 0 ? [] : array_column($weekdays, 'label')"
        :series="[
            ['name' => 'Quiz attempts', 'data' => array_column($weekdays, 'attempts'), 'slot' => 1],
            ['name' => 'Missions completed', 'data' => array_column($weekdays, 'missions'), 'slot' => 2],
            ['name' => 'Activities completed', 'data' => array_column($weekdays, 'activities'), 'slot' => 3],
        ]"
        value-labels="none"
        x-label="Weekday"
        empty-text="No game activity in this period yet."
    />
    @if ($busiest)
        <p class="mt-3 text-xs text-ink-500 dark:text-ink-400">
            Busiest day: <span class="font-semibold text-ink-900 dark:text-white">{{ $names[$busiest['label']] ?? $busiest['label'] }}</span>
            with {{ trans_choice(':count game event|:count game events', $busiest['attempts'] + $busiest['missions'] + $busiest['activities']) }}.
        </p>
    @endif
</x-section>
