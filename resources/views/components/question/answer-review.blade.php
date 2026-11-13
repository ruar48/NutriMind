@props([
    'question',
    'answer' => null,
    'number' => null,
    'reveal' => true,
    'answerLabel' => "Learner's answer",
])

@php
    $reveal = filter_var($reveal, FILTER_VALIDATE_BOOLEAN);
    $learnerAnswer = $answer?->answer;
    $isCorrect = $answer !== null && (bool) $answer->is_correct;
    $isAnswered = $isCorrect || ($answer !== null && filled($learnerAnswer));
    $status = match (true) {
        $isCorrect => 'correct',
        $isAnswered => 'incorrect',
        default => 'unanswered',
    };

    $statuses = [
        'correct' => ['label' => 'Correct', 'color' => 'leaf', 'icon' => 'circle-check'],
        'incorrect' => ['label' => 'Incorrect', 'color' => 'berry', 'icon' => 'circle-x'],
        'unanswered' => ['label' => 'Not answered', 'color' => 'slate', 'icon' => 'circle-dashed'],
    ];
    $statusTone = $statuses[$status];

    $pointsPossible = (int) $question->points;
    $pointsEarned = (int) ($answer?->points_awarded ?? 0);

    $usesChoices = $question->type?->usesChoices() ?? false;
    $choices = $usesChoices ? ($question->choices ?? []) : [];
    $chosenIndex = $isAnswered && $usesChoices ? trim((string) $learnerAnswer) : null;
    $chosenIsListed = $chosenIndex !== null && in_array($chosenIndex, array_map('strval', array_keys($choices)), true);
    $acceptedAnswers = $reveal && ! $usesChoices ? $question->acceptedAnswers() : [];

    $choiceTones = [
        'chosen-correct' => [
            'row' => 'bg-leaf-50 ring-leaf-600/30 dark:bg-leaf-400/10 dark:ring-leaf-400/35',
            'letter' => 'bg-leaf-600 text-white dark:bg-leaf-500 dark:text-ink-950',
        ],
        'chosen-incorrect' => [
            'row' => 'bg-berry-50 ring-berry-600/30 dark:bg-berry-400/10 dark:ring-berry-400/35',
            'letter' => 'bg-berry-600 text-white dark:bg-berry-500 dark:text-ink-950',
        ],
        'correct' => [
            'row' => 'bg-leaf-50/40 ring-leaf-600/25 dark:bg-leaf-400/5 dark:ring-leaf-400/25',
            'letter' => 'bg-leaf-100 text-leaf-800 dark:bg-leaf-400/15 dark:text-leaf-200',
        ],
        'plain' => [
            'row' => 'bg-white ring-ink-900/10 dark:bg-white/3 dark:ring-white/10',
            'letter' => 'bg-ink-900/5 text-ink-700 dark:bg-white/8 dark:text-ink-200',
        ],
    ];

    $answerBoxTones = [
        'correct' => ['box' => 'bg-leaf-50 ring-leaf-600/25 dark:bg-leaf-400/10 dark:ring-leaf-400/30', 'icon' => 'text-leaf-600 dark:text-leaf-300'],
        'incorrect' => ['box' => 'bg-berry-50 ring-berry-600/25 dark:bg-berry-400/10 dark:ring-berry-400/30', 'icon' => 'text-berry-600 dark:text-berry-300'],
        'unanswered' => ['box' => 'bg-ink-50 ring-ink-900/10 dark:bg-white/3 dark:ring-white/10', 'icon' => 'text-ink-400 dark:text-ink-500'],
    ];
@endphp

