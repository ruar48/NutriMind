{{--
    "Ready to publish" checklist of a draft quiz: at least one question, at least one active classroom, reviewed
    since the last question change. checklist comes from QuizController::publishChecklist().
    withPublish = false leaves out the Publish button (when the page header already offers it); canEdit = false hides
    the links to the editing pages (the teacher may not change the quiz).
--}}
@props([
    'quiz',
    'checklist',
    'withPublish' => true,
    'canEdit' => true,
])

@php
    $steps = [
        [
            'done' => $checklist['hasQuestions'],
            'title' => 'Add at least one question',
            'done_text' => trans_choice(':count question added|:count questions added', $quiz->questions->count()),
            'todo_text' => 'Write a question or add one from the question bank.',
            'action' => $canEdit ? ['label' => 'Add question', 'url' => route('teacher.quizzes.questions.create', $quiz), 'icon' => 'plus'] : null,
            'key' => 'questions',
        ],
        [
            'done' => $checklist['hasClassroom'],
            'title' => 'Assign at least one classroom',
            'done_text' => trans_choice('Assigned to :count classroom|Assigned to :count classrooms', $quiz->classrooms->whereNull('archived_at')->count()),
            'todo_text' => 'Choose the classes that take this quiz in the quiz settings.',
            'action' => $canEdit ? ['label' => 'Edit settings', 'url' => route('teacher.quizzes.edit', $quiz), 'icon' => 'pencil'] : null,
            'key' => 'classrooms',
        ],
        [
            'done' => $checklist['isReviewed'],
            'title' => 'Review the questions',
            'done_text' => $quiz->reviewed_at ? 'Reviewed '.$quiz->reviewed_at->diffForHumans() : 'Reviewed',
            'todo_text' => 'Preview the quiz as learners see it, then mark it as reviewed. Any question change asks for a new review.',
            'action' => ['label' => 'Review quiz', 'url' => route('teacher.quizzes.review.show', $quiz), 'icon' => 'eye'],
            'key' => 'review',
        ],
    ];
    $doneCount = collect($steps)->where('done', true)->count();
@endphp

<x-section
    title="Ready to publish"
    :description="$checklist['isReady'] ? 'Everything is set. Publish when you are ready.' : 'Complete these steps before learners can take the quiz.'"
    icon="list-checks"
    :color="$checklist['isReady'] ? 'leaf' : 'sun'"
    {{ $attributes->merge(['data-publish-checklist' => $checklist['isReady'] ? 'ready' : 'incomplete']) }}
>
    <x-progress :value="$doneCount" :max="3" size="sm" :color="$checklist['isReady'] ? 'leaf' : 'sun'" label="Steps done" show-value :value-text="$doneCount.' of 3'" class="mb-4" />

    <ol role="list" class="flex flex-col gap-4">
        @foreach ($steps as $step)
            <li class="flex items-start gap-3" data-checklist-step="{{ $step['key'] }}" data-done="{{ $step['done'] ? 'true' : 'false' }}">
                @if ($step['done'])
                    <x-icon name="circle-check" class="mt-0.5 size-5 shrink-0 text-leaf-600 dark:text-leaf-400" label="Done" />
                @else
                    <x-icon name="circle-dashed" class="mt-0.5 size-5 shrink-0 text-ink-400 dark:text-ink-500" label="To do" />
                @endif
                <div class="min-w-0 flex-1">
                    <p @class([
                        'text-sm font-semibold',
                        'text-ink-900 dark:text-white' => ! $step['done'],
                        'text-ink-600 dark:text-ink-300' => $step['done'],
                    ])>{{ $step['title'] }}</p>
                    <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">{{ $step['done'] ? $step['done_text'] : $step['todo_text'] }}</p>
                    @if (! $step['done'] && $step['action'] !== null)
                        <x-button variant="soft" size="xs" :icon="$step['action']['icon']" :href="$step['action']['url']" class="mt-2">{{ $step['action']['label'] }}</x-button>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    @if ($checklist['isReady'] && $withPublish && $canEdit)
        <x-slot:footer>
            <div class="flex justify-end">
                <x-quiz.status-actions :quiz="$quiz" ready only="primary" />
            </div>
        </x-slot:footer>
    @endif
</x-section>
