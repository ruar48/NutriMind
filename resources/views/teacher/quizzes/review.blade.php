@php
    $teacher = auth()->user();
    $canUpdate = $teacher->can('update', $quiz);
    $questionCount = $quiz->questions->count();
@endphp

<x-layouts.app :title="'Preview · '.$quiz->title">
    <x-page-header
        eyebrow="Learner preview"
        :title="$quiz->title"
        description="This is how learners see the quiz in the NutriMind game. Check every question, picture and video before you publish."
        :breadcrumbs="[
            ['label' => 'Quizzes', 'url' => route('teacher.quizzes.index')],
            ['label' => $quiz->title, 'url' => route('teacher.quizzes.show', $quiz)],
            ['label' => 'Preview'],
        ]"
    >
        <x-slot:meta>
            <x-status-badge :status="$quiz->status" />
            @if ($quiz->isReviewed())
                <x-badge color="leaf" icon="badge-check" :title="$quiz->reviewed_at->format('M j, Y g:i A')">Reviewed {{ $quiz->reviewed_at->diffForHumans() }}</x-badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            @if ($showAnswers)
                <x-button variant="secondary" icon="eye-off" :href="route('teacher.quizzes.review.show', $quiz)" aria-pressed="true">Hide answers</x-button>
            @else
                <x-button variant="secondary" icon="eye" :href="route('teacher.quizzes.review.show', [$quiz, 'answers' => 1])" aria-pressed="false">Show answers</x-button>
            @endif
            <x-button variant="ghost" icon="arrow-left" :href="route('teacher.quizzes.show', $quiz)">Back to builder</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-quiz.status-errors />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-4 xl:col-span-2">
            <x-card class="overflow-hidden bg-dot-grid" data-animate>
                <div class="flex flex-col gap-4">
                    <div class="flex items-start gap-4">
                        <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-leaf-600 text-white shadow-card dark:bg-leaf-500 dark:text-ink-950">
                            <x-icon name="gamepad-2" class="size-6" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold tracking-[0.12em] text-leaf-700 uppercase dark:text-leaf-300">{{ $quiz->subject->name }} · {{ $quiz->grade_level->label() }}</p>
                            <h2 class="mt-1 font-display text-xl font-bold tracking-tight wrap-break-word text-ink-950 dark:text-white">{{ $quiz->title }}</h2>
                        </div>
                    </div>
                    @if (filled($quiz->instructions))
                        <p class="text-sm wrap-break-word whitespace-pre-line text-ink-700 dark:text-ink-200">{{ $quiz->instructions }}</p>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <x-badge color="sky" icon="list-ordered">{{ trans_choice(':count question|:count questions', $questionCount) }}</x-badge>
                        <x-badge color="sun" icon="star">{{ trans_choice(':count point|:count points', $totalPoints) }}</x-badge>
                        <x-badge color="leaf" icon="target">Pass at {{ $quiz->passing_score }}%</x-badge>
                        <x-badge color="grape" icon="timer">{{ $quiz->time_limit_minutes ? trans_choice(':count minute|:count minutes', $quiz->time_limit_minutes) : 'No time limit' }}</x-badge>
                        <x-badge color="slate" icon="repeat">{{ $quiz->max_attempts ? trans_choice(':count attempt|:count attempts', $quiz->max_attempts) : 'Unlimited attempts' }}</x-badge>
                    </div>
                    @if ($quiz->shuffle_questions)
                        <p class="flex items-center gap-1.5 text-xs text-ink-500 dark:text-ink-400"><x-icon name="shuffle" class="size-3.5" />Learners see these questions in a shuffled order.</p>
                    @endif
                </div>
            </x-card>

            @forelse ($quiz->questions as $question)
                <x-quiz.preview-question :question="$question" :number="$loop->iteration" :total="$questionCount" :show-answers="$showAnswers" />
            @empty
                <x-empty-state variant="dashed" illustration="empty-plate" title="Nothing to preview yet" description="Add questions to the quiz, then come back to review them.">
                    @if ($canUpdate && $quiz->isDraft())
                        <x-slot:actions>
                            <x-button icon="plus" :href="route('teacher.quizzes.questions.create', $quiz)">Add question</x-button>
                        </x-slot:actions>
                    @endif
                </x-empty-state>
            @endforelse
        </div>

        <aside class="flex min-w-0 flex-col gap-6 xl:sticky xl:top-24 xl:self-start">
            @if ($quiz->isDraft())
                <x-section title="Review" icon="badge-check" :color="$quiz->isReviewed() ? 'leaf' : 'sun'" data-review-panel>
                    @if ($quiz->isReviewed())
                        <p class="text-sm text-ink-600 dark:text-ink-300">You reviewed these questions {{ $quiz->reviewed_at->diffForHumans() }}. Changing a question asks for a new review.</p>
                    @else
                        <p class="text-sm text-ink-600 dark:text-ink-300">Read each question as a learner would. Check the pictures, videos and answers, then mark the quiz as reviewed.</p>
                    @endif

                    @if ($canUpdate && ! $quiz->isReviewed())
                        <x-slot:footer>
                            <form method="POST" action="{{ route('teacher.quizzes.review.store', $quiz) }}" class="flex justify-end" data-submit-once>
                                @csrf
                                <x-button type="submit" icon="badge-check" :disabled="$questionCount === 0">Mark as reviewed</x-button>
                            </form>
                        </x-slot:footer>
                    @endif
                </x-section>

                <x-quiz.publish-checklist :quiz="$quiz" :checklist="$checklist" :can-edit="$canUpdate" />
            @else
                <x-alert variant="info" icon="lock" title="This quiz is {{ strtolower($quiz->status->label()) }}">
                    Its questions cannot change while learners can see it. In the builder you can move it back to draft if nobody took it yet, or duplicate it.
                </x-alert>
            @endif
        </aside>
    </div>
</x-layouts.app>