<x-card {{ $attributes->merge(['data-answer-review' => $status]) }}>
    <div class="@container flex min-w-0 flex-col gap-4">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            @if ($number !== null)
                <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-ink-900/5 font-display text-base font-bold text-ink-800 tabular-nums dark:bg-white/8 dark:text-ink-100">
                    <span class="sr-only">Question </span>{{ $number }}
                </span>
            @endif
            <x-status-badge :status="$question->type" />
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <span class="text-sm text-ink-600 tabular-nums dark:text-ink-300" data-answer-review-points><span class="font-semibold text-ink-900 dark:text-white">{{ $pointsEarned }}</span> / {{ $pointsPossible }} {{ \Illuminate\Support\Str::plural('point', $pointsPossible) }}</span>
                <x-badge :color="$statusTone['color']" :icon="$statusTone['icon']" data-answer-review-status>{{ $statusTone['label'] }}</x-badge>
            </div>
        </div>

        <p class="text-base font-medium wrap-break-word whitespace-pre-line text-ink-900 dark:text-ink-50">{{ $question->prompt }}</p>

        <x-question.media :question="$question" />

        @if ($usesChoices)
            <ul role="list" class="flex flex-col gap-2" aria-label="Choices">
                @foreach ($choices as $index => $choiceText)
                    @php
                        $isChosen = $chosenIndex !== null && $chosenIndex === (string) $index;
                        $isCorrectChoice = $reveal && $question->isCorrectAnswer((string) $index);
                        $tone = $choiceTones[match (true) {
                            $isChosen && $isCorrect => 'chosen-correct',
                            $isChosen => 'chosen-incorrect',
                            $isCorrectChoice => 'correct',
                            default => 'plain',
                        }];
                    @endphp
                    <li
                        @class(['flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl px-3 py-2.5 ring-1 ring-inset', $tone['row']])
                        data-choice="{{ $index }}"
                        @if ($isChosen) data-chosen @endif
                        @if ($isCorrectChoice) data-correct-choice @endif
                    >
                        <span @class(['grid size-8 shrink-0 place-items-center rounded-lg font-display text-sm font-bold', $tone['letter']])>{{ $question->choiceLetter((int) $index) }}</span>
                        <span class="min-w-0 flex-1 text-sm wrap-break-word text-ink-800 dark:text-ink-100">{{ $choiceText }}</span>
                        @if ($isChosen || $isCorrectChoice)
                            <span class="flex flex-wrap items-center gap-1.5">
                                @if ($isChosen)
                                    <x-badge :color="$isCorrect ? 'leaf' : 'berry'" :icon="$isCorrect ? 'circle-check' : 'circle-x'" size="sm">
                                        {{ $answerLabel }}<span class="sr-only">{{ $isCorrect ? ' (correct)' : ' (incorrect)' }}</span>
                                    </x-badge>
                                @endif
                                @if ($isCorrectChoice)
                                    <x-badge color="leaf" icon="check" size="sm">Correct answer</x-badge>
                                @endif
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($chosenIndex !== null && ! $chosenIsListed)
                <p class="text-sm text-ink-600 dark:text-ink-300">
                    {{ $answerLabel }}: <span class="font-medium wrap-break-word text-ink-900 dark:text-white">{{ $question->answerText($learnerAnswer) }}</span>
                </p>
            @endif
        @else
            <dl class="grid grid-cols-1 gap-3 @lg:grid-cols-2">
                <div @class(['min-w-0 rounded-xl px-3.5 py-3 ring-1 ring-inset', $answerBoxTones[$status]['box']]) data-answer-review-typed>
                    <dt class="text-xs font-medium text-ink-600 dark:text-ink-300">{{ $answerLabel }}</dt>
                    <dd class="mt-1 flex items-start gap-2 text-sm">
                        <x-icon :name="$statusTone['icon']" @class(['mt-0.5 size-4', $answerBoxTones[$status]['icon']]) />
                        <span @class([
                            'min-w-0 wrap-break-word',
                            'font-medium text-ink-900 dark:text-white' => $isAnswered,
                            'text-ink-500 italic dark:text-ink-400' => ! $isAnswered,
                        ])>{{ $question->answerText($learnerAnswer) }}</span>
                    </dd>
                </div>
                @if ($reveal)
                    <div class="min-w-0 rounded-xl px-3.5 py-3 ring-1 ring-leaf-600/25 ring-inset dark:ring-leaf-400/25" data-answer-review-accepted>
                        <dt class="text-xs font-medium text-ink-600 dark:text-ink-300">{{ count($acceptedAnswers) === 1 ? 'Accepted answer' : 'Accepted answers' }}</dt>
                        <dd class="mt-1.5">
                            @if ($acceptedAnswers === [])
                                <span class="text-sm text-ink-500 italic dark:text-ink-400">None set</span>
                            @else
                                <ul role="list" class="flex flex-wrap gap-1.5">
                                    @foreach ($acceptedAnswers as $acceptedAnswer)
                                        <li class="inline-flex max-w-full items-center gap-1 rounded-lg bg-leaf-50 px-2 py-0.5 text-sm font-medium wrap-break-word text-leaf-800 ring-1 ring-leaf-600/20 ring-inset dark:bg-leaf-400/10 dark:text-leaf-200 dark:ring-leaf-400/25">
                                            <x-icon name="check" class="size-3.5" stroke-width="2.25" />
                                            <span class="min-w-0">{{ $acceptedAnswer }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>
        @endif

        @if ($reveal && filled($question->explanation))
            <div class="flex gap-3 rounded-xl bg-sky-50 px-3.5 py-3 text-sm ring-1 ring-sky-600/15 ring-inset dark:bg-sky-400/10 dark:ring-sky-400/20" data-answer-review-explanation>
                <x-icon name="lightbulb" class="mt-0.5 size-4 text-sky-600 dark:text-sky-300" />
                <div class="min-w-0">
                    <p class="font-semibold text-sky-950 dark:text-sky-100">Explanation</p>
                    <p class="mt-0.5 wrap-break-word whitespace-pre-line text-sky-900/85 dark:text-sky-100/80">{{ $question->explanation }}</p>
                </div>
            </div>
        @endif
    </div>
</x-card>
