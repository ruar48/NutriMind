@php
    $isFiltered = collect(request()->query())->except('page')->filter(fn ($value) => filled($value))->isNotEmpty();
@endphp

<x-layouts.app :title="$report->title">
    <x-page-header
        eyebrow="Reports"
        :title="$report->title"
        :description="$report->description"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('teacher.dashboard')],
            ['label' => 'Reports', 'url' => route('teacher.reports.index')],
            ['label' => $report->title],
        ]"
    >
        <x-slot:leading>
            <x-reports.type-icon :type="$reportType" size="lg" />
        </x-slot:leading>
        @unless ($canExport)
            <x-slot:meta>
                <x-badge color="slate" icon="lock" data-report-export-denied>Exporting is turned off for your account</x-badge>
            </x-slot:meta>
        @endunless
        <x-slot:actions>
            <x-reports.export-actions :report-type="$reportType" portal="teacher" :can-export="$canExport" />
        </x-slot:actions>
    </x-page-header>

    <x-reports.switcher :report-types="$reportTypes" :current="$reportType" portal="teacher" />

    @if ($errors->any())
        <x-alert variant="warning" title="Some filters were not valid" data-report-filter-errors>
            They were cleared, so the report shows all your active classrooms. Choose from the lists to report on your own classrooms and subjects.
        </x-alert>
    @endif

    <x-reports.filters :report-type="$reportType" :options="$options" portal="teacher" />

    <x-reports.summary :report="$report" />

    <x-reports.preview :report="$report" :rows="$rows">
        <x-slot:empty>
            @if ($isFiltered)
                <x-empty-state illustration="search" title="Nothing matches these filters" description="Try another classroom, subject or period, or reset the filters.">
                    <x-slot:actions>
                        <x-button variant="secondary" icon="rotate-ccw" :href="route('teacher.reports.show', $reportType)">Reset filters</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @elseif (! $hasSubjects)
                <x-empty-state illustration="lock" title="No subject assigned yet" :description="\App\Policies\ReportTypePolicy::NO_SUBJECT_MESSAGE">
                    <x-slot:actions>
                        <x-button variant="secondary" icon="book-open" :href="route('teacher.subjects.index')">My subjects</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @elseif ($reportType === \App\Enums\ReportType::QuizResults)
                <x-empty-state illustration="chart" title="No published quizzes yet" description="Results appear here once you publish a quiz to your classrooms.">
                    <x-slot:actions>
                        <x-button icon="plus" :href="route('teacher.quizzes.create')">New quiz</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @elseif ($reportType === \App\Enums\ReportType::MissionProgress)
                <x-empty-state illustration="chart" title="No missions for your classrooms yet" description="Missions of your classrooms’ subjects and grades appear here once the school adds them.">
                    <x-slot:actions>
                        <x-button variant="secondary" icon="flag" :href="route('teacher.missions.index')">Mission progress</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <x-empty-state
                    illustration="classroom"
                    :title="$reportType === \App\Enums\ReportType::ClassroomSummary ? 'No active classrooms yet' : 'No learners in your active classrooms'"
                    description="Create a classroom and add learners to see their results here."
                >
                    <x-slot:actions>
                        <x-button icon="plus" :href="route('teacher.classrooms.create')">New classroom</x-button>
                        <x-button variant="secondary" icon="school" :href="route('teacher.classrooms.index')">My classrooms</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @endif
        </x-slot:empty>
    </x-reports.preview>
</x-layouts.app>
