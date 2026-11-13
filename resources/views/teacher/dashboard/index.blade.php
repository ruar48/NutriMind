@php
    $percent = fn (?float $value): string => $value === null ? '—' : number_format($value, 1).'%';
    $learnerCount = $monthOverview['learners'];
    $completionRate = $completionTotals['assigned'] === 0 ? null : round($completionTotals['submitted'] / $completionTotals['assigned'] * 100, 1);
    $heroDescription = $classrooms->isEmpty()
        ? 'Your classrooms, quizzes and learners’ game progress will show up here.'
        : number_format($learnerCount).' '.\Illuminate\Support\Str::plural('learner', $learnerCount).' across '.$classrooms->count().' '.\Illuminate\Support\Str::plural('classroom', $classrooms->count()).'. Here is how they are doing.';
    $actions = array_values(array_filter([
        $can['createQuiz'] ? ['label' => 'New quiz', 'description' => 'Build a quiz with images or video', 'icon' => 'clipboard-check', 'color' => 'grape', 'url' => route('teacher.quizzes.create')] : null,
        $can['createClassroom'] ? ['label' => 'New classroom', 'description' => 'Set up a class and its learners', 'icon' => 'school', 'color' => 'leaf', 'url' => route('teacher.classrooms.create')] : null,
        $can['useQuestionBank'] ? ['label' => 'Question bank', 'description' => 'Reuse and share questions', 'icon' => 'library', 'color' => 'sky', 'url' => route('teacher.question-bank.index')] : null,
        $can['giveFeedback'] ? ['label' => 'Send feedback', 'description' => 'Praise or guide a learner', 'icon' => 'message-circle', 'color' => 'pink', 'url' => route('teacher.feedback.create')] : null,
        ['label' => 'Mission progress', 'description' => 'Who finished which mission', 'icon' => 'flag', 'color' => 'carrot', 'url' => route('teacher.missions.index')],
        ['label' => 'Reports', 'description' => 'Printable reports and CSV files', 'icon' => 'file-spreadsheet', 'color' => 'indigo', 'url' => route('teacher.reports.index')],
    ]));
@endphp

