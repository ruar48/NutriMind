<x-layouts.app :title="$student->name">
    <x-page-header
        eyebrow="Learner"
        :title="$student->name"
        description="Progress in the subjects you teach this learner."
        :breadcrumbs="[['label' => 'Learners', 'url' => route('teacher.students.index')], ['label' => $student->name]]"
    >
        @if ($canGiveFeedback)
            <x-slot:actions>
                <x-button icon="message-circle" :href="route('teacher.feedback.create', ['student' => $student->getKey()])">Send feedback</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <x-learner.profile-header :summary="$summary" />
    <x-learner.progress-overview :summary="$summary" />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-learner.weekly-trend :weeks="$summary->weeklyScores" description="Your subjects · average of the attempts submitted each week" class="xl:col-span-2" />
        <x-learner.subject-breakdown :subjects="$summary->subjectBreakdown" title="Your subjects" />
    </div>

    <x-section id="attempts" title="Quiz attempts" description="Every quiz this learner submitted in your subjects, newest first" icon="clipboard-check" color="grape" padding="none">
        @if ($attempts->isEmpty())
            <x-empty-state compact icon="clipboard-check" title="No quiz attempts yet" description="Quizzes the learner submits in the game appear here." />
        @else
            <x-table flush caption="Quiz attempts">
                <x-slot:head>
                    <x-table.th>Quiz</x-table.th>
                    <x-table.th align="right">Attempt</x-table.th>
                    <x-table.th>Score</x-table.th>
                    <x-table.th>Result</x-table.th>
                    <x-table.th align="right">Duration</x-table.th>
                    <x-table.th>Submitted</x-table.th>
                </x-slot:head>

                @foreach ($attempts as $attempt)
                    @php
                        $attemptLink = $attemptUrl($attempt);
                    @endphp
                    <x-table.row data-attempt-row="{{ $attempt->getKey() }}">
                        <x-table.td primary>
                            <div class="flex min-w-48 flex-col gap-1">
                                @if ($attemptLink)
                                    <a href="{{ $attemptLink }}" class="rounded hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $attempt->quiz->title }}</a>
                                @else
                                    <span>{{ $attempt->quiz->title }}</span>
                                @endif
                                <x-subject-chip :subject="$attempt->quiz->subject" size="sm" />
                            </div>
                        </x-table.td>
                        <x-table.td align="right" numeric>#{{ $attempt->attempt_number }}</x-table.td>
                        <x-table.td>
                            <div class="flex items-center gap-2 whitespace-nowrap">
                                <x-score-pill :value="(float) $attempt->percentage" show-band />
                                <span class="text-xs tabular-nums">{{ $attempt->score }} / {{ $attempt->max_score }}</span>
                            </div>
                        </x-table.td>
                        <x-table.td>
                            @if ($attempt->passed)
                                <x-badge color="leaf" icon="circle-check" size="sm">Passed</x-badge>
                            @else
                                <x-badge color="berry" icon="circle-x" size="sm">Not passed</x-badge>
                            @endif
                        </x-table.td>
                        <x-table.td align="right"><x-monitoring.duration :seconds="$attempt->duration_seconds" /></x-table.td>
                        <x-table.td>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-monitoring.time :value="$attempt->submitted_at" />
                                @if ($attempt->is_offline)
                                    <x-badge color="sky" icon="wifi-off" size="sm">Offline</x-badge>
                                @endif
                            </div>
                        </x-table.td>
                    </x-table.row>
                @endforeach
            </x-table>
            @if ($attempts->hasPages())
                <div class="border-t border-ink-900/5 px-5 py-3 dark:border-white/8">{{ $attempts->fragment('attempts')->links() }}</div>
            @endif
        @endif
    </x-section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-learner.missions-list :missions="$missions" title="Mission progress" description="Missions in your subjects, most recently played first" />

        <x-section title="Learning modules" description="Game activities completed in each module of your subjects" icon="map" color="teal" padding="none">
            @if ($modules->isEmpty())
                <x-empty-state compact icon="map" title="No modules yet" description="Published learning modules for this learner’s grade in your subjects appear here." />
            @else
                <ul class="divide-y divide-ink-900/5 dark:divide-white/8">
                    @foreach ($modules as $module)
                        <li class="flex flex-col gap-3 px-5 py-4 sm:px-6" data-module-row="{{ $module->getKey() }}">
                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                                <div class="min-w-0">
                                    <p class="font-medium break-words text-ink-900 dark:text-ink-100">{{ $module->title }}</p>
                                    <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                                        {{ collect([$module->world_name, $module->quarter ? 'Quarter '.$module->quarter : null, $module->competency_code])->filter()->implode(' · ') ?: 'Learning module' }}
                                    </p>
                                </div>
                                <x-subject-chip :subject="$module->subject" size="sm" />
                            </div>
                            @if ($module->activities_total > 0)
                                <x-progress
                                    :value="$module->activities_completed"
                                    :max="$module->activities_total"
                                    color="teal"
                                    size="sm"
                                    label="Activities completed"
                                    show-value
                                    :value-text="$module->activities_completed.' of '.$module->activities_total"
                                />
                            @else
                                <p class="text-xs text-ink-500 dark:text-ink-400">No published activities yet.</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-section>
    </div>

    @if ($canGiveFeedback)
        <x-section title="Your feedback" :description="trans_choice('You sent :count message to this learner|You sent :count messages to this learner', $feedbackCount)" icon="message-circle" color="grape">
            <x-slot:actions>
                @if ($feedbackCount > $feedback->count())
                    <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('teacher.feedback.index', ['student' => $student->getKey()])">View all</x-button>
                @endif
                <x-button variant="soft" size="sm" icon="plus" :href="route('teacher.feedback.create', ['student' => $student->getKey()])">Send feedback</x-button>
            </x-slot:actions>

            @if ($feedback->isEmpty())
                <x-empty-state compact icon="message-circle" title="No feedback yet" description="Praise, encouragement and guidance you send appear here and in the Parent Portal." />
            @else
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    @foreach ($feedback as $item)
                        <x-monitoring.feedback-card :feedback="$item" :show-learner="false" :attempt-url="$item->quizAttempt ? $attemptUrl($item->quizAttempt) : null" />
                    @endforeach
                </div>
            @endif
        </x-section>
    @endif

    <x-learner.rewards-shelf :rewards="$summary->rewards" />
</x-layouts.app>
