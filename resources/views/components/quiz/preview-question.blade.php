{{--
    One question as learners see it in the quiz, for the review page. showAnswers adds the answer key and explanation.
--}}
@props([
    'question',
    'number',
    'total',
    'showAnswers' => false,
])

<x-card {{ $attributes->class('scroll-mt-24') }} id="preview-question-{{ $question->id }}" data-preview-question="{{ $question->id }}">
    <div class="@container flex min-w-0 flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-semibold tracking-[0.12em] text-leaf-700 uppercase dark:text-leaf-300">Question {{ $number }} of {{ $total }}</p>
            <x-badge color="sun" icon="star" size="sm">{{ trans_choice(':count point|:count points', $question->points) }}</x-badge>
        </div>

        <p class="font-display text-lg leading-snug font-semibold wrap-break-word whitespace-pre-line text-ink-950 dark:text-white">{{ $question->prompt }}</p>

        <x-question.media :question="$question" />

        @if ($question->type === \App\Enums\QuestionType::TrueFalse)
            <p class="text-sm text-ink-500 dark:text-ink-400">Is this statement true or false?</p>
        @endif

        <x-quiz.answer-key :question="$question" :reveal="$showAnswers" />

        @if ($showAnswers && filled($question->explanation))
            <div class="flex gap-3 rounded-xl bg-sky-50 px-3.5 py-3 ring-1 ring-sky-600/15 ring-inset dark:bg-sky-400/10 dark:ring-sky-400/20" data-preview-explanation>
                <x-icon name="lightbulb" class="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-300" />
                <div class="min-w-0 text-sm">
                    <p class="font-medium text-sky-900 dark:text-sky-100">Explanation</p>
                    <p class="mt-0.5 wrap-break-word whitespace-pre-line text-sky-900/80 dark:text-sky-100/80">{{ $question->explanation }}</p>
                </div>
            </div>
        @endif
    </div>
</x-card>
