{{--
    Quiz figures per grade (LearningAnalytics::gradePerformance()).
--}}
@props([
    'grades' => [],
    'title' => 'Performance by grade',
    'description' => 'Average, pass rate and learners assessed per grade',
])

<x-section :title="$title" :description="$description" icon="graduation-cap" color="sky" {{ $attributes }}>
    <div class="@container">
        <div class="grid grid-cols-1 gap-3 @sm:grid-cols-2">
            @foreach ($grades as $row)
                <div class="flex min-w-0 items-center gap-4 rounded-2xl bg-ink-50 p-4 dark:bg-white/5" data-insights-grade="{{ $row['grade']->value }}">
                    <x-progress-ring :value="$row['averageScore'] ?? 0" size="md" :color="$row['grade']->color()" :label="$row['grade']->label().' average score'">
                        <span class="font-display text-sm font-bold text-ink-950 dark:text-white">{{ $row['averageScore'] === null ? '—' : number_format($row['averageScore'], 1).'%' }}</span>
                    </x-progress-ring>
                    <div class="min-w-0">
                        <x-badge :color="$row['grade']->color()" :icon="$row['grade']->icon()" size="sm">{{ $row['grade']->label() }}</x-badge>
                        <p class="mt-2 text-sm text-ink-600 dark:text-ink-300">
                            @if ($row['results'] === 0)
                                No quiz results yet
                            @else
                                {{ number_format($row['passRate'], 1) }}% passed · {{ trans_choice(':count learner|:count learners', $row['learners']) }}
                            @endif
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-section>
