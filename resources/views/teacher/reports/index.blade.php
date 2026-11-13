<x-layouts.app title="Reports">
    <x-page-header
        eyebrow="Insights"
        title="Reports"
        description="Learner performance and class results for your classrooms. Preview them with filters, then export or print them."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('teacher.dashboard')], ['label' => 'Reports']]"
    >
        <x-slot:actions>
            <x-button :href="route('teacher.reports.show', \App\Enums\ReportType::StudentPerformance)" icon="trending-up">Learner performance</x-button>
        </x-slot:actions>
    </x-page-header>

    @unless ($hasSubjects)
        <x-alert variant="info" title="No subject assigned yet" data-report-no-subjects>
            {{ \App\Policies\ReportTypePolicy::NO_SUBJECT_MESSAGE }} Reports cover the classrooms of your assigned subjects.
        </x-alert>
    @endunless

    @unless ($canExport)
        <x-alert variant="info" title="Exporting is turned off for your account" data-report-export-denied>
            You can preview every report. Ask the school administrator for the “Export reports” permission to download CSV files and print reports.
        </x-alert>
    @endunless

    <x-reports.catalog :report-types="$reportTypes" portal="teacher" :can-export="$canExport" />

    <x-reports.definitions portal="teacher" />
</x-layouts.app>
