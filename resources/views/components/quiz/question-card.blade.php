{{--
    One question in the quiz builder: type, points, prompt, media, answer key and explanation, with the teacher's actions.
    editable: the quiz is a draft nobody attempted (edit, move, delete); canSaveToBank: the teacher may use the question bank.
--}}
@props([
    'question',
    'quiz',
    'number',
    'editable' => false,
    'isFirst' => false,
    'isLast' => false,
    'canSaveToBank' => false,
])

<x-card
    id="question-{{ $question->id }}"
    {{ $attributes->class('scroll-mt-24') }}
    data-quiz-question="{{ $question->id }}"
>
    <div class="@container flex min-w-0 flex-col gap-4">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-leaf-600 font-display text-base font-bold text-white tabular-nums shadow-card dark:bg-leaf-500 dark:text-ink-950">
                <span class="sr-only">Question </span>{{ $number }}
            </span>
            <x-status-badge :status="$question->type" />
            <x-badge color="sun" icon="star">{{ trans_choice(':count point|:count points', $question->points) }}</x-badge>
            @if ($question->question_bank_item_id !== null)
                <x-badge color="grape" icon="library">From question bank</x-badge>
            @endif

            <div class="ml-auto flex items-center gap-1">
                @if ($editable)
                    <form method="POST" action="{{ route('teacher.quizzes.questions.position.update', [$quiz, $question]) }}" data-submit-once>
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="direction" value="up">
                        <x-icon-button type="submit" icon="arrow-up" :label="'Move question '.$number.' up'" :disabled="$isFirst" />
                    </form>
                    <form method="POST" action="{{ route('teacher.quizzes.questions.position.update', [$quiz, $question]) }}" data-submit-once>
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="direction" value="down">
                        <x-icon-button type="submit" icon="arrow-down" :label="'Move question '.$number.' down'" :disabled="$isLast" />
                    </form>
                    <x-icon-button :href="route('teacher.quizzes.questions.edit', [$quiz, $question])" icon="pencil" :label="'Edit question '.$number" />
                @endif

                @if ($editable || $canSaveToBank)
                    <x-dropdown variant="icon" icon="ellipsis-vertical" :label="'More actions for question '.$number">
                        @if ($canSaveToBank)
                            <form method="POST" action="{{ route('teacher.quizzes.questions.bank.store', [$quiz, $question]) }}" data-submit-once>
                                @csrf
                                <x-dropdown.item type="submit" icon="bookmark">Save to question bank</x-dropdown.item>
                            </form>
                        @endif
                        @if ($editable)
                            @if ($canSaveToBank)
                                <x-dropdown.divider />
                            @endif
                            <x-confirm-dialog
                                :action="route('teacher.quizzes.questions.destroy', [$quiz, $question])"
                                :title="'Delete question '.$number.'?'"
                                message="The question and its image or video are removed from this quiz. This cannot be undone."
                                confirm="Delete question"
                                button="Delete question"
                                button-variant="menu"
                            />
                        @endif
                    </x-dropdown>
                @endif
            </div>
        </div>

        <p class="text-base font-medium wrap-break-word whitespace-pre-line text-ink-900 dark:text-ink-50">{{ $question->prompt }}</p>

        <x-question.media :question="$question" />

        <x-quiz.answer-key :question="$question" />

        @if (filled($question->explanation))
            <div class="flex gap-3 rounded-xl bg-sky-50 px-3.5 py-3 ring-1 ring-sky-600/15 ring-inset dark:bg-sky-400/10 dark:ring-sky-400/20" data-quiz-question-explanation>
                <x-icon name="lightbulb" class="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-300" />
                <div class="min-w-0 text-sm">
                    <p class="font-medium text-sky-900 dark:text-sky-100">Explanation</p>
                    <p class="mt-0.5 wrap-break-word whitespace-pre-line text-sky-900/80 dark:text-sky-100/80">{{ $question->explanation }}</p>
                </div>
            </div>
        @endif
    </div>
</x-card>
