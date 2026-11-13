@props([
    'subjects' => [],
    'title' => 'Progress by subject',
    'description' => 'Best quiz scores, missions and game activities in each classroom subject',
])

<x-section :title="$title" :description="$description" icon="layers" color="teal" padding="none" {{ $attributes }}>
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset

    @if (count($subjects) === 0)
        <x-empty-state compact icon="school" title="No subjects yet" description="Subjects appear here once the learner is enrolled in a classroom." />
    @else
        <ul class="divide-y divide-ink-900/5 dark:divide-white/8">
            @foreach ($subjects as $row)
                <li class="@container px-5 py-4 sm:px-6">
                    <div class="flex flex-col gap-3 @xl:flex-row @xl:items-center @xl:gap-6">
                        <div class="flex min-w-0 items-center justify-between gap-3 @xl:w-64 @xl:shrink-0">
                            <x-subject-chip :subject="$row['subject']" />
                            <x-score-pill :value="$row['averageScore']" show-band />
                        </div>

                        <dl class="grid min-w-0 flex-1 grid-cols-3 gap-3">
                            <div class="min-w-0">
                                <dt class="text-xs text-ink-500 dark:text-ink-400">Quizzes taken</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-ink-900 tabular-nums dark:text-white">{{ number_format($row['quizzesTaken']) }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs text-ink-500 dark:text-ink-400">Missions done</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-ink-900 tabular-nums dark:text-white">{{ number_format($row['missionsCompleted']) }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs text-ink-500 dark:text-ink-400">Activities</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-ink-900 tabular-nums dark:text-white">
                                    {{ number_format($row['activitiesCompleted']) }} / {{ number_format($row['activitiesTotal']) }}
                                    @if ($row['activitiesTotal'] > 0)
                                        <x-progress
                                            :value="$row['activitiesCompleted']"
                                            :max="$row['activitiesTotal']"
                                            color="teal"
                                            size="xs"
                                            class="mt-1.5"
                                            aria-hidden="true"
                                        />
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-section>
