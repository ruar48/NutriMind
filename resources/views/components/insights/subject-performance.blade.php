{{--
    Average quiz result per subject (LearningAnalytics::subjectPerformance()); byGrade groups the bars by grade.
--}}
@props([
    'subjects' => [],
    'byGrade' => false,
    'title' => 'Performance by subject',
    'description' => 'Average of each learner’s best score per quiz',
])

@php
    $labels = array_map(fn (array $row): string => $row['subject']->name, $subjects);
    $grades = \App\Enums\GradeLevel::cases();
    $gradeSlots = [\App\Enums\GradeLevel::Five->value => 4, \App\Enums\GradeLevel::Six->value => 2];

    $series = $byGrade
        ? array_map(fn (\App\Enums\GradeLevel $grade): array => [
            'name' => $grade->label(),
            'data' => array_map(fn (array $row): ?float => $row['byGrade'][$grade->value] ?? null, $subjects),
            'slot' => $gradeSlots[$grade->value] ?? 1,
        ], $grades)
        : [['name' => 'Average score', 'data' => array_column($subjects, 'averageScore')]];
@endphp

<x-section :title="$title" :description="$description" icon="book-open" color="leaf" {{ $attributes }}>
    <x-chart.bar
        label="Average score by subject"
        format="percent"
        :max="100"
        :labels="$labels"
        :series="$series"
        x-label="Subject"
        empty-text="No subject has quiz results in this period yet."
    />

    @if ($subjects !== [])
        <ul class="mt-5 flex flex-col divide-y divide-ink-900/5 dark:divide-white/8">
            @foreach ($subjects as $row)
                <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-2.5">
                    <x-subject-chip :subject="$row['subject']" size="sm" />
                    <div class="flex items-center gap-3 text-sm text-ink-500 dark:text-ink-400">
                        <span class="tabular-nums">{{ trans_choice(':count learner|:count learners', $row['learners']) }}</span>
                        <x-score-pill :value="$row['averageScore']" size="sm" show-band />
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-section>
