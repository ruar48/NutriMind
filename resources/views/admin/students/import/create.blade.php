<x-layouts.app title="Import learners">
    <x-page-header
        eyebrow="Learners"
        title="Import learners from CSV"
        description="Create learner accounts in bulk, enroll them in a classroom and link their parents."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['label' => 'Learners', 'url' => route('admin.students.index')],
            ['label' => 'Import CSV'],
        ]"
    >
        <x-slot:leading>
            <span class="grid size-14 place-items-center rounded-2xl bg-grape-600 text-white shadow-card">
                <x-icon name="file-up" class="size-7" />
            </span>
        </x-slot:leading>
        <x-slot:actions>
            <x-button variant="secondary" icon="download" :href="route('admin.students.import.template')">Download template</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-import.result
        :result="session('importResult')"
        :credentials="session('credentials', [])"
        :download-url="route('admin.students.import.credentials')"
    >
        <x-slot:actions>
            <x-button variant="secondary" size="sm" icon="backpack" :href="route('admin.students.index')">View learners</x-button>
        </x-slot:actions>
    </x-import.result>

    <form method="POST" action="{{ route('admin.students.import.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4" data-submit-once data-animate style="--stagger: 1">
        @csrf

        <x-form.section
            title="Upload a class list"
            description="Nothing is saved yet: you review every row before anything is imported."
        >
            <x-slot:aside>
                <x-import.steps :template-url="route('admin.students.import.template')" />
            </x-slot:aside>

            <x-form.select
                name="classroom_id"
                label="Classroom"
                :options="$classroomOptions"
                :value="$selectedClassroomId"
                placeholder="No classroom — create accounts only"
                hint="Learners are enrolled in this classroom, and learners who already have an account (same LRN) are enrolled too. Without a classroom, every row needs a grade level and existing learners are skipped."
                optional
                class="col-span-full"
            />

            @if ($classroomOptions === [])
                <x-alert variant="info" icon="school" class="col-span-full" data-import-no-classrooms>
                    There are no active classrooms yet. You can still create learner accounts and enroll them later.
                </x-alert>
            @endif

            <x-import.file-field />
        </x-form.section>

        <x-form.actions :sticky="false" :cancel="route('admin.students.index')" submit="Upload and preview" submit-icon="file-up">
            <x-icon name="shield-check" class="size-4" />
            The file is kept privately and deleted after the import.
        </x-form.actions>
    </form>

    <x-import.column-guide :template-url="route('admin.students.import.template')" data-animate style="--stagger: 2" />
</x-layouts.app>
