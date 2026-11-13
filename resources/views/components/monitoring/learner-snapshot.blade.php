@props([
    'student',
    'summary' => null,
])

<x-section title="Learner" icon="user-round" color="sky" {{ $attributes }}>
    @if ($summary)
        <x-slot:actions>
            <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('teacher.students.show', $student)">View progress</x-button>
        </x-slot:actions>
    @endif

    <div class="flex items-center gap-3">
        <x-avatar :name="$student->name" size="lg" />
        <div class="min-w-0">
            <p class="font-display text-lg font-semibold break-words text-ink-950 dark:text-white">{{ $student->name }}</p>
            <p class="text-sm text-ink-500 dark:text-ink-400">
                {{ $student->studentProfile?->grade_level?->label() ?? 'No grade on file' }}
                @if ($student->username)
                    · <span class="font-mono">{{ $student->username }}</span>
                @endif
            </p>
        </div>
    </div>

    @if ($summary)
        @if ($summary->classrooms->isNotEmpty())
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($summary->classrooms as $classroom)
                    <x-subject-chip :name="$classroom->name" :color="$classroom->subject->color" :icon="$classroom->subject->icon" size="sm" />
                @endforeach
            </div>
        @endif

        <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">
            <div class="min-w-0">
                <dt class="text-ink-500 dark:text-ink-400">Average score</dt>
                <dd class="mt-1 flex flex-wrap items-center gap-2">
                    <x-score-pill :value="$summary->averageScore" show-band />
                </dd>
            </div>
            <div class="min-w-0">
                <dt class="text-ink-500 dark:text-ink-400">Quizzes</dt>
                <dd class="mt-1 font-semibold text-ink-900 tabular-nums dark:text-white">{{ $summary->quizzesTaken }} taken · {{ $summary->quizzesPassed }} passed</dd>
            </div>
            <div class="min-w-0">
                <dt class="text-ink-500 dark:text-ink-400">Missions completed</dt>
                <dd class="mt-1 font-semibold text-ink-900 tabular-nums dark:text-white">{{ $summary->missionsCompleted }}</dd>
            </div>
            <div class="min-w-0">
                <dt class="text-ink-500 dark:text-ink-400">Last active</dt>
                <dd class="mt-1 font-semibold text-ink-900 dark:text-white"><x-monitoring.time :value="$summary->lastActiveAt" empty="Not played yet" /></dd>
            </div>
        </dl>
    @else
        <x-alert variant="info" class="mt-4">This learner is no longer in your classrooms. You can still edit or delete the feedback you wrote.</x-alert>
    @endif
</x-section>
