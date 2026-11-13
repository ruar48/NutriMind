@php
    $teacher = auth()->user();
    $canUpdate = $teacher->can('update', $quiz);
    $canDelete = $teacher->can('delete', $quiz);
    $canUseBank = $teacher->can('viewAny', \App\Models\QuestionBankItem::class);
    $canSaveToBank = $teacher->can('create', \App\Models\QuestionBankItem::class);
    $editable = $canUpdate && $questionsLockedMessage === null;
    $hasAttempts = (bool) $quiz->attempts_exists;
    $questionCount = $quiz->questions->count();
    $activeClassrooms = $quiz->classrooms->whereNull('archived_at');
@endphp

<x-layouts.app :title="$quiz->title">
    <x-page-header
        eyebrow="Quiz builder"
        :title="$quiz->title"
        :description="$quiz->grade_level->label().' · '.$quiz->subject->name.' · '.trans_choice(':count question|:count questions', $questionCount).' · '.trans_choice(':count point|:count points', $totalPoints)"
        :breadcrumbs="[['label' => 'Quizzes', 'url' => route('teacher.quizzes.index')], ['label' => $quiz->title]]"
    >
        <x-slot:leading>
            <span class="grid size-14 place-items-center rounded-2xl bg-leaf-600 text-white shadow-card dark:bg-leaf-500 dark:text-ink-950">
                <x-icon :name="$quiz->subject->icon" class="size-7" />
            </span>
        </x-slot:leading>
        <x-slot:meta>
            <x-status-badge :status="$quiz->status" />
            <x-subject-chip :subject="$quiz->subject" />
            <x-badge :color="$quiz->grade_level->color()" icon="graduation-cap">{{ $quiz->grade_level->label() }}</x-badge>
            @if ($quiz->isReviewed())
                <x-badge color="leaf" icon="badge-check" :title="$quiz->reviewed_at->format('M j, Y g:i A')">Reviewed {{ $quiz->reviewed_at->diffForHumans() }}</x-badge>
            @elseif ($quiz->isDraft())
                <x-badge color="sun" icon="eye-off">Not reviewed</x-badge>
            @endif
            @if ($quiz->submitted_attempts_count > 0)
                <x-badge color="grape" icon="users">{{ trans_choice(':count submitted attempt|:count submitted attempts', $quiz->submitted_attempts_count) }}</x-badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            @if ($canUpdate)
                <x-quiz.status-actions :quiz="$quiz" :ready="$checklist['isReady']" :has-attempts="$hasAttempts" only="primary" />
            @endif
            <x-button variant="secondary" icon="eye" :href="route('teacher.quizzes.review.show', $quiz)">Preview</x-button>
            <x-dropdown label="More" icon="ellipsis" variant="secondary" width="lg">
                @if ($canUpdate)
                    <x-dropdown.item :href="route('teacher.quizzes.edit', $quiz)" icon="pencil">Edit settings</x-dropdown.item>
                @endif
                @if (! $quiz->isDraft() || $hasAttempts)
                    <x-dropdown.item :href="route('teacher.quizzes.results.show', $quiz)" icon="chart-column">View results</x-dropdown.item>
                @endif
                @if ($canUpdate)
                    <form method="POST" action="{{ route('teacher.quizzes.duplicate.store', $quiz) }}" data-submit-once>
                        @csrf
                        <x-dropdown.item type="submit" icon="copy">Duplicate quiz</x-dropdown.item>
                    </form>
                    @if (! $quiz->isDraft() && ! $hasAttempts)
                        <x-dropdown.divider />
                        <x-quiz.status-actions :quiz="$quiz" :has-attempts="$hasAttempts" only="secondary" menu />
                    @endif
                @endif
                @if ($canDelete)
                    <x-dropdown.divider />
                    <x-confirm-dialog
                        :action="route('teacher.quizzes.destroy', $quiz)"
                        :title="'Delete “'.$quiz->title.'”?'"
                        message="The quiz, its questions and their images and videos are removed permanently. This cannot be undone."
                        confirm="Delete quiz"
                        button="Delete quiz"
                        button-variant="menu"
                    />
                @endif
            </x-dropdown>
        </x-slot:actions>
    </x-page-header>

    <x-quiz.status-errors />

    @if ($canUpdate && $questionsLockedMessage !== null)
        <x-alert variant="info" icon="lock" title="Questions are locked" data-questions-locked>
            {{ $questionsLockedMessage }}
            @if ($hasAttempts)
                Duplicating keeps the original quiz and its results unchanged.
            @endif
        </x-alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-4 xl:col-span-2">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-display text-lg font-semibold tracking-tight text-ink-950 dark:text-white">Questions</h2>
                    <p class="text-sm text-ink-600 dark:text-ink-300">
                        {{ trans_choice(':count question|:count questions', $questionCount) }} · {{ trans_choice(':count point|:count points', $totalPoints) }}
                        @if ($quiz->shuffle_questions)
                            · Shuffled for each learner
                        @endif
                    </p>
                </div>
                @if ($editable)
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($canUseBank)
                            <x-button variant="secondary" icon="library" :href="route('teacher.quizzes.bank-questions.create', $quiz)">Add from bank</x-button>
                        @endif
                        <x-dropdown label="Add question" icon="plus" variant="secondary" width="lg">
                            <x-dropdown.label>Question type</x-dropdown.label>
                            @foreach (\App\Enums\QuestionType::cases() as $type)
                                <x-dropdown.item :href="route('teacher.quizzes.questions.create', [$quiz, 'type' => $type->value])" :icon="$type->icon()">{{ $type->label() }}</x-dropdown.item>
                            @endforeach
                        </x-dropdown>
                    </div>
                @endif
            </div>

            @forelse ($quiz->questions as $question)
                <x-quiz.question-card
                    :question="$question"
                    :quiz="$quiz"
                    :number="$loop->iteration"
                    :editable="$editable"
                    :is-first="$loop->first"
                    :is-last="$loop->last"
                    :can-save-to-bank="$canSaveToBank"
                    data-animate
                    style="--stagger: {{ min($loop->iteration, 8) }}"
                />
            @empty
                <x-empty-state variant="dashed" illustration="empty-plate" title="No questions yet" description="Write multiple choice, true or false and identification questions, with a picture or video when it helps.">
                    @if ($editable)
                        <x-slot:actions>
                            @foreach (\App\Enums\QuestionType::cases() as $type)
                                <x-button :variant="$loop->first ? 'primary' : 'secondary'" :icon="$type->icon()" :href="route('teacher.quizzes.questions.create', [$quiz, 'type' => $type->value])">{{ $type->label() }}</x-button>
                            @endforeach
                            @if ($canUseBank)
                                <x-button variant="ghost" icon="library" :href="route('teacher.quizzes.bank-questions.create', $quiz)">Add from question bank</x-button>
                            @endif
                        </x-slot:actions>
                    @endif
                </x-empty-state>
            @endforelse
        </div>

        <aside class="flex min-w-0 flex-col gap-6">
            @if ($quiz->isDraft())
                <x-quiz.publish-checklist :quiz="$quiz" :checklist="$checklist" :with-publish="false" :can-edit="$canUpdate" />
            @else
                <x-section title="Learner results" :description="trans_choice(':count submitted attempt so far|:count submitted attempts so far', $quiz->submitted_attempts_count)" icon="chart-column" color="grape">
                    <p class="text-sm text-ink-600 dark:text-ink-300">See scores, the questions learners miss most, and who has not taken the quiz yet.</p>
                    <x-button variant="soft" size="sm" icon-trailing="arrow-right" :href="route('teacher.quizzes.results.show', $quiz)" class="mt-4">View results</x-button>
                </x-section>
            @endif

            <x-section title="Settings" icon="sliders-horizontal" color="slate">
                @if ($canUpdate)
                    <x-slot:actions>
                        <x-button variant="ghost" size="sm" icon="pencil" :href="route('teacher.quizzes.edit', $quiz)">Edit</x-button>
                    </x-slot:actions>
                @endif
                <x-definition-list>
                    <x-detail-row label="Passing score" icon="target" :value="$quiz->passing_score.'%'" />
                    <x-detail-row label="Time limit" icon="timer" :value="$quiz->time_limit_minutes ? trans_choice(':count minute|:count minutes', $quiz->time_limit_minutes) : 'No time limit'" />
                    <x-detail-row label="Attempts allowed" icon="repeat" :value="$quiz->max_attempts ? number_format($quiz->max_attempts) : 'Unlimited'" />
                    <x-detail-row label="Question order" icon="shuffle" :value="$quiz->shuffle_questions ? 'Shuffled' : 'As written'" />
                    <x-detail-row label="Correct answers" icon="eye" :value="$quiz->show_correct_answers ? 'Shown after submitting' : 'Hidden from learners'" />
                    <x-detail-row label="Learning module" icon="map" :value="$quiz->module?->title" />
                    <x-detail-row label="Quarter" icon="calendar-range" :value="$quiz->academicTerm?->name" />
                    <x-detail-row label="Opens" icon="calendar" :value="$window['available_from']?->format('M j, Y g:i A') ?? 'When published'" />
                    <x-detail-row label="Due" icon="calendar-check" :value="$window['due_at']?->format('M j, Y g:i A') ?? 'No due date'" />
                </x-definition-list>
                @if (filled($quiz->instructions))
                    <div class="mt-4 rounded-xl bg-ink-50 px-3.5 py-3 text-sm dark:bg-white/3">
                        <p class="font-medium text-ink-800 dark:text-ink-100">Instructions</p>
                        <p class="mt-1 wrap-break-word whitespace-pre-line text-ink-600 dark:text-ink-300">{{ $quiz->instructions }}</p>
                    </div>
                @endif
            </x-section>

            <x-section title="Classrooms" :description="trans_choice(':count active classroom|:count active classrooms', $activeClassrooms->count())" icon="school" color="sky">
                @if ($quiz->classrooms->isEmpty())
                    <x-empty-state compact icon="school" title="Not assigned yet" description="Choose the classes that take this quiz.">
                        @if ($canUpdate)
                            <x-slot:actions><x-button size="sm" variant="soft" icon="pencil" :href="route('teacher.quizzes.edit', $quiz)">Assign classrooms</x-button></x-slot:actions>
                        @endif
                    </x-empty-state>
                @else
                    <ul role="list" class="flex flex-col divide-y divide-ink-900/5 dark:divide-white/8">
                        @foreach ($quiz->classrooms as $classroom)
                            <li class="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0">
                                    <a href="{{ route('teacher.classrooms.show', $classroom) }}" class="block truncate text-sm font-medium text-ink-900 hover:text-leaf-700 hover:underline dark:text-ink-50 dark:hover:text-leaf-300">{{ $classroom->name }}</a>
                                    <p class="text-xs text-ink-500 dark:text-ink-400">
                                        {{ $classroom->pivot->due_at ? 'Due '.$classroom->pivot->due_at->format('M j, Y g:i A') : 'No due date' }}
                                    </p>
                                </div>
                                @if ($classroom->isArchived())
                                    <x-badge color="slate" icon="archive" size="sm">Archived</x-badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-section>
        </aside>
    </div>
</x-layouts.app>
