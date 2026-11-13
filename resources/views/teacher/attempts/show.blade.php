@php
    $isSubmitted = $attempt->submitted_at !== null;
    $band = $attempt->band();
    $percentage = (float) $attempt->percentage;
    $correctAnswers = $attempt->answers->where('is_correct', true)->count();
@endphp

<x-layouts.app :title="$student->name.' · '.$quiz->title">
    <x-page-header
        eyebrow="Quiz attempt"
        :title="$student->name"
        :description="$quiz->title.' · Attempt '.$attempt->attempt_number"
        :breadcrumbs="[
            ['label' => 'Quizzes', 'url' => route('teacher.quizzes.index')],
            ['label' => $quiz->title, 'url' => route('teacher.quizzes.show', $quiz)],
            ['label' => 'Results', 'url' => route('teacher.quizzes.results.show', $quiz)],
            ['label' => 'Attempt '.$attempt->attempt_number.' · '.$student->name],
        ]"
    >
        <x-slot:leading>
            <x-avatar :name="$student->name" size="xl" class="ring-4 ring-white shadow-card dark:ring-ink-900" />
        </x-slot:leading>
        <x-slot:meta>
            <x-subject-chip :subject="$quiz->subject" />
            @if ($isSubmitted)
                <x-status-badge :status="$band" />
                @if ($attempt->passed)
                    <x-badge color="leaf" icon="circle-check">Passed</x-badge>
                @else
                    <x-badge color="berry" icon="circle-x">Not passed</x-badge>
                @endif
            @else
                <x-badge color="sun" icon="timer">Not submitted yet</x-badge>
            @endif
            @if ($attempt->is_offline)
                <x-badge color="sky" icon="wifi-off">Played offline</x-badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            @if ($canViewLearner)
                <x-button variant="secondary" icon="user-round" :href="route('teacher.students.show', $student)">Learner profile</x-button>
            @endif
            @if ($canGiveFeedback && $isSubmitted)
                <x-button icon="message-circle" :href="route('teacher.feedback.create', ['student' => $student->getKey(), 'attempt' => $attempt->getKey()])">Give feedback on this attempt</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-4 xl:col-span-2">
            <h2 class="font-display text-lg font-semibold tracking-tight text-ink-950 dark:text-white">Answers</h2>
            @forelse ($quiz->questions as $question)
                <x-question.answer-review :question="$question" :answer="$answersByQuestion->get($question->getKey())" :number="$loop->iteration" />
            @empty
                <x-empty-state variant="card" icon="list-checks" title="This quiz has no questions" description="There is nothing to review for this attempt." />
            @endforelse
        </div>

        <aside class="flex min-w-0 flex-col gap-6">
            <x-section title="Result" icon="target" :color="$band->color()">
                <div class="flex items-center gap-5">
                    <x-progress-ring :value="$isSubmitted ? $percentage : 0" size="lg" :color="$band->color()" :label="'Score '.rtrim(rtrim(number_format($percentage, 1), '0'), '.').'%'">
                        <span class="font-display text-xl font-bold text-ink-950 tabular-nums dark:text-white">{{ $isSubmitted ? rtrim(rtrim(number_format($percentage, 1), '0'), '.').'%' : '—' }}</span>
                    </x-progress-ring>
                    <div class="min-w-0 space-y-1">
                        <p class="font-display text-2xl font-bold text-ink-950 tabular-nums dark:text-white">{{ $attempt->score }} / {{ $attempt->max_score }}</p>
                        <p class="text-sm text-ink-600 dark:text-ink-300">{{ $correctAnswers }} of {{ $quiz->questions->count() }} {{ \Illuminate\Support\Str::plural('question', $quiz->questions->count()) }} correct</p>
                        <p class="text-sm text-ink-600 dark:text-ink-300">Passing score {{ $quiz->passing_score }}%</p>
                    </div>
                </div>

                <x-definition-list class="mt-6">
                    <x-detail-row label="Attempt" icon="repeat" :value="'#'.$attempt->attempt_number" />
                    <x-detail-row label="Duration" icon="timer"><x-monitoring.duration :seconds="$attempt->duration_seconds" /></x-detail-row>
                    <x-detail-row label="Started" icon="play"><x-monitoring.time :value="$attempt->started_at" :relative="false" /></x-detail-row>
                    <x-detail-row label="Submitted" icon="send"><x-monitoring.time :value="$attempt->submitted_at" :relative="false" empty="Not submitted yet" /></x-detail-row>
                    <x-detail-row label="Classroom" icon="school" :value="$attempt->classroom?->name" />
                    <x-detail-row label="Played" :icon="$attempt->is_offline ? 'wifi-off' : 'globe'">
                        @if ($attempt->is_offline)
                            Offline · {{ $attempt->synced_at ? 'synced '.$attempt->synced_at->format('M j, Y g:i A') : 'not synced yet' }}
                        @else
                            Online
                        @endif
                    </x-detail-row>
                </x-definition-list>
            </x-section>

            <x-section title="Other attempts" description="This learner’s other submitted attempts at the quiz" icon="history" color="slate" padding="none">
                @if ($otherAttempts->isEmpty())
                    <x-empty-state compact icon="history" title="No other attempts" description="This is the learner’s only submitted attempt." />
                @else
                    <ul class="divide-y divide-ink-900/5 dark:divide-white/8">
                        @foreach ($otherAttempts as $otherAttempt)
                            <li>
                                <a href="{{ route('teacher.attempts.show', $otherAttempt) }}" class="flex items-center justify-between gap-3 px-5 py-3 transition hover:bg-ink-50/70 sm:px-6 dark:hover:bg-white/3">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-ink-900 dark:text-ink-100">Attempt {{ $otherAttempt->attempt_number }}</span>
                                        <span class="block text-xs text-ink-500 dark:text-ink-400"><x-monitoring.time :value="$otherAttempt->submitted_at" /></span>
                                    </span>
                                    <x-score-pill :value="(float) $otherAttempt->percentage" show-band />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-section>
        </aside>
    </div>
</x-layouts.app>
