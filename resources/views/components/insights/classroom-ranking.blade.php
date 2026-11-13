{{--
    Classrooms ranked by average quiz result (LearningAnalytics::classroomRanking()).
    Props: classrooms (rows), url (?Closure(Classroom): ?string — links each bar), showTeacher.
--}}
@props([
    'classrooms' => [],
    'url' => null,
    'showTeacher' => true,
    'title' => 'Classroom ranking',
    'description' => 'Average of each learner’s best score per quiz',
])

@php
    $items = array_map(fn (array $row): array => [
        'label' => $row['classroom']->name,
        'sublabel' => implode(' · ', array_filter([
            $row['classroom']->subject->name,
            $showTeacher ? $row['classroom']->teacher->name : null,
            trans_choice(':count learner|:count learners', $row['learners']),
        ])),
        'value' => $row['averageScore'],
        'url' => $url instanceof \Closure ? $url($row['classroom']) : null,
    ], $classrooms);
@endphp

<x-section :title="$title" :description="$description" icon="trophy" color="sun" {{ $attributes }}>
    <x-chart.hbar
        label="Average score by classroom"
        format="percent"
        label-heading="Classroom"
        value-heading="Average score"
        :items="$items"
        empty-text="No classroom has quiz results in this period yet."
    />
</x-section>
