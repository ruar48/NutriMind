{{--
    The answers of a question (QuizQuestion or QuestionBankItem).
    reveal = true: the correct choice is highlighted / the accepted answers are listed (teacher answer key).
    reveal = false: the choices as learners see them / an empty answer box for identification (learner preview).
--}}
@props([
    'question',
    'reveal' => true,
])

@php
    $usesChoices = $question->type?->usesChoices() ?? false;
    $choices = $usesChoices ? array_values((array) ($question->choices ?? [])) : [];
    $acceptedAnswers = $question->acceptedAnswers();
@endphp

<div {{ $attributes->class('min-w-0') }} data-answer-key="{{ $reveal ? 'revealed' : 'hidden' }}">
    @if ($usesChoices)
        <ul role="list" class="grid grid-cols-1 gap-2 @lg:grid-cols-2" aria-label="Choices">
            @foreach ($choices as $index => $choiceText)
                @php
                    $isCorrectChoice = $reveal && $question->isCorrectAnswer((string) $index);
                @endphp
                <li
                    @class([
                        'flex min-w-0 items-center gap-3 rounded-xl px-3 py-2.5 ring-1 ring-inset',
                        'bg-leaf-50 ring-leaf-600/30 dark:bg-leaf-400/10 dark:ring-leaf-400/35' => $isCorrectChoice,
                        'bg-white ring-ink-900/10 dark:bg-white/3 dark:ring-white/10' => ! $isCorrectChoice,
                    ])
                    data-choice="{{ $index }}"
                    @if ($isCorrectChoice) data-correct-choice @endif
                >
                    <span @class([
                        'grid size-8 shrink-0 place-items-center rounded-lg font-display text-sm font-bold',
                        'bg-leaf-600 text-white dark:bg-leaf-500 dark:text-ink-950' => $isCorrectChoice,
                        'bg-ink-900/5 text-ink-700 dark:bg-white/8 dark:text-ink-200' => ! $isCorrectChoice,
                    ])>{{ $question->choiceLetter((int) $index) }}</span>
                    <span class="min-w-0 flex-1 text-sm wrap-break-word text-ink-800 dark:text-ink-100">{{ $choiceText }}</span>
                    @if ($isCorrectChoice)
                        <x-badge color="leaf" icon="check" size="sm">Correct<span class="sr-only"> answer</span></x-badge>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($reveal && $acceptedAnswers === [])
            <p class="mt-2 flex items-center gap-1.5 text-sm font-medium text-berry-700 dark:text-berry-300">
                <x-icon name="triangle-alert" class="size-4" />
                No correct choice is marked.
            </p>
        @endif
    @elseif ($reveal)
        <div class="rounded-xl px-3.5 py-3 ring-1 ring-leaf-600/25 ring-inset dark:ring-leaf-400/25" data-answer-key-accepted>
            <p class="text-xs font-medium text-ink-600 dark:text-ink-300">{{ count($acceptedAnswers) === 1 ? 'Accepted answer' : 'Accepted answers' }}</p>
            @if ($acceptedAnswers === [])
                <p class="mt-1 text-sm text-ink-500 italic dark:text-ink-400">No accepted answers yet.</p>
            @else
                <ul role="list" class="mt-1.5 flex flex-wrap gap-1.5">
                    @foreach ($acceptedAnswers as $acceptedAnswer)
                        <li><x-badge color="leaf" icon="check" size="sm">{{ $acceptedAnswer }}</x-badge></li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-ink-500 dark:text-ink-400">Letter case and extra spaces are ignored when answers are checked.</p>
            @endif
        </div>
    @else
        <div class="flex h-12 items-center gap-2 rounded-xl border-2 border-dashed border-ink-900/15 px-3.5 text-sm text-ink-500 dark:border-white/15 dark:text-ink-400">
            <x-icon name="pencil" class="size-4" />
            Learners type their answer here.
        </div>
    @endif
</div>
