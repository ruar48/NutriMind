{{--
    Average quiz result per learning module (LearningAnalytics::moduleMastery()), weakest first.
--}}
@props([
    'modules' => [],
    'title' => 'Module mastery',
    'description' => 'Quizzes linked to each learning module · weakest first',
])

@php
    $items = array_map(fn (array $row): array => [
        'label' => $row['module']->title,
        'sublabel' => implode(' · ', array_filter([
            $row['module']->grade_level->label(),
            $row['module']->subject->name,
            $row['module']->quarter ? 'Q'.$row['module']->quarter : null,
            trans_choice(':count learner|:count learners', $row['learners']),
        ])),
        'value' => $row['averageScore'],
    ], $modules);
@endphp

<x-section :title="$title" :description="$description" icon="map" color="grape" {{ $attributes }}>
    <x-chart.hbar
        label="Average score by learning module"
        format="percent"
        :sorted="false"
        label-heading="Module"
        value-heading="Average score"
        :items="$items"
        empty-text="No quiz linked to a learning module has results in this period yet."
    />
    @if ($modules !== [])
        <p class="mt-4 flex items-start gap-2 text-xs text-ink-500 dark:text-ink-400">
            <x-icon name="info" class="mt-px size-3.5 shrink-0" />
            Modules below 75% are worth revisiting in class before learners move on.
        </p>
    @endif
</x-section>
