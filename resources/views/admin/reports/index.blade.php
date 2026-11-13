<x-layouts.app title="Reports & export">
    <x-page-header
        eyebrow="Insights"
        title="Reports & export"
        description="Generate school-wide reports, preview them with filters, then export them as CSV or print them with signature lines."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Reports & export']]"
    >
        <x-slot:actions>
            <x-button :href="route('admin.reports.show', \App\Enums\ReportType::StudentPerformance)" icon="trending-up">Learner performance</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-reports.catalog :report-types="$reportTypes" portal="admin" />

    <x-reports.definitions portal="admin" />
</x-layouts.app>
