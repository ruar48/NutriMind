@php
    $firstName = \Illuminate\Support\Str::before(trim($student->name).' ', ' ');
    $percentage = (float) $attempt->percentage;
    $band = $attempt->band();
    $duration = $attempt->duration_seconds === null
        ? null
        : sprintf('%d:%02d', intdiv($attempt->duration_seconds, 60), $attempt->duration_seconds % 60);
    $questionCount = $quiz->questions->count();
@endphp

<x-layouts.app :title="$quiz->title" description="Your child’s answers in a NutriMind quiz.">
    <x-page-header
        :title="$quiz->title"
        :description="$student->name.' · Attempt '.$attempt->attempt_number.' · '.$attempt->submitted_at->format('M j, Y g:i A')"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('parent.dashboard')],
            ['label' => 'My children', 'url' => route('parent.children.index')],
            ['label' => $student->name, 'url' => route('parent.children.show', $student)],
            ['label' => 'Quizzes', 'url' => route('parent.children.show', [$student, 'tab' => 'quizzes'])],
            ['label' => $quiz->title],
        ]"
    >
        <x-slot:meta>
            <x-subject-chip :subject="$quiz->subject" />
            <x-score-pill :value="$percentage" show-band />
            <x-badge :color="$attempt->passed ? 'leaf' : 'berry'" :icon="$attempt->passed ? 'check' : 'x'">{{ $attempt->passed ? 'Passed' : 'Not passed' }}</x-badge>
            @if ($attempt->is_offline)
                <x-badge color="sky" icon="wifi-off">Played offline</x-badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" icon="arrow-left" :href="route('parent.children.show', [$student, 'tab' => 'quizzes'])">All quiz attempts</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-4 xl:col-span-2">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-display text-lg font-semibold tracking-tight text-ink-950 dark:text-white">Answers</h2>
                    <p class="text-sm text-ink-600 dark:text-ink-300" data-correct-summary>
                        {{ $correctCount }} of {{ trans_choice(':count question|:count questions', $questionCount) }} answered correctly
                    </p>
                </div>
            </div>

            @unless ($quiz->show_correct_answers)
                <x-alert variant="info" title="Correct answers are hidden for this quiz" data-answers-hidden>
                    The teacher chose not to reveal the correct answers, so learners can retake the quiz fairly. You can still see which of {{ $firstName }}’s answers were right or wrong.
                </x-alert>
            @endunless

            @forelse ($quiz->questions as $question)
                <x-question.answer-review
                    :question="$question"
                    :answer="$answersByQuestion->get($question->id)"
                    :number="$loop->iteration"
                    :reveal="$quiz->show_correct_answers"
                    answer-label="Your child's answer"
                />
            @empty
                <x-empty-state variant="card" icon="clipboard-list" title="No questions to show" description="This quiz no longer has questions to review." />
            @endforelse
        </div>

        <aside class="flex min-w-0 flex-col gap-6">
            <x-section title="Result" :description="$band->label()" icon="target" :color="$band->color()" data-attempt-result>
                <div class="flex items-center gap-5">
                    <x-progress-ring :value="$percentage" size="lg" :color="$band->color()" label="Score" />
                    <div class="min-w-0">
                        <p class="font-display text-2xl font-bold text-ink-950 tabular-nums dark:text-white">{{ $attempt->score }} / {{ $attempt->max_score }}</p>
                        <p class="text-sm text-ink-600 dark:text-ink-300">points · passing score {{ $quiz->passing_score }}%</p>
                    </div>
                </div>

                <x-definition-list class="mt-5">
                    <x-detail-row label="Result" icon="flag">{{ $attempt->passed ? 'Passed' : 'Not passed yet' }}</x-detail-row>
                    <x-detail-row label="Attempt" icon="repeat" :value="'Attempt '.$attempt->attempt_number" />
                    <x-detail-row label="Time taken" icon="timer">
                        @if ($duration)
                            <span class="tabular-nums">{{ $duration }}</span> <span class="font-normal text-ink-500 dark:text-ink-400">min:sec</span>
                        @endif
                    </x-detail-row>
                    <x-detail-row label="Submitted" icon="calendar-check">
                        <time datetime="{{ $attempt->submitted_at->toIso8601String() }}" title="{{ $attempt->submitted_at->diffForHumans() }}">{{ $attempt->submitted_at->format('M j, Y g:i A') }}</time>
                    </x-detail-row>
                    @if ($attempt->is_offline)
                        <x-detail-row label="Synced" icon="cloud-check">
                            @if ($attempt->synced_at)
                                <time datetime="{{ $attempt->synced_at->toIso8601String() }}" title="{{ $attempt->synced_at->diffForHumans() }}">{{ $attempt->synced_at->format('M j, Y g:i A') }}</time>
                            @endif
                        </x-detail-row>
                    @endif
                    <x-detail-row label="Class" icon="school" :value="$attempt->classroom?->name" />
                </x-definition-list>

                @if ($attempt->is_offline)
                    <x-slot:footer>
                        <p class="flex items-start gap-2 text-xs text-ink-500 dark:text-ink-400">
                            <x-icon name="wifi-off" class="mt-px size-3.5 shrink-0" />
                            Played without internet. The game sent the result when it reconnected.
                        </p>
                    </x-slot:footer>
                @endif
            </x-section>

            <x-section title="All attempts" description="Every try at this quiz" icon="history" color="grape" padding="none" data-attempt-history>
                <ul class="divide-y divide-ink-900/5 dark:divide-white/8">
                    @foreach ($otherAttempts as $other)
                        @php
                            $isCurrent = $other->is($attempt);
                        @endphp
                        <li @class(['flex items-center justify-between gap-3 px-5 py-3 sm:px-6', 'bg-carrot-50/60 dark:bg-carrot-400/10' => $isCurrent])>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-ink-900 dark:text-ink-100">
                                    @if ($isCurrent)
                                        <span aria-current="page">Attempt {{ $other->attempt_number }}</span>
                                        <span class="text-xs font-normal text-ink-500 dark:text-ink-400">(this one)</span>
                                    @else
                                        <a href="{{ route('parent.children.attempts.show', [$student, $other]) }}" class="rounded hover:text-carrot-700 hover:underline dark:hover:text-carrot-300">Attempt {{ $other->attempt_number }}</a>
                                    @endif
                                </p>
                                <p class="text-xs text-ink-500 dark:text-ink-400">
                                    <time datetime="{{ $other->submitted_at->toIso8601String() }}" title="{{ $other->submitted_at->format('M j, Y g:i A') }}">{{ $other->submitted_at->format('M j, Y') }}</time>
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <x-score-pill :value="(float) $other->percentage" size="sm" />
                                <x-icon :name="$other->passed ? 'circle-check' : 'circle-x'" @class(['size-4', 'text-leaf-600 dark:text-leaf-400' => $other->passed, 'text-berry-600 dark:text-berry-400' => ! $other->passed]) :label="$other->passed ? 'Passed' : 'Not passed'" />
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-section>
        </aside>
    </div>
</x-layouts.app>
