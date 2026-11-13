@php
    $hasActivity = $overview['results'] > 0 || $overview['attempts'] > 0 || $overview['missionsCompleted'] > 0 || $overview['activitiesCompleted'] > 0;
@endphp

<x-layouts.app title="School analytics" description="School-level learning analytics by grade, subject and period.">
    <x-page-header
        eyebrow="Insights"
        title="School analytics"
        :description="'How learners across the school are doing · '.$period->label.' ('.$period->rangeText().')'"
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'School analytics']]"
    >
        <x-slot:meta>
            <x-badge color="grape" icon="calendar-range">{{ $period->label }}</x-badge>
            @if ($grade)
                <x-badge :color="$grade->color()" :icon="$grade->icon()">{{ $grade->label() }}</x-badge>
            @endif
            @if ($subject)
                <x-subject-chip :subject="$subject" size="sm" />
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" icon="file-spreadsheet" :href="route('admin.reports.index')">Reports &amp; export</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-col gap-4" data-print-hidden>
        <x-filter-bar
            :action="route('admin.analytics.index')"
            :search="false"
            :reset-url="route('admin.analytics.index')"
            :preserve="[]"
            :active="$filters['grade'] !== null || $filters['subject'] !== null || $period->key !== \App\Services\Analytics\PeriodOptions::DEFAULT"
        >
            <x-form.select name="grade" label="Grade" hide-label placeholder="All grades" :options="$gradeOptions" :value="$filters['grade']" :old="false" class="sm:w-40" />
            <x-form.select name="subject" label="Subject" hide-label placeholder="All subjects" :options="$subjectOptions" :value="$filters['subject']" :old="false" class="sm:w-52" />
            <x-insights.period-fields :options="$periodOptions" :filters="$filters" />
        </x-filter-bar>

        @if ($errors->any())
            <x-alert variant="warning" title="Some filters were not valid, so the default view is shown" dismissible>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif
    </div>

    <x-insights.kpi-grid :overview="$overview" :period="$period" />

    @unless ($hasActivity)
        <x-empty-state
            variant="dashed"
            illustration="chart"
            title="No learning activity in this view"
            description="No quiz attempts, missions or activities were recorded for these filters. Try a longer period or clear the filters."
        >
            <x-slot:actions>
                <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.analytics.index')">Clear filters</x-button>
                <x-button variant="ghost" icon="calendar-range" :href="route('admin.analytics.index', ['period' => '90d'])">Last 90 days</x-button>
            </x-slot:actions>
        </x-empty-state>
    @endunless

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-insights.score-trend :weeks="$trend" class="lg:col-span-2" />
        <x-insights.score-distribution :buckets="$distribution" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-insights.engagement-trend :weeks="$trend" class="lg:col-span-2" />
        <x-insights.band-distribution :bands="$bands" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-insights.subject-performance :subjects="$subjectPerformance" :by-grade="$grade === null" :title="$grade === null ? 'Performance by grade and subject' : 'Performance by subject'" class="lg:col-span-2" />
        <x-insights.grade-performance :grades="$gradePerformance" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-insights.classroom-ranking :classrooms="$classrooms" description="Top 10 by average of each learner’s best score per quiz" />
        <x-insights.module-mastery :modules="$modules" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-insights.missed-questions :questions="$missedQuestions" />
        <x-insights.at-risk-learners :at-risk="$atRisk" :url="$learnerUrl" :view-all-url="route('admin.students.index')" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-insights.weekday-engagement :weekdays="$weekdays" class="lg:col-span-2" />
        <x-insights.completion :completion="$completion" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-insights.sync-health :health="$syncHealth" />
        <x-insights.top-learners :entries="$leaderboard" :url="$learnerUrl" class="lg:col-span-2" />
    </div>

    <x-section title="Teacher activity" :description="'Quizzes and feedback in this period'.($subject ? ' · '.$subject->name.' teachers' : '')" icon="graduation-cap" color="leaf" padding="none">
        <x-slot:actions>
            <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('admin.teachers.index')">All teachers</x-button>
        </x-slot:actions>

        @if ($teachers->isEmpty())
            <x-empty-state
                icon="graduation-cap"
                :title="$subject ? 'No teacher is assigned '.$subject->name : 'No active teachers yet'"
                description="Teacher accounts with an assigned subject appear here."
            >
                <x-slot:actions>
                    <x-button icon="user-check" :href="route('admin.subject-assignments.index')">Assign subjects</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-table flush caption="Teacher activity in the selected period">
                <x-slot:head>
                    <x-table.th>Teacher</x-table.th>
                    <x-table.th align="right">Classrooms</x-table.th>
                    <x-table.th align="right">Quizzes created</x-table.th>
                    <x-table.th align="right">Quizzes published</x-table.th>
                    <x-table.th align="right">Feedback sent</x-table.th>
                    <x-table.th>Last sign-in</x-table.th>
                </x-slot:head>

                @foreach ($teachers as $teacher)
                    <x-table.row>
                        <x-table.td primary>
                            <div class="flex items-center gap-3">
                                <x-avatar :name="$teacher->name" size="sm" />
                                <div class="min-w-0">
                                    <a href="{{ route('admin.teachers.show', $teacher) }}" class="block truncate hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $teacher->name }}</a>
                                    <p class="truncate text-xs font-normal text-ink-500 dark:text-ink-400">{{ $teacher->email }}</p>
                                </div>
                            </div>
                        </x-table.td>
                        <x-table.td align="right" numeric>{{ number_format($teacher->active_classrooms_count) }}</x-table.td>
                        <x-table.td align="right" numeric>{{ number_format($teacher->quizzes_created_count) }}</x-table.td>
                        <x-table.td align="right" numeric>{{ number_format($teacher->quizzes_published_count) }}</x-table.td>
                        <x-table.td align="right" numeric>{{ number_format($teacher->feedback_sent_count) }}</x-table.td>
                        <x-table.td class="whitespace-nowrap">
                            @if ($teacher->last_login_at)
                                <time datetime="{{ $teacher->last_login_at->toIso8601String() }}" title="{{ $teacher->last_login_at->format('M j, Y g:i A') }}">{{ $teacher->last_login_at->diffForHumans() }}</time>
                            @else
                                <span class="text-ink-400 dark:text-ink-500">Never</span>
                            @endif
                        </x-table.td>
                    </x-table.row>
                @endforeach
            </x-table>
            @if ($teachers->hasPages())
                <div class="border-t border-ink-900/5 px-5 py-3 dark:border-white/8">{{ $teachers->links() }}</div>
            @endif
        @endif
    </x-section>
</x-layouts.app>
