@php
    $percent = fn (?float $value): string => $value === null ? '—' : number_format($value, 1).'%';
    $setupIncomplete = collect($checklist)->contains('done', false);
    $missions = $completion['missions'];
    $syncAttention = $syncHealth['overdue'] + $syncHealth['neverSynced'];
    $openModeration = $moderation['reports'] + $moderation['pendingThreads'];
    $subjectsWithResults = collect($subjects)->filter(fn (array $row): bool => $row['results'] > 0)->values()->all();
@endphp

<x-layouts.app title="Dashboard" description="School-wide overview of learners, teachers and game progress.">
    <x-hero-banner
        eyebrow="Administrator Portal"
        color="grape"
        :title="$greeting"
        :description="$schoolName.' has '.number_format($headcounts['learners']).' '.\Illuminate\Support\Str::plural('learner', $headcounts['learners']).' and '.number_format($headcounts['teachers']).' '.\Illuminate\Support\Str::plural('teacher', $headcounts['teachers']).' in '.number_format($headcounts['classrooms']).' active '.\Illuminate\Support\Str::plural('classroom', $headcounts['classrooms']).'.'"
    >
        <div class="flex flex-wrap gap-2">
            @if ($currentTerm)
                <x-badge color="sun" icon="calendar-range" size="lg">{{ $currentTerm->name }} · SY {{ $currentTerm->schoolYear->name }}</x-badge>
            @else
                <x-badge color="slate" icon="calendar-range" size="lg">No active quarter</x-badge>
            @endif
            <x-badge color="leaf" icon="clipboard-check" size="lg">{{ trans_choice(':count quiz attempt|:count quiz attempts', $weekOverview['attempts']) }} this week</x-badge>
            @if ($openModeration > 0)
                <x-badge color="berry" icon="shield" size="lg">{{ trans_choice(':count item to moderate|:count items to moderate', $openModeration) }}</x-badge>
            @endif
        </div>
        <x-slot:actions>
            <x-button variant="secondary" icon="chart-line" :href="route('admin.analytics.index')">School analytics</x-button>
            <x-button variant="ghost" icon="file-spreadsheet" :href="route('admin.reports.index')" class="text-white hover:bg-white/10 hover:text-white">Reports &amp; export</x-button>
        </x-slot:actions>
    </x-hero-banner>

    @if ($setupIncomplete)
        <x-insights.setup-checklist :steps="$checklist" :school-name="$schoolName" data-animate />
    @endif

    <section aria-labelledby="school-at-a-glance" class="flex flex-col gap-4">
        <h2 id="school-at-a-glance" class="sr-only">School at a glance</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Learners" :value="number_format($headcounts['learners'])" icon="backpack" color="sky" hint="Active learner accounts" :href="route('admin.students.index')" :count-up="$headcounts['learners']" data-animate style="--stagger: 1" />
            <x-stat-card label="Teachers" :value="number_format($headcounts['teachers'])" icon="graduation-cap" color="leaf" hint="Active teacher accounts" :href="route('admin.teachers.index')" :count-up="$headcounts['teachers']" data-animate style="--stagger: 2" />
            <x-stat-card label="Parents & guardians" :value="number_format($headcounts['parents'])" icon="house-heart" color="carrot" hint="Active parent accounts" :href="route('admin.parents.index')" :count-up="$headcounts['parents']" data-animate style="--stagger: 3" />
            <x-stat-card label="Active classrooms" :value="number_format($headcounts['classrooms'])" icon="school" color="grape" hint="Not archived" :count-up="$headcounts['classrooms']" data-animate style="--stagger: 4" />
            <x-stat-card
                label="Average score · 30 days"
                :value="$percent($monthOverview['averageScore'])"
                icon="target"
                color="leaf"
                :trend="$monthOverview['averageScoreDelta']"
                trend-format="points"
                :trend-label="$month->period->comparisonLabel()"
                :hint="trans_choice(':count quiz result|:count quiz results', $monthOverview['results'])"
                :href="route('admin.analytics.index')"
                :count-up="$monthOverview['averageScore']"
                :count-decimals="1"
                count-suffix="%"
                data-animate
                style="--stagger: 5"
            />
            <x-stat-card
                label="Quiz attempts · 7 days"
                :value="number_format($weekOverview['attempts'])"
                icon="clipboard-check"
                color="teal"
                :trend="$weekOverview['attemptsDelta']"
                trend-label="vs previous 7 days"
                :sparkline="array_column($dailyAttempts, 'attempts')"
                :href="route('admin.analytics.index', ['period' => '7d'])"
                :count-up="$weekOverview['attempts']"
                data-animate
                style="--stagger: 6"
            />
            <x-stat-card
                label="Mission completion · 30 days"
                :value="$percent($missions['rate'])"
                icon="flag"
                color="carrot"
                :hint="$missions['started'] === 0 ? 'No missions started' : number_format($missions['completed']).' of '.number_format($missions['started']).' started missions'"
                :count-up="$missions['rate']"
                :count-decimals="1"
                count-suffix="%"
                data-animate
                style="--stagger: 7"
            />
            <x-stat-card
                label="Needs attention"
                :value="number_format($atRisk['count'])"
                icon="triangle-alert"
                color="berry"
                :hint="'Below 75% or inactive '.\App\Services\Analytics\LearningAnalytics::INACTIVE_AFTER_DAYS.'+ days'"
                :href="route('admin.analytics.index')"
                :count-up="$atRisk['count']"
                data-animate
                style="--stagger: 8"
            />
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-insights.engagement-trend :weeks="$engagement" description="Quiz attempts and learners who played, last 8 weeks" class="lg:col-span-2" />
        <x-insights.band-distribution :bands="$bands" description="Learners by average quiz result, last 30 days" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-insights.subject-performance :subjects="$subjectsWithResults" by-grade title="Performance by grade and subject" description="Average of each learner’s best score per quiz, last 30 days" class="lg:col-span-2" />
        <x-insights.sync-health :health="$syncHealth" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-insights.classroom-ranking :classrooms="$topClassrooms" title="Top classrooms" description="Highest average quiz result, last 30 days" />

        <x-section title="Recent sign-ins" description="Staff and parents who signed in lately" icon="log-in" color="sky">
            <x-slot:actions>
                <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('admin.users.index')">All users</x-button>
            </x-slot:actions>
            @if ($recentSignIns->isEmpty())
                <x-empty-state compact icon="log-in" title="No sign-ins yet" description="Accounts appear here after their first sign-in." />
            @else
                <ul class="flex flex-col divide-y divide-ink-900/5 dark:divide-white/8">
                    @foreach ($recentSignIns as $user)
                        <li class="flex items-center gap-3 py-2.5">
                            <x-avatar :name="$user->name" size="sm" />
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('admin.users.edit', $user) }}" class="block truncate text-sm font-semibold text-ink-900 hover:underline dark:text-ink-50">{{ $user->name }}</a>
                                <time class="text-xs text-ink-500 dark:text-ink-400" datetime="{{ $user->last_login_at->toIso8601String() }}" title="{{ $user->last_login_at->format('M j, Y g:i A') }}">{{ $user->last_login_at->diffForHumans() }}</time>
                            </div>
                            <x-status-badge :status="$user->role" size="sm" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-section>

        <x-section title="Moderation" description="Parent forum items waiting for an administrator" icon="shield" color="berry">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                <x-stat-card label="Open reports" :value="number_format($moderation['reports'])" icon="flag" color="berry" :href="route('admin.forum.reports.index')" data-insights-moderation="reports" />
                <x-stat-card label="Pending threads" :value="number_format($moderation['pendingThreads'])" icon="clock" color="sun" :href="route('admin.forum.pending.index')" data-insights-moderation="pending" />
            </div>
            @if ($moderation['latestReports']->isEmpty())
                <x-empty-state compact illustration="forum" title="All clear" description="No forum report is waiting for review." class="mt-2" />
            @else
                <x-timeline class="mt-5">
                    @foreach ($moderation['latestReports'] as $report)
                        <x-timeline.item
                            :title="$report->reason->label()"
                            icon="flag"
                            color="berry"
                            :time="$report->created_at"
                            :href="route('admin.forum.reports.index')"
                        >
                            {{ $report->reply ? 'A reply in' : 'Thread' }} “{{ \Illuminate\Support\Str::limit($report->thread->title, 60) }}” · reported by {{ $report->reporter->name }}
                        </x-timeline.item>
                    @endforeach
                </x-timeline>
            @endif
        </x-section>
    </div>

    <x-insights.quick-actions
        description="Jump to the tasks administrators do most"
        :actions="[
            ['label' => 'New user', 'description' => 'Administrator, teacher or parent account', 'icon' => 'user-plus', 'color' => 'grape', 'url' => route('admin.users.create')],
            ['label' => 'New learner', 'description' => 'Learner account and profile', 'icon' => 'backpack', 'color' => 'sky', 'url' => route('admin.students.create')],
            ['label' => 'Import learners', 'description' => 'Upload a CSV class list', 'icon' => 'file-up', 'color' => 'teal', 'url' => route('admin.students.import.create')],
            ['label' => 'Assign subjects', 'description' => 'Who teaches what', 'icon' => 'user-check', 'color' => 'leaf', 'url' => route('admin.subject-assignments.index')],
            ['label' => 'New learning module', 'description' => 'Game world content', 'icon' => 'map', 'color' => 'carrot', 'url' => route('admin.modules.create')],
            ['label' => 'Reports & export', 'description' => 'Printable reports and CSV files', 'icon' => 'file-spreadsheet', 'color' => 'indigo', 'url' => route('admin.reports.index')],
        ]"
    />
</x-layouts.app>