<x-layouts.app title="Dashboard" description="Your classrooms, learners and quiz results at a glance.">
    <x-hero-banner eyebrow="Teacher Portal" color="leaf" :title="$greeting" :description="$heroDescription">
        <div class="flex flex-wrap gap-2">
            @if ($currentTerm)
                <x-badge color="sky" icon="calendar-range" size="lg">{{ $currentTerm->name }} · SY {{ $currentTerm->schoolYear->name }}</x-badge>
            @else
                <x-badge color="slate" icon="calendar-range" size="lg">No active quarter</x-badge>
            @endif
            <x-badge color="sun" icon="calendar-check" size="lg" data-insights-due-this-week="{{ $quizzesDueThisWeek }}">{{ trans_choice(':count quiz due this week|:count quizzes due this week', $quizzesDueThisWeek) }}</x-badge>
            <x-badge color="carrot" icon="flame" size="lg">{{ trans_choice(':count mission completed this week|:count missions completed this week', $weekOverview['missionsCompleted']) }}</x-badge>
        </div>
        <x-slot:actions>
            @if ($can['createQuiz'])
                <x-button variant="secondary" icon="plus" :href="route('teacher.quizzes.create')">New quiz</x-button>
            @endif
            @if ($can['viewAnalytics'])
                <x-button variant="ghost" icon="chart-line" :href="route('teacher.analytics.index')" class="text-white hover:bg-white/10 hover:text-white">View analytics</x-button>
            @endif
        </x-slot:actions>
    </x-hero-banner>

    @unless ($hasSubjects)
        <x-alert variant="warning" title="No subject assigned yet">
            {{ \App\Policies\ClassroomPolicy::NO_SUBJECT_MESSAGE }}
        </x-alert>
    @endunless

    <section aria-labelledby="teaching-at-a-glance">
        <h2 id="teaching-at-a-glance" class="sr-only">Your teaching at a glance</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                label="My learners"
                :value="number_format($learnerCount)"
                icon="users"
                color="sky"
                :hint="'Across '.trans_choice(':count classroom|:count classrooms', $classrooms->count())"
                :href="route('teacher.students.index')"
                :count-up="$learnerCount"
                data-animate
                style="--stagger: 1"
            />
            <x-stat-card
                label="Average score · 30 days"
                :value="$percent($monthOverview['averageScore'])"
                icon="target"
                color="leaf"
                :trend="$monthOverview['averageScoreDelta']"
                trend-format="points"
                trend-label="vs previous 30 days"
                :sparkline="array_values(array_filter(array_column($monthTrend, 'averageScore'), fn ($value) => $value !== null))"
                :count-up="$monthOverview['averageScore']"
                :count-decimals="1"
                count-suffix="%"
                data-animate
                style="--stagger: 2"
            />
            <x-stat-card
                label="Quiz completion"
                :value="$percent($completionRate)"
                icon="clipboard-check"
                color="grape"
                :hint="$completionTotals['assigned'] === 0 ? 'No quizzes open to learners yet' : number_format($completionTotals['submitted']).' of '.number_format($completionTotals['assigned']).' assigned quizzes submitted'"
                :href="route('teacher.quizzes.index')"
                :count-up="$completionRate"
                :count-decimals="1"
                count-suffix="%"
                data-animate
                style="--stagger: 3"
            />
            <x-stat-card
                label="Missions this week"
                :value="number_format($weekOverview['missionsCompleted'])"
                icon="flag"
                color="carrot"
                :trend="$weekOverview['missionsCompletedDelta']"
                trend-label="vs same days last week"
                hint="Completed by your learners"
                :href="route('teacher.missions.index')"
                :count-up="$weekOverview['missionsCompleted']"
                data-animate
                style="--stagger: 4"
            />
        </div>
    </section>

    <x-section title="My classrooms" description="Ring = share of assigned quizzes your learners have submitted" icon="school" color="leaf">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('teacher.classrooms.index')">All classrooms</x-button>
        </x-slot:actions>

        @if ($classrooms->isEmpty())
            <x-empty-state
                illustration="classroom"
                title="No classrooms yet"
                :description="$hasSubjects ? 'Create your first classroom, then add learners or import a class list.' : 'Once the administrator assigns you a subject, you can create classrooms for it.'"
            >
                <x-slot:actions>
                    @if ($can['createClassroom'])
                        <x-button icon="plus" :href="route('teacher.classrooms.create')">New classroom</x-button>
                    @endif
                    <x-button variant="secondary" icon="book-open" :href="route('teacher.subjects.index')">My subjects</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
                @foreach ($classrooms as $classroom)
                    <x-insights.classroom-card
                        :classroom="$classroom"
                        :completion="$completion->get($classroom->id)"
                        :average-score="$classroomScores->get($classroom->id)"
                        data-animate
                        :style="'--stagger: '.min($loop->iteration, 8)"
                    />
                @endforeach
            </div>
        @endif
    </x-section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-section title="Due soon" description="Published quizzes due in the next 7 days" icon="calendar-check" color="sun">
            <x-slot:actions>
                <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('teacher.quizzes.index')">All quizzes</x-button>
            </x-slot:actions>
            @if ($dueSoon === [])
                <x-empty-state compact illustration="hourglass" title="Nothing due this week" description="Quizzes with a due date in the next 7 days appear here." />
            @else
                <ul class="flex flex-col gap-3">
                    @foreach ($dueSoon as $item)
                        <li class="rounded-2xl p-4 ring-1 ring-ink-900/8 ring-inset dark:ring-white/10" data-insights-due="{{ $item['quiz']->id }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('teacher.quizzes.show', $item['quiz']) }}" class="block truncate text-sm font-semibold text-ink-900 hover:underline dark:text-ink-50">{{ $item['quiz']->title }}</a>
                                    <p class="truncate text-xs text-ink-500 dark:text-ink-400">{{ $item['classroom']->name }} · {{ $item['quiz']->subject->name }}</p>
                                </div>
                                <time class="shrink-0 rounded-full bg-sun-50 px-2.5 py-1 text-xs font-semibold whitespace-nowrap text-sun-800 dark:bg-sun-400/10 dark:text-sun-200" datetime="{{ $item['dueAt']->toIso8601String() }}" title="Due {{ $item['dueAt']->format('M j, Y g:i A') }}">
                                    {{ $item['dueAt']->isToday() ? 'Today '.$item['dueAt']->format('g:i A') : $item['dueAt']->format('D, M j') }}
                                </time>
                            </div>
                            <x-progress
                                class="mt-3"
                                :value="$item['submitted']"
                                :max="max(1, $item['enrolled'])"
                                size="sm"
                                color="sun"
                                label="Submitted"
                                show-value
                                :value-text="$item['submitted'].' of '.$item['enrolled']"
                            />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-section>

        <x-insights.at-risk-learners
            :at-risk="$atRisk"
            :url="$learnerUrl"
            :view-all-url="route('teacher.students.index', ['band' => \App\Enums\PerformanceBand::DidNotMeetExpectations->value])"
            :description="'Last 30 days: average below 75% or no game activity for '.\App\Services\Analytics\LearningAnalytics::INACTIVE_AFTER_DAYS.'+ days'"
        />

        <x-section title="Recent submissions" description="Latest quiz attempts in your classrooms" icon="clipboard-check" color="grape">
            @if ($recentSubmissions->isEmpty())
                <x-empty-state compact icon="clipboard-check" title="No attempts yet" description="Submissions appear here as learners finish quizzes in the game." />
            @else
                <x-timeline>
                    @foreach ($recentSubmissions as $attempt)
                        <x-timeline.item
                            :title="$attempt->student->name.' · '.$attempt->quiz->title"
                            :time="$attempt->submitted_at"
                            :icon="$attempt->passed ? 'circle-check' : 'triangle-alert'"
                            :color="$attempt->passed ? 'leaf' : 'berry'"
                            :href="$attemptUrl($attempt)"
                        >
                            <span class="inline-flex flex-wrap items-center gap-1.5">
                                <span>{{ $attempt->passed ? 'Passed with' : 'Scored' }}</span>
                                <x-score-pill :value="(float) $attempt->percentage" size="sm" />
                                @if ($attempt->is_offline)
                                    <x-badge color="sky" icon="wifi-off" size="sm">Played offline</x-badge>
                                @endif
                            </span>
                        </x-timeline.item>
                    @endforeach
                </x-timeline>
            @endif
        </x-section>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-insights.top-learners :entries="$leaderboard" :url="$learnerUrl" description="Most game XP among your learners" />
        <x-insights.quick-actions :actions="$actions" class="lg:col-span-2" />
    </div>
</x-layouts.app>
