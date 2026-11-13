<x-layouts.app :title="'Import learners · '.$classroom->name">
    <x-page-header
        eyebrow="Learners"
        title="Import learners from CSV"
        :description="'Add a class list to '.$classroom->name.' · '.$classroom->grade_level->label().' · Section '.$classroom->section"
        :breadcrumbs="[
            ['label' => 'Classrooms', 'url' => route('teacher.classrooms.index')],
            ['label' => $classroom->name, 'url' => route('teacher.classrooms.show', $classroom)],
            ['label' => 'Import CSV'],
        ]"
    >
        <x-slot:leading>
            <span class="grid size-14 place-items-center rounded-2xl bg-leaf-600 text-white shadow-card">
                <x-icon name="file-up" class="size-7" />
            </span>
        </x-slot:leading>
        <x-slot:meta>
            <x-subject-chip :subject="$classroom->subject" />
            <x-badge :color="$classroom->grade_level->color()" :icon="$classroom->grade_level->icon()">{{ $classroom->grade_level->label() }}</x-badge>
            <span class="inline-flex items-center gap-1.5 text-xs text-ink-600 dark:text-ink-300">
                <x-icon name="users" class="size-3.5" />
                {{ trans_choice(':count learner enrolled|:count learners enrolled', $classroom->students_count) }}
            </span>
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" icon="download" :href="route('teacher.imports.template')">Download template</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-import.result
        :result="session('importResult')"
        :credentials="session('credentials', [])"
        :download-url="route('teacher.imports.credentials')"
    >
        <x-slot:actions>
            <x-button variant="secondary" size="sm" icon="school" :href="route('teacher.classrooms.show', $classroom)">View classroom</x-button>
        </x-slot:actions>
    </x-import.result>

    <form method="POST" action="{{ route('teacher.classrooms.import.store', $classroom) }}" enctype="multipart/form-data" class="flex flex-col gap-4" data-submit-once data-animate style="--stagger: 1">
        @csrf

        <x-form.section
            title="Upload a class list"
            :description="'Every learner joins this class as '.$classroom->grade_level->label().'. Nothing is saved until you review the rows and confirm.'"
        >
            <x-slot:aside>
                <x-import.steps :template-url="route('teacher.imports.template')" />
            </x-slot:aside>

            <x-alert variant="info" icon="user-check" class="col-span-full">
                Learners who already have a NutriMind account (same LRN) are enrolled in this class instead of getting a second account.
            </x-alert>

            <x-import.file-field />
        </x-form.section>

        <x-form.actions :sticky="false" :cancel="route('teacher.classrooms.show', $classroom)" submit="Upload and preview" submit-icon="file-up">
            <x-icon name="shield-check" class="size-4" />
            The file is kept privately and deleted after the import.
        </x-form.actions>
    </form>

    <x-import.column-guide :template-url="route('teacher.imports.template')" :classroom-grade="$classroom->grade_level" data-animate style="--stagger: 2" />
</x-layouts.app>
