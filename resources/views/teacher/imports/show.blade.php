<x-layouts.app :title="'Review import · '.$classroom->name">
    <x-page-header
        eyebrow="Learners"
        title="Review the import"
        :description="$import->originalName.' · uploaded '.$import->uploadedAt->diffForHumans()"
        :breadcrumbs="[
            ['label' => 'Classrooms', 'url' => route('teacher.classrooms.index')],
            ['label' => $classroom->name, 'url' => route('teacher.classrooms.show', $classroom)],
            ['label' => 'Import CSV', 'url' => route('teacher.classrooms.import.create', $classroom)],
            ['label' => 'Review'],
        ]"
    >
        <x-slot:meta>
            <x-subject-chip :subject="$classroom->subject" />
            <x-badge color="sky" icon="school">{{ $classroom->name }}</x-badge>
            <x-badge :color="$classroom->grade_level->color()" :icon="$classroom->grade_level->icon()">{{ $classroom->grade_level->label() }}</x-badge>
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" icon="rotate-ccw" :href="route('teacher.classrooms.import.create', $classroom)">Upload a different file</x-button>
            <x-import.commit-button id="import-commit-header" :preview="$preview" :classroom="$classroom" :action="route('teacher.classrooms.import.commit', [$classroom, $import->id])" />
        </x-slot:actions>
    </x-page-header>

    <x-import.summary :preview="$preview" :classroom="$classroom" />

    <x-import.preview-table
        :preview="$preview"
        :rows="$rows"
        :status="$status"
        :base-url="route('teacher.classrooms.import.show', [$classroom, $import->id])"
        data-animate
        style="--stagger: 2"
    />

    <x-import.commit-bar :preview="$preview">
        <x-button variant="ghost" icon="rotate-ccw" :href="route('teacher.classrooms.import.create', $classroom)">Upload a different file</x-button>
        <x-import.commit-button id="import-commit-footer" :preview="$preview" :classroom="$classroom" :action="route('teacher.classrooms.import.commit', [$classroom, $import->id])" />
    </x-import.commit-bar>
</x-layouts.app>
