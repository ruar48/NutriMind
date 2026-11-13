@php
    $hasActivity = $overview['results'] > 0 || $overview['attempts'] > 0 || $overview['missionsCompleted'] > 0 || $overview['activitiesCompleted'] > 0;
    $scopeClassrooms = count($scope->classroomIds ?? []);
    $subjectsWithResults = collect($subjectPerformance)->filter(fn (array $row): bool => $row['results'] > 0)->count();
@endphp

<x-layouts.app title="Learning analytics" description="Learning data of your classrooms: trends, mastery and learners who need help.">
    <x-page-header
        eyebrow="Insights"
        title="Learning analytics"
        :description="'How your learners are doing · '.$period->label.' ('.$period->rangeText().')'"
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('teacher.dashboard')], ['label' => 'Learning analytics']]"
    >
        <x-slot:meta>
            <x-badge color="leaf" icon="calendar-range">{{ $period->label }}</x-badge>
            @if ($classroom)
                <x-badge color="slate" icon="school">{{ $classroom->name }}{{ $classroom->isArchived() ? ' (archived)' : '' }}</x-badge>
            @else
                <x-badge color="slate" icon="school">{{ trans_choice(':count classroom|:count classrooms', $scopeClassrooms) }}</x-badge>
            @endif
            @if ($subject)
                <x-subject-chip :subject="$subject" size="sm" />
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" icon="file-spreadsheet" :href="route('teacher.reports.index')">Reports</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (! $hasClassrooms)
        <x-empty-state
            variant="card"
            illustration="chart"
            title="No classrooms to analyze yet"
            description="Analytics appear once you have a classroom with learners who play NutriMind."
        >
            <x-slot:actions>
                <x-button icon="school" :href="route('teacher.classrooms.index')">Go to classrooms</x-button>
                <x-button variant="secondary" icon="book-open" :href="route('teacher.subjects.index')">My subjects</x-button>
            </x-slot:actions>
        </x-empty-state>
    @else
        <div class="flex flex-col gap-4" data-print-hidden>
            <x-filter-bar
                :action="route('teacher.analytics.index')"
                :search="false"
                :reset-url="route('teacher.analytics.index')"
                :preserve="[]"
                :active="$filters['classroom'] !== null || $filters['subject'] !== null || $period->key !== \App\Services\Analytics\PeriodOptions::DEFAULT"
            >
                <x-form.select name="classroom" label="Classroom" hide-label placeholder="All active classrooms" :options="$classroomOptions" :value="$filters['classroom']" :old="false" class="sm:w-60" />
                <x-form.select name="subject" label="Subject" hide-label placeholder="All my subjects" :options="$subjectOptions" :value="$filters['subject']" :old="false" class="sm:w-52" />
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
                description="None of your learners submitted a quiz or finished a mission or activity for these filters. Try a longer period or another classroom."
            >
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('teacher.analytics.index')">Clear filters</x-button>
                    <x-button variant="ghost" icon="calendar-range" :href="route('teacher.analytics.index', array_filter(['classroom' => $filters['classroom'], 'subject' => $filters['subject'], 'period' => '90d']))">Last 90 days</x-button>
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

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-insights.module-mastery :modules="$modules" />
            <x-insights.missed-questions :questions="$missedQuestions" :url="$itemAnalysisUrl" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-insights.at-risk-learners
                :at-risk="$atRisk"
                :url="$learnerUrl"
                :view-all-url="route('teacher.students.index', array_filter(['classroom' => $filters['classroom'], 'band' => \App\Enums\PerformanceBand::DidNotMeetExpectations->value]))"
                class="lg:col-span-2"
            />
            <x-insights.top-learners :entries="$leaderboard" :url="$learnerUrl" description="Most game XP among these learners" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-insights.weekday-engagement :weekdays="$weekdays" class="lg:col-span-2" />
            <div class="flex min-w-0 flex-col gap-6">
                <x-insights.completion :completion="$completion" />
                <x-insights.sync-health :health="$syncHealth" />
            </div>
        </div>

        @if ($subjectsWithResults > 1 || count($classrooms) > 1)
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                @if ($subjectsWithResults > 1)
                    <x-insights.subject-performance :subjects="$subjectPerformance" title="Your subjects" />
                @endif
                @if (count($classrooms) > 1)
                    <x-insights.classroom-ranking :classrooms="$classrooms" :url="$classroomUrl" :show-teacher="false" title="Your classrooms" description="Ranked by average of each learner’s best score per quiz" @class(['lg:col-span-2' => $subjectsWithResults <= 1]) />
                @endif
            </div>
        @endif
    @endif
</x-layouts.app>
