@props([
    'summary',
])

@php
    $averageText = $summary->averageScore === null
        ? '—'
        : rtrim(rtrim(number_format($summary->averageScore, 1), '0'), '.').'%';
@endphp

<div {{ $attributes->class('grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4') }}>
    <x-stat-card
        label="Average score"
        :value="$averageText"
        icon="target"
        :color="$summary->band->color()"
        :hint="$summary->averageScore === null ? 'No quiz attempts yet' : $summary->band->label()"
        :count-up="$summary->averageScore"
        :count-decimals="1"
        count-suffix="%"
        data-animate
        style="--stagger: 1"
    />

    <x-stat-card
        label="Quizzes taken"
        :value="number_format($summary->quizzesTaken)"
        icon="clipboard-check"
        color="sky"
        :hint="number_format($summary->quizzesPassed).' passed'"
        :count-up="$summary->quizzesTaken"
        data-animate
        style="--stagger: 2"
    />

    <x-stat-card
        label="Missions completed"
        :value="number_format($summary->missionsCompleted)"
        icon="flag"
        color="carrot"
        :hint="number_format($summary->missionsInProgress).' in progress'"
        :count-up="$summary->missionsCompleted"
        data-animate
        style="--stagger: 3"
    />

    <x-stat-card
        label="Activities completed"
        :value="number_format($summary->activitiesCompleted)"
        icon="book-open"
        color="grape"
        :hint="$summary->activitiesTotal === 0 ? 'No activities for this grade yet' : 'of '.number_format($summary->activitiesTotal).' available'"
        :count-up="$summary->activitiesCompleted"
        data-animate
        style="--stagger: 4"
    >
        @if ($summary->activitiesTotal > 0)
            <x-progress :value="$summary->activitiesCompleted" :max="$summary->activitiesTotal" color="grape" size="sm" label="Completion" show-value />
        @endif
    </x-stat-card>
</div>
