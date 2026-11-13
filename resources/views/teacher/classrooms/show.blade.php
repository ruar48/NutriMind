@php
    $user = auth()->user();
    $isArchived = $classroom->isArchived();
    $canUpdate = $user->can('update', $classroom);
    $canDelete = $canBeDeleted && $user->can('delete', $classroom);
    $canManageStudents = $user->can('manageStudents', $classroom);
    $canImport = $user->can('importStudents', $classroom);
    $description = collect([
        $classroom->grade_level->label(),
        'Section '.$classroom->section,
        $classroom->room,
    ])->filter()->implode(' · ');
@endphp

<x-layouts.app :title="$classroom->name">
    <x-page-header
        :eyebrow="'SY '.$classroom->schoolYear->name"
        :title="$classroom->name"
        :description="$description"
        :breadcrumbs="[['label' => 'Classrooms', 'url' => route('teacher.classrooms.index', $isArchived ? ['status' => 'archived'] : [])], ['label' => $classroom->name]]"
    >
        <x-slot:leading>
            <x-classrooms.subject-tile :subject="$classroom->subject" size="lg" @class(['grayscale' => $isArchived]) />
        </x-slot:leading>
        <x-slot:meta>
            <x-subject-chip :subject="$classroom->subject" />
            <x-status-badge :status="$classroom->learning_mode" />
            @if ($isArchived)
                <x-badge color="slate" icon="archive">Archived</x-badge>
            @endif
            <span class="inline-flex h-7 items-center gap-1 rounded-full bg-white pr-1 pl-3 ring-1 ring-ink-900/10 ring-inset dark:bg-white/5 dark:ring-white/12">
                <span aria-hidden="true" class="text-xs text-ink-600 dark:text-ink-300">Code</span>
                <x-classrooms.class-code :code="$classroom->class_code" />
            </span>
        </x-slot:meta>
        <x-slot:actions>
            @if ($canManageStudents)
                <x-button icon="user-plus" :href="route('teacher.classrooms.students.create', $classroom)">Add learner</x-button>
            @endif
            @if ($canImport)
                <x-button variant="secondary" icon="file-up" :href="route('teacher.classrooms.import.create', $classroom)">Import CSV</x-button>
            @endif
            @if ($canUpdate)
                <x-button variant="secondary" icon="pencil" :href="route('teacher.classrooms.edit', $classroom)">Edit</x-button>
                <x-dropdown variant="icon" icon="ellipsis-vertical" :label="'More actions for '.$classroom->name">
                    @if ($isArchived)
                        <x-confirm-dialog
                            :action="route('teacher.classrooms.archive.destroy', $classroom)"
                            :danger="false"
                            :title="'Restore '.$classroom->name.'?'"
                            message="The classroom moves back to your active classes and you can manage its learners again."
                            confirm="Restore classroom"
                            button="Restore classroom"
                            button-variant="menu"
                            button-icon="archive-restore"
                        />
                    @else
                        <x-confirm-dialog
                            :action="route('teacher.classrooms.archive.store', $classroom)"
                            method="POST"
                            :title="'Archive '.$classroom->name.'?'"
                            message="Learners and quiz results stay available, but the class becomes read-only until you restore it."
                            confirm="Archive classroom"
                            button="Archive classroom"
                            button-variant="menu"
                            button-icon="archive"
                        />
                    @endif
                    @if ($canDelete)
                        <x-dropdown.divider />
                        <x-confirm-dialog
                            :action="route('teacher.classrooms.destroy', $classroom)"
                            :title="'Delete '.$classroom->name.'?'"
                            message="The empty classroom and its class code are removed permanently. This cannot be undone."
                            confirm="Delete classroom"
                            button="Delete classroom"
                            button-variant="menu"
                        />
                    @endif
                </x-dropdown>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-credentials-alert :credentials="session('credentials', [])" />

    @if ($isArchived)
        <x-alert variant="info" icon="archive" title="This classroom is archived">
            Its learners and quiz results are read-only.
            @if ($canUpdate)
                Restore it from the actions menu to add, remove or reset learners.
            @endif
        </x-alert>
    @endif

    <x-tabs label="Classroom sections">
        <x-tab :href="route('teacher.classrooms.show', [$classroom, 'tab' => 'learners'])" :active="$tab === 'learners'" icon="users" :count="$classroom->students_count">Learners</x-tab>
        <x-tab :href="route('teacher.classrooms.show', [$classroom, 'tab' => 'quizzes'])" :active="$tab === 'quizzes'" icon="clipboard-check" :count="$classroom->quizzes_count">Quizzes</x-tab>
        <x-tab :href="route('teacher.classrooms.show', [$classroom, 'tab' => 'overview'])" :active="$tab === 'overview'" icon="chart-line">Overview</x-tab>
    </x-tabs>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 xl:col-span-2">
            @if ($tab === 'learners')
                <x-classrooms.roster
                    :classroom="$classroom"
                    :students="$students"
                    :levels="$levels"
                    :can-manage-students="$canManageStudents"
                    :can-import="$canImport"
                />
            @elseif ($tab === 'quizzes')
                <x-classrooms.assigned-quizzes :classroom="$classroom" :quizzes="$quizzes" />
            @else
                <x-classrooms.overview :classroom="$classroom" :stats="$stats" :can-update="$canUpdate" />
            @endif
        </div>

        <aside class="flex min-w-0 flex-col gap-6" aria-label="Classroom details">
            <x-card data-animate style="--stagger: 1">
                <x-classrooms.class-code :code="$classroom->class_code" size="lg" />
                <p class="mt-3 flex items-start gap-2 text-sm text-ink-600 dark:text-ink-300">
                    <x-icon name="gamepad-2" class="mt-0.5 size-4 shrink-0 text-leaf-600 dark:text-leaf-400" />
                    <span>Learners enter this code in the NutriMind game to join this class.</span>
                </p>
            </x-card>

            <x-section title="Details" icon="info" color="slate" data-animate style="--stagger: 2">
                <x-definition-list>
                    <x-detail-row label="School year" :value="$classroom->schoolYear->name" />
                    <x-detail-row label="Subject">{{ $classroom->subject->name }} <span class="font-normal text-ink-500 dark:text-ink-400">({{ $classroom->subject->code }})</span></x-detail-row>
                    <x-detail-row label="Grade level" :value="$classroom->grade_level->label()" />
                    <x-detail-row label="Section" :value="$classroom->section" />
                    <x-detail-row label="Room" :value="$classroom->room" />
                    <x-detail-row label="Learning mode" :value="$classroom->learning_mode->label()" />
                    <x-detail-row label="Created" :value="$classroom->created_at?->format('M j, Y')" />
                    @if ($isArchived)
                        <x-detail-row label="Archived" :value="$classroom->archived_at->format('M j, Y')" />
                    @endif
                </x-definition-list>
                @if (filled($classroom->description))
                    <p class="mt-4 rounded-xl bg-ink-50 p-3 text-sm whitespace-pre-line text-ink-700 ring-1 ring-ink-900/5 ring-inset dark:bg-white/4 dark:text-ink-200 dark:ring-white/8">{{ $classroom->description }}</p>
                @endif
            </x-section>
        </aside>
    </div>
</x-layouts.app>
