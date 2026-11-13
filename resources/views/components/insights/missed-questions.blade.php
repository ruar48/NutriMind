{{--
    The questions missed most often (LearningAnalytics::mostMissedQuestions()).
    Props: questions (rows), url (?Closure(Quiz): ?string — the item analysis link of a question's quiz).
--}}
@props([
    'questions' => [],
    'url' => null,
    'title' => 'Most-missed questions',
    'description' => 'Highest share of wrong or blank answers in this period',
])

<x-section :title="$title" :description="$description" icon="crosshair" color="berry" {{ $attributes }}>
    @if ($questions === [])
        <x-empty-state
            compact
            icon="circle-check"
            title="No tricky questions yet"
            :description="'Questions show up here once at least '.\App\Services\Analytics\LearningAnalytics::MOST_MISSED_MIN_ANSWERS.' answers come in and some are wrong.'"
        />
    @else
        <ol class="flex flex-col gap-3">
            @foreach ($questions as $row)
                @php
                    $question = $row['question'];
                    $link = $url instanceof \Closure ? $url($question->quiz) : null;
                @endphp
                <li class="rounded-2xl p-4 ring-1 ring-ink-900/8 ring-inset dark:ring-white/10" data-insights-missed-question="{{ $question->id }}">
                    <div class="flex items-start gap-3">
                        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-berry-50 font-display text-sm font-bold text-berry-700 dark:bg-berry-400/10 dark:text-berry-300" aria-hidden="true">{{ $loop->iteration }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-ink-900 wrap-break-word dark:text-ink-50">{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($question->prompt), 160) }}</p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-ink-500 dark:text-ink-400">
                                <x-status-badge :status="$question->type" size="sm" />
                                <span class="truncate">{{ $question->quiz->title }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ $question->quiz->subject->name }}</span>
                            </div>
                        </div>
                    </div>

                    <x-progress
                        class="mt-3"
                        :value="$row['missRate']"
                        color="berry"
                        size="sm"
                        label="Missed by"
                        show-value
                        :value-text="number_format($row['missRate'], 1).'% · '.$row['missed'].' of '.$row['answered'].' answers'"
                    />

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                        <p class="min-w-0 text-xs text-ink-600 dark:text-ink-300">
                            @if ($row['commonWrongAnswer'] !== null)
                                Most chosen wrong answer:
                                <span class="font-semibold text-ink-900 dark:text-white">“{{ \Illuminate\Support\Str::limit($row['commonWrongAnswer'], 60) }}”</span>
                                ({{ $row['commonWrongCount'] }})
                            @else
                                Missed answers were left blank.
                            @endif
                        </p>
                        @if ($link)
                            <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="$link">Item analysis</x-button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</x-section>
