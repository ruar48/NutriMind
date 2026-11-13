<x-layouts.app title="Review import">
    <x-page-header
        eyebrow="Learners"
        title="Review the import"
        :description="$import->originalName.' · uploaded '.$import->uploadedAt->diffForHumans()"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['label' => 'Learners', 'url' => route('admin.students.index')],
            ['label' => 'Import CSV', 'url' => route('admin.students.import.create')],
            ['label' => 'Review'],
        ]"
    >
        <x-slot:meta>
            @if ($classroom)
                <x-subject-chip :subject="$classroom->subject" />
                <x-badge color="sky" icon="school">{{ $classroom->name }}</x-badge>
                <span class="inline-flex items-center gap-1.5 text-xs text-ink-600 dark:text-ink-300">
                    <x-icon name="graduation-cap" class="size-3.5" />
                    {{ $classroom->teacher->name }} · SY {{ $classroom->schoolYear->name }}
                </span>
            @else
                <x-badge color="slate" icon="info">No classroom · accounts only</x-badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.students.import.create')">Upload a different file</x-button>
            <x-import.commit-button id="import-commit-header" :preview="$preview" :classroom="$classroom" :action="route('admin.students.import.commit', $import->id)" />
        </x-slot:actions>
    </x-page-header>

    <x-import.summary :preview="$preview" :classroom="$classroom" />

    <x-import.preview-table
        :preview="$preview"
        :rows="$rows"
        :status="$status"
        :base-url="route('admin.students.import.show', $import->id)"
        data-animate
        style="--stagger: 2"
    />

    <x-import.commit-bar :preview="$preview">
        <x-button variant="ghost" icon="rotate-ccw" :href="route('admin.students.import.create')">Upload a different file</x-button>
        <x-import.commit-button id="import-commit-footer" :preview="$preview" :classroom="$classroom" :action="route('admin.students.import.commit', $import->id)" />
    </x-import.commit-bar>
</x-layouts.app>
