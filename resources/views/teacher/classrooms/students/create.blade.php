@php
    $grade = $classroom->grade_level->label();
    $canImport = auth()->user()->can('importStudents', $classroom);
    $enrollError = $errors->first('student_ids') ?: $errors->first('student_ids.*');
@endphp

<x-layouts.app :title="'Add learners · '.$classroom->name">
    <x-page-header
        eyebrow="Learners"
        title="Add learners"
        :description="$classroom->name.' · '.$grade.' · Section '.$classroom->section"
        :breadcrumbs="[
            ['label' => 'Classrooms', 'url' => route('teacher.classrooms.index')],
            ['label' => $classroom->name, 'url' => route('teacher.classrooms.show', $classroom)],
            ['label' => 'Add learners'],
        ]"
    >
        <x-slot:leading>
            <x-classrooms.subject-tile :subject="$classroom->subject" size="lg" />
        </x-slot:leading>
        @if ($canImport)
            <x-slot:actions>
                <x-button variant="secondary" icon="file-up" :href="route('teacher.classrooms.import.create', $classroom)">Import a CSV class list</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <form method="POST" action="{{ route('teacher.classrooms.students.store', $classroom) }}" class="flex flex-col gap-4" data-submit-once data-animate style="--stagger: 1">
        @csrf

        <x-form.section
            title="Create a new learner account"
            :description="'For a learner who does not have a NutriMind account yet. They join this class as '.$grade.'.'"
            columns="2"
        >
            <x-slot:aside>
                <x-alert variant="info" icon="key-round">
                    Leave the username and password empty to generate them. You see the sign-in details once, right after saving.
                </x-alert>
            </x-slot:aside>

            <x-form.input name="name" label="Full name" maxlength="120" placeholder="e.g. Juan Dela Cruz" autocomplete="off" required class="col-span-full" />
            <x-form.input name="lrn" label="LRN" maxlength="12" inputmode="numeric" placeholder="12-digit Learner Reference Number" autocomplete="off" optional />
            <x-form.input name="grade_display" label="Grade level" :value="$grade" prefix-icon="lock" hint="Fixed to the grade of this class." :old="false" disabled />
            <x-form.select name="sex" label="Sex" :options="\App\Enums\Sex::options()" placeholder="Not specified" optional />
            <x-form.input name="birthdate" type="date" label="Birthdate" :max="now()->subDay()->toDateString()" optional />
            <x-form.input name="username" label="Username" maxlength="30" prefix-icon="user" hint="Letters, numbers, dashes and underscores." autocomplete="off" optional />
            <x-form.password name="password" label="Password" hint="At least 8 characters with letters and numbers." autocomplete="new-password" optional />
        </x-form.section>

        <x-form.actions :sticky="false" :cancel="route('teacher.classrooms.show', $classroom)" submit="Create learner" submit-icon="user-plus" />
    </form>

    <x-divider label="or" />

    <x-section
        title="Enroll existing learners"
        :description="'Find active '.$grade.' learners who already have a NutriMind account and are not in this class yet.'"
        icon="users"
        color="sky"
        data-animate
        style="--stagger: 2"
    >
        <div class="flex flex-col gap-5">
            <x-filter-bar
                :action="route('teacher.classrooms.students.create', $classroom)"
                search-label="Search learners to enroll"
                search-placeholder="Search by name, LRN or username"
                :reset-url="route('teacher.classrooms.students.create', $classroom)"
            />

            @if ($enrollError)
                <x-alert variant="danger" data-enroll-error>{{ $enrollError }}</x-alert>
            @endif

            @if ($matches === null)
                <x-empty-state compact icon="search" title="Search for a learner" :description="'Type a name, LRN or username to list up to '.\App\Http\Controllers\Teacher\ClassroomStudentController::SEARCH_LIMIT.' matching '.$grade.' learners.'" />
            @elseif ($matches->isEmpty())
                <x-empty-state compact illustration="search" title="No learners found" :description="'Only active '.$grade.' learners who are not in this class yet are listed. Check the spelling, or create a new account above.'" />
            @else
                <form method="POST" action="{{ route('teacher.classrooms.enrollments.store', $classroom) }}" class="flex flex-col gap-4" data-submit-once>
                    @csrf

                    <fieldset class="flex flex-col gap-3">
                        <legend class="sr-only">Learners to enroll</legend>

                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-ink-50 px-4 py-3 ring-1 ring-ink-900/5 ring-inset dark:bg-white/4 dark:ring-white/8" data-js-only>
                            <x-form.checkbox name="select_all" label="Select all" :old="false" data-check-all="enroll" />
                            <p class="text-sm text-ink-600 dark:text-ink-300"><span data-check-count="enroll">0</span> selected</p>
                        </div>

                        <ul class="divide-y divide-ink-900/5 rounded-xl ring-1 ring-ink-900/8 ring-inset dark:divide-white/8 dark:ring-white/10">
                            @foreach ($matches as $match)
                                <li class="flex items-start gap-3 px-4 py-3" data-enroll-match="{{ $match->id }}">
                                    <x-form.checkbox
                                        name="student_ids[]"
                                        :value="$match->id"
                                        :label="$match->name"
                                        :description="collect([
                                            $match->username,
                                            $match->studentProfile?->lrn ? 'LRN '.$match->studentProfile->lrn : null,
                                        ])->filter()->implode(' · ')"
                                        data-check-group="enroll"
                                        class="min-w-0 flex-1"
                                    />
                                    <div class="hidden max-w-[50%] flex-wrap justify-end gap-1.5 sm:flex">
                                        @forelse ($match->enrolledClassrooms->take(2) as $enrolledClassroom)
                                            <x-badge size="sm" color="slate" icon="school">{{ $enrolledClassroom->name }}</x-badge>
                                        @empty
                                            <x-badge size="sm" color="sun" icon="circle-dashed">No class yet</x-badge>
                                        @endforelse
                                        @if ($match->enrolledClassrooms->count() > 2)
                                            <x-badge size="sm" color="slate">+{{ $match->enrolledClassrooms->count() - 2 }}</x-badge>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </fieldset>

                    @if ($hasMoreMatches)
                        <p class="flex items-center gap-2 text-sm text-ink-600 dark:text-ink-300" data-enroll-more>
                            <x-icon name="info" class="size-4 shrink-0" />
                            More learners match. Showing the first {{ \App\Http\Controllers\Teacher\ClassroomStudentController::SEARCH_LIMIT }} — type more of the name to narrow the list.
                        </p>
                    @endif

                    <div class="flex justify-end">
                        <x-button type="submit" icon="user-check" data-check-requires="enroll">Enroll selected learners</x-button>
                    </div>
                </form>
            @endif
        </div>
    </x-section>
</x-layouts.app>
