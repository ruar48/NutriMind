@php
    $isFiltered = collect(request()->query())->except('page')->filter(fn ($value) => filled($value))->isNotEmpty();
    $isAccounts = $reportType === \App\Enums\ReportType::UserAccounts;
@endphp

<x-layouts.app :title="$report->title">
    <x-page-header
        eyebrow="Reports"
        :title="$report->title"
        :description="$report->description"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['label' => 'Reports & export', 'url' => route('admin.reports.index')],
            ['label' => $report->title],
        ]"
    >
        <x-slot:leading>
            <x-reports.type-icon :type="$reportType" size="lg" />
        </x-slot:leading>
        <x-slot:actions>
            <x-reports.export-actions :report-type="$reportType" portal="admin" />
        </x-slot:actions>
    </x-page-header>

    <x-reports.switcher :report-types="$reportTypes" :current="$reportType" portal="admin" />

    @if ($errors->any())
        <x-alert variant="warning" title="Some filters were not valid" data-report-filter-errors>
            They were cleared, so the report shows {{ $isAccounts ? 'every account' : 'every active classroom' }}. Check the highlighted filters and try again.
        </x-alert>
    @endif

    <x-reports.filters :report-type="$reportType" :options="$options" portal="admin" />

    <x-reports.summary :report="$report" />

    <x-reports.preview :report="$report" :rows="$rows">
        <x-slot:empty>
            @if ($isFiltered)
                <x-empty-state illustration="search" title="Nothing matches these filters" description="Try another classroom, subject or period, or reset the filters.">
                    <x-slot:actions>
                        <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.reports.show', $reportType)">Reset filters</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @elseif ($isAccounts)
                <x-empty-state icon="users" title="No accounts yet" description="Create administrator, teacher and parent accounts to start.">
                    <x-slot:actions>
                        <x-button icon="user-plus" :href="route('admin.users.create')">New user</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @elseif ($reportType === \App\Enums\ReportType::StudentPerformance)
                <x-empty-state illustration="classroom" title="No learners in active classrooms" description="Learners appear here once they are enrolled in a classroom.">
                    <x-slot:actions>
                        <x-button icon="user-plus" :href="route('admin.students.create')">New learner</x-button>
                        <x-button variant="secondary" icon="file-up" :href="route('admin.students.import.create')">Import learners</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @elseif ($reportType === \App\Enums\ReportType::MissionProgress)
                <x-empty-state illustration="chart" title="No missions to report on" description="Missions of the subjects and grades of active classrooms appear here.">
                    <x-slot:actions>
                        <x-button icon="flag" :href="route('admin.missions.index')">Manage missions</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <x-empty-state illustration="chart" title="No data to report on yet" description="Results appear here once teachers run classrooms and publish quizzes.">
                    <x-slot:actions>
                        <x-button variant="secondary" icon="user-check" :href="route('admin.subject-assignments.index')">Subject assignment</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @endif
        </x-slot:empty>
    </x-reports.preview>
</x-layouts.app>
