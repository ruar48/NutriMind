{{--
    Headline figures of an analytics scope with their change against the comparison period.
    Props: overview (LearningAnalytics::overview() array), period (AnalyticsPeriod).
--}}
@props([
    'overview',
    'period',
])

@php
    $percent = fn (?float $value): string => $value === null ? '—' : number_format($value, 1).'%';
    $comparison = $period->comparisonLabel();
@endphp

<div {{ $attributes->class('grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3') }} data-insights-kpis>
    <x-stat-card
        label="Average score"
        :value="$percent($overview['averageScore'])"
        icon="target"
        color="leaf"
        :trend="$overview['averageScoreDelta']"
        trend-format="points"
        :trend-label="$comparison"
        :hint="trans_choice(':count quiz result|:count quiz results', $overview['results'])"
        :count-up="$overview['averageScore']"
        :count-decimals="1"
        count-suffix="%"
        data-animate
        style="--stagger: 1"
    />
    <x-stat-card
        label="Pass rate"
        :value="$percent($overview['passRate'])"
        icon="badge-check"
        color="teal"
        :trend="$overview['passRateDelta']"
        trend-format="points"
        :trend-label="$comparison"
        hint="Results at or above the quiz’s passing score"
        :count-up="$overview['passRate']"
        :count-decimals="1"
        count-suffix="%"
        data-animate
        style="--stagger: 2"
    />
    <x-stat-card
        label="Quiz attempts"
        :value="number_format($overview['attempts'])"
        icon="clipboard-check"
        color="grape"
        :trend="$overview['attemptsDelta']"
        :trend-label="$comparison"
        :count-up="$overview['attempts']"
        data-animate
        style="--stagger: 3"
    />
    <x-stat-card
        label="Active learners"
        :value="number_format($overview['activeLearners'])"
        icon="users"
        color="sky"
        :trend="$overview['activeLearnersDelta']"
        :trend-label="$comparison"
        :hint="'of '.number_format($overview['learners']).' '.\Illuminate\Support\Str::plural('learner', $overview['learners'])"
        :count-up="$overview['activeLearners']"
        data-animate
        style="--stagger: 4"
    />
    <x-stat-card
        label="Missions completed"
        :value="number_format($overview['missionsCompleted'])"
        icon="flag"
        color="carrot"
        :trend="$overview['missionsCompletedDelta']"
        :trend-label="$comparison"
        :count-up="$overview['missionsCompleted']"
        data-animate
        style="--stagger: 5"
    />
    <x-stat-card
        label="Activities completed"
        :value="number_format($overview['activitiesCompleted'])"
        icon="puzzle"
        color="indigo"
        :trend="$overview['activitiesCompletedDelta']"
        :trend-label="$comparison"
        :count-up="$overview['activitiesCompleted']"
        data-animate
        style="--stagger: 6"
    />
</div>
