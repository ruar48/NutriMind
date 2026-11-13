@php
    $percent = fn (?float $value): string => $value === null ? '—' : rtrim(rtrim(number_format($value, 1), '0'), '.').'%';
    $averageBand = \App\Enums\PerformanceBand::fromScore($summary->averageScore);
    $totalPoints = (int) $quiz->questions_sum_points;
    $exportUrl = route('teacher.quizzes.results.export', array_filter(['quiz' => $quiz, 'classroom' => $classroom?->getKey()]));
    $scopeLabel = $classroom ? $classroom->name : trans_choice(':count classroom|:count classrooms', $classrooms->count());
@endphp

<x-layouts.app :title="'Results · '.$quiz->title">
    <x-page-header
        eyebrow="Quiz results"
        :title="$quiz->title"
        :description="collect([
            $quiz->grade_level->label(),
            $quiz->subject->name,
            trans_choice(':count question|:count questions', $quiz->questions_count),
            trans_choice(':count point|:count points', $totalPoints),
            'Passing score '.$quiz->passing_score.'%',
        ])->implode(' · ')"
        :breadcrumbs="[
            ['label' => 'Quizzes', 'url' => route('teacher.quizzes.index')],
            ['label' => $quiz->title, 'url' => route('teacher.quizzes.show', $quiz)],
            ['label' => 'Results'],
        ]"
    >
        <x-slot:meta>
            <x-status-badge :status="$quiz->status" />
            <x-subject-chip :subject="$quiz->subject" />
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" icon="clipboard-list" :href="route('teacher.quizzes.show', $quiz)">Open quiz</x-button>
            @if ($canExport)
                <x-button icon="download" :href="$exportUrl">Export CSV</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($classrooms->isEmpty())
        <x-alert variant="info" title="Not assigned to a classroom">
            Assign this quiz to one of your classrooms so its learners can take it in the game.
        </x-alert>
    @else
        <x-filter-bar :action="route('teacher.quizzes.results.show', $quiz)" :search="false" :reset-url="route('teacher.quizzes.results.show', $quiz)">
            <x-form.select
                name="classroom"
                label="Classroom"
                hide-label
                placeholder="All assigned classrooms"
                :options="$classrooms->mapWithKeys(fn ($option) => [$option->getKey() => $option->name.($option->isArchived() ? ' (archived)' : '')])->all()"
                :value="$classroom?->getKey()"
                :old="false"
                class="sm:w-64"
            />
        </x-filter-bar>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Assigned learners" :value="number_format($summary->assignedLearners)" icon="users" color="sky" :hint="'In '.$scopeLabel" data-animate style="--stagger: 1" />
        <x-stat-card label="Attempted" :value="number_format($summary->attemptedLearners)" icon="user-check" color="leaf" :hint="'of '.number_format($summary->assignedLearners).' assigned learners'" data-animate style="--stagger: 2" />
        <x-stat-card label="Completion rate" :value="$percent($summary->completionRate)" icon="gauge" color="teal" hint="Assigned learners who submitted" data-animate style="--stagger: 3">
            <x-progress :value="$summary->completionRate ?? 0" color="teal" size="sm" label="Completion rate" />
        </x-stat-card>
        <x-stat-card label="Average score" :value="$percent($summary->averageScore)" icon="target" :color="$averageBand->color()" :hint="$summary->averageScore === null ? 'No submissions yet' : 'Best attempts · '.$averageBand->label()" data-animate style="--stagger: 4" />
        <x-stat-card label="Pass rate" :value="$percent($summary->passRate)" icon="circle-check" color="leaf" :hint="'Best attempt at '.$quiz->passing_score.'% or more'" data-animate style="--stagger: 5" />
        <x-stat-card label="Average duration" :value="\App\Services\Monitoring\QuizResults::formatDuration($summary->averageDurationSeconds)" icon="timer" color="grape" hint="Minutes : seconds per attempt" data-animate style="--stagger: 6" />
        <x-stat-card label="Submitted attempts" :value="number_format($summary->submittedAttempts)" icon="clipboard-check" color="indigo" :hint="trans_choice('By :count learner|By :count learners', $summary->learnersWithResults)" data-animate style="--stagger: 7" />
        <x-stat-card label="Offline submissions" :value="number_format($summary->offlineSubmissions)" icon="wifi-off" color="sky" hint="Played offline, synced later" data-animate style="--stagger: 8" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-section title="Score distribution" description="Each learner’s best attempt, in 10-point ranges" icon="chart-column" color="leaf" class="lg:col-span-2">
            <x-chart.histogram
                label="Best score distribution"
                x-label="Best score (%)"
                series-name="Learners"
                :buckets="$summary->histogram"
                empty-text="The distribution appears after the first submissions."
            />
        </x-section>

        <x-section title="Not yet attempted" :description="trans_choice(':count assigned learner has not submitted|:count assigned learners have not submitted', $summary->notAttempted->count())" icon="hourglass" color="sun" padding="none">
            @if ($summary->assignedLearners === 0)
                <x-empty-state compact icon="users" title="No assigned learners" description="Learners of the classrooms this quiz is assigned to appear here." />
            @elseif ($summary->notAttempted->isEmpty())
                <x-empty-state compact icon="party-popper" title="Everyone has submitted" description="Every assigned learner has at least one submitted attempt." />
            @else
                <ul class="max-h-96 divide-y divide-ink-900/5 overflow-y-auto dark:divide-white/8" data-not-attempted>
                    @foreach ($summary->notAttempted as $learner)
                        <li class="flex items-center gap-3 px-5 py-3 sm:px-6">
                            <x-avatar :name="$learner->name" size="sm" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('teacher.students.show', $learner) }}" class="block truncate text-sm font-medium text-ink-900 hover:text-leaf-700 hover:underline dark:text-ink-100 dark:hover:text-leaf-300">{{ $learner->name }}</a>
                                <p class="truncate text-xs text-ink-500 dark:text-ink-400">{{ $learner->enrolledClassrooms->pluck('name')->implode(', ') }}</p>
                            </div>
                            <span class="shrink-0 text-xs text-ink-500 dark:text-ink-400">
                                <x-monitoring.time :value="$learner->studentProfile?->last_active_at" empty="Not played yet" />
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-section>
    </div>

    <x-section id="attempts" title="Attempts" :description="trans_choice(':count submitted attempt|:count submitted attempts', $attempts->total())" icon="clipboard-check" color="grape" padding="none">
        @if ($attempts->isEmpty())
            <x-empty-state compact icon="clipboard-check" title="No submissions yet" :description="$classroom ? 'Nobody in '.$classroom->name.' has submitted this quiz yet.' : 'Attempts appear here as learners finish the quiz in the game.'" />
        @else
            <x-table flush caption="Submitted attempts">
                <x-slot:head>
                    <x-table.th>Learner</x-table.th>
                    <x-table.th align="right">Attempt</x-table.th>
                    <x-table.th align="right">Score</x-table.th>
                    <x-table.th sort="score" default-direction="desc">Percent</x-table.th>
                    <x-table.th>Band</x-table.th>
                    <x-table.th>Result</x-table.th>
                    <x-table.th align="right">Duration</x-table.th>
                    <x-table.th sort="date" default-direction="desc">Submitted</x-table.th>
                    <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
                </x-slot:head>

                @foreach ($attempts as $attempt)
                    <x-table.row data-attempt-row="{{ $attempt->getKey() }}">
                        <x-table.td primary>
                            <div class="flex min-w-48 items-center gap-3">
                                <x-avatar :name="$attempt->student->name" size="sm" />
                                <div class="min-w-0">
                                    @if (in_array((int) $attempt->student_id, $linkedLearnerIds, true))
                                        <a href="{{ route('teacher.students.show', $attempt->student) }}" class="block truncate rounded hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $attempt->student->name }}</a>
                                    @else
                                        <span class="block truncate">{{ $attempt->student->name }}</span>
                                    @endif
                                    <p class="truncate text-xs font-normal text-ink-500 dark:text-ink-400">{{ $attempt->classroom?->name ?? 'No classroom recorded' }}</p>
                                </div>
                            </div>
                        </x-table.td>
                        <x-table.td align="right" numeric>#{{ $attempt->attempt_number }}</x-table.td>
                        <x-table.td align="right" numeric class="whitespace-nowrap">{{ $attempt->score }} / {{ $attempt->max_score }}</x-table.td>
                        <x-table.td><x-score-pill :value="(float) $attempt->percentage" /></x-table.td>
                        <x-table.td><x-status-badge :status="$attempt->band()" size="sm" /></x-table.td>
                        <x-table.td>
                            @if ($attempt->passed)
                                <x-badge color="leaf" icon="circle-check" size="sm">Passed</x-badge>
                            @else
                                <x-badge color="berry" icon="circle-x" size="sm">Not passed</x-badge>
                            @endif
                        </x-table.td>
                        <x-table.td align="right"><x-monitoring.duration :seconds="$attempt->duration_seconds" /></x-table.td>
                        <x-table.td>
                            <div class="flex flex-col items-start gap-1">
                                <x-monitoring.time :value="$attempt->submitted_at" />
                                @if ($attempt->is_offline)
                                    <x-badge color="sky" icon="wifi-off" size="sm" :title="$attempt->synced_at ? 'Synced '.$attempt->synced_at->format('M j, Y g:i A') : 'Not synced yet'">
                                        Offline · {{ $attempt->synced_at ? 'synced '.$attempt->synced_at->format('M j, g:i A') : 'not synced' }}
                                    </x-badge>
                                @endif
                            </div>
                        </x-table.td>
                        <x-table.td align="right">
                            <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('teacher.attempts.show', $attempt)">
                                Review<span class="sr-only"> {{ $attempt->student->name }}’s attempt {{ $attempt->attempt_number }}</span>
                            </x-button>
                        </x-table.td>
                    </x-table.row>
                @endforeach
            </x-table>
            @if ($attempts->hasPages())
                <div class="border-t border-ink-900/5 px-5 py-3 dark:border-white/8">{{ $attempts->fragment('attempts')->links() }}</div>
            @endif
        @endif
    </x-section>

    <x-section id="item-analysis" title="Item analysis" description="How each question performed across the submitted attempts" icon="list-checks" color="teal" padding="none">
        @if (count($items) === 0)
            <x-empty-state compact icon="list-checks" title="No questions yet" description="Add questions to the quiz to see how each one performs." />
        @else
            <x-table flush caption="Item analysis by question">
                <x-slot:head>
                    <x-table.th align="right">#</x-table.th>
                    <x-table.th>Question</x-table.th>
                    <x-table.th>Answered correctly</x-table.th>
                    <x-table.th>Difficulty</x-table.th>
                    <x-table.th>Correct answer</x-table.th>
                    <x-table.th>Most-chosen wrong answer</x-table.th>
                </x-slot:head>

                @foreach ($items as $item)
                    <x-table.row :id="'question-'.$item->question->getKey()" data-item-row="{{ $item->question->getKey() }}">
                        <x-table.td align="right" numeric class="align-top font-semibold">{{ $item->number }}</x-table.td>
                        <x-table.td class="align-top">
                            <div class="flex min-w-64 max-w-md flex-col gap-2">
                                <p class="text-sm break-words text-ink-900 dark:text-ink-100">{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($item->question->prompt), 160) }}</p>
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-status-badge :status="$item->question->type" size="sm" />
                                    <span class="text-xs text-ink-500 dark:text-ink-400">{{ trans_choice(':count point|:count points', $item->question->points) }}</span>
                                </div>
                                <x-question.media :question="$item->question" compact />
                            </div>
                        </x-table.td>
                        <x-table.td class="align-top">
                            @if ($item->difficultyIndex === null)
                                <span class="text-ink-400 dark:text-ink-500">No attempts yet</span>
                            @else
                                <div class="w-40">
                                    <x-progress :value="$item->percentCorrect()" :color="$item->difficulty->color()" size="sm" :label="number_format($item->correct).' of '.number_format($item->responses)" show-value />
                                    <p class="mt-1 text-xs text-ink-500 tabular-nums dark:text-ink-400">p = {{ number_format($item->difficultyIndex, 2) }}</p>
                                </div>
                            @endif
                        </x-table.td>
                        <x-table.td class="align-top">
                            @if ($item->difficulty)
                                <x-status-badge :status="$item->difficulty" size="sm" />
                            @else
                                <span class="text-ink-400 dark:text-ink-500">—</span>
                            @endif
                        </x-table.td>
                        <x-table.td class="align-top">
                            <span class="block max-w-56 break-words text-ink-800 dark:text-ink-100">{{ $item->question->correctAnswerText() ?: '—' }}</span>
                        </x-table.td>
                        <x-table.td class="align-top">
                            @if ($item->mostChosenWrongAnswer !== null)
                                <span class="block max-w-56 font-medium break-words text-berry-700 dark:text-berry-300" data-wrong-answer>{{ $item->mostChosenWrongAnswer }}</span>
                                <span class="text-xs text-ink-500 dark:text-ink-400">{{ trans_choice('Chosen in :count attempt|Chosen in :count attempts', $item->mostChosenWrongCount) }}</span>
                            @else
                                <span class="text-ink-400 dark:text-ink-500">None</span>
                            @endif
                        </x-table.td>
                    </x-table.row>
                @endforeach
            </x-table>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-ink-900/5 px-5 py-3 text-xs text-ink-500 sm:px-6 dark:border-white/8 dark:text-ink-400">
                <span>Difficulty index p = share of attempts that answered correctly.</span>
                @foreach (\App\Services\Monitoring\ItemDifficulty::cases() as $difficulty)
                    <span class="inline-flex items-center gap-1.5"><x-status-badge :status="$difficulty" size="sm" /> {{ $difficulty->description() }}</span>
                @endforeach
            </div>
        @endif
    </x-section>
</x-layouts.app>
