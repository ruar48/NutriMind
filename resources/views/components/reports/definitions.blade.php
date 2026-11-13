@props([
    'portal',
])

<x-section
    title="How the numbers work"
    description="The same rules apply to the preview, the CSV export and the print view."
    icon="lightbulb"
    color="sun"
    {{ $attributes }}
    data-report-definitions
>
    <x-definition-list columns="2">
        <x-detail-row label="Scores" icon="target">Each learner’s best attempt per quiz. Averages are rounded to one decimal.</x-detail-row>
        <x-detail-row label="Performance bands" icon="gauge">DepEd descriptors: Outstanding 90–100, Very Satisfactory 85–89, Satisfactory 80–84, Fairly Satisfactory 75–79, Did Not Meet Expectations below 75.</x-detail-row>
        <x-detail-row label="Classrooms" icon="school">{{ $portal === 'teacher' ? 'Your active classrooms in the subjects assigned to you.' : 'Every active classroom.' }} Archived classrooms appear when you choose one in the classroom filter.</x-detail-row>
        <x-detail-row label="Period" icon="calendar-range">From and To limit the quiz attempts and mission progress counted to those days.</x-detail-row>
        <x-detail-row label="Points and levels" icon="sparkles">Game totals of each learner, synced from the NutriMind game.</x-detail-row>
        <x-detail-row label="Exports" icon="file-spreadsheet">CSV files open in Excel or Google Sheets. The print view is ready to print or save as PDF, with signature lines.</x-detail-row>
    </x-definition-list>
</x-section>
