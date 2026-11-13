@php
    $subjectOptions = $subjects->pluck('name', 'id')->all();
    $teachersPerSubject = $subjects->map(fn ($subject) => [
        'label' => $subject->name,
        'value' => $subject->teachers_count,
        'sublabel' => $subject->is_active ? $subject->code : $subject->code.' · inactive',
        'url' => route('admin.subject-assignments.index', ['subject' => $subject->id]),
    ])->values()->all();
@endphp

<x-layouts.app title="Subject assignment" description="Assign subjects to teachers.">
    <x-page-header
        eyebrow="Academic setup"
        title="Subject assignment"
        description="Teachers can only open classrooms, build quizzes, use the question bank and write lesson plans for the subjects assigned here."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Subject assignment']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="book-open" :href="route('admin.subjects.index')">Manage subjects</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card label="Active teachers" :value="number_format($activeTeachersCount)" icon="graduation-cap" color="leaf" data-animate style="--stagger: 1" />
        <x-stat-card
            label="Teachers without subjects"
            :value="number_format($unassignedTeachersCount)"
            icon="user-x"
            :color="$unassignedTeachersCount > 0 ? 'sun' : 'leaf'"
            :hint="$unassignedTeachersCount > 0 ? 'They can’t open classrooms yet' : 'Everyone has a subject'"
            :href="$unassignedTeachersCount > 0 ? route('admin.subject-assignments.index', ['coverage' => 'unassigned']) : null"
            data-animate
            style="--stagger: 2"
        />
        <x-stat-card label="Active subjects" :value="number_format($activeSubjects->count())" icon="book-open" color="grape" :hint="trans_choice(':count subject in total|:count subjects in total', $subjects->count())" data-animate style="--stagger: 3" />
    </div>

    @if ($activeSubjects->isEmpty())
        <x-alert variant="warning" title="No active subjects">
            Create or activate a subject before assigning teachers.
            <a href="{{ route('admin.subjects.create') }}" class="font-semibold underline underline-offset-2">Create a subject</a>
        </x-alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-4 xl:col-span-2">
            <x-filter-bar
                :action="route('admin.subject-assignments.index')"
                search-label="Search teachers"
                search-placeholder="Search teachers by name or email"
                :reset-url="route('admin.subject-assignments.index')"
            >
                <x-form.select name="subject" label="Subject" hide-label placeholder="Any subject" :options="$subjectOptions" :value="$subjectFilter" :old="false" class="sm:w-48" />
                <x-form.select name="coverage" label="Assignments" hide-label placeholder="Any teacher" :options="$coverageOptions" :value="$coverage" :old="false" class="sm:w-48" />
            </x-filter-bar>

            @if ($teachers->isEmpty())
                @if ($isFiltered)
                    <x-empty-state variant="card" illustration="search" title="No teachers match your filters" description="Try another name, subject or assignment filter.">
                        <x-slot:actions>
                            <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.subject-assignments.index')">Clear filters</x-button>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    <x-empty-state variant="card" illustration="classroom" title="No active teachers yet" description="Create teacher accounts first, then assign their subjects here.">
                        <x-slot:actions>
                            <x-button icon="user-plus" :href="route('admin.users.create', ['role' => 'teacher'])">Add teacher</x-button>
                        </x-slot:actions>
                    </x-empty-state>
                @endif
            @else
                @foreach ($teachers as $teacher)
                    @php
                        $bag = \App\Http\Requests\Admin\UpdateSubjectAssignmentRequest::errorBagFor($teacher);
                        $failed = $errors->hasBag($bag);
                        $assignedIds = $teacher->assignedSubjects->modelKeys();
                        $choices = $activeSubjects->concat($teacher->assignedSubjects->where('is_active', false))->values();
                        $formError = $failed ? $errors->getBag($bag)->first('subject_ids') ?: $errors->getBag($bag)->first('subject_ids.*') : null;
                    @endphp

                    <x-section
                        :id="'teacher-'.$teacher->id"
                        class="scroll-mt-24"
                        padding="none"
                        data-assignment-teacher="{{ $teacher->id }}"
                    >
                        <form
                            id="assignment-form-{{ $teacher->id }}"
                            method="POST"
                            action="{{ route('admin.subject-assignments.update', $teacher) }}"
                            data-submit-once
                            data-setup-assignment-form
                            data-setup-revoke-dialog="revoke-subjects-{{ $teacher->id }}"
                        >
                            @csrf
                            @method('PUT')

                            <div class="flex flex-wrap items-start justify-between gap-4 px-5 pt-5 sm:px-6">
                                <div class="flex min-w-0 items-center gap-3">
                                    <x-avatar :name="$teacher->name" size="md" />
                                    <div class="min-w-0">
                                        <h2 class="truncate font-display text-base font-semibold tracking-tight text-ink-950 dark:text-white">{{ $teacher->name }}</h2>
                                        <p class="truncate text-sm text-ink-500 dark:text-ink-400">{{ $teacher->email ?? $teacher->username }}</p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @forelse ($teacher->assignedSubjects as $assignedSubject)
                                        <x-subject-chip :subject="$assignedSubject" size="sm" @class(['opacity-60' => ! $assignedSubject->is_active]) />
                                    @empty
                                        <x-badge color="sun" icon="triangle-alert">No subjects</x-badge>
                                    @endforelse
                                </div>
                            </div>

                            <fieldset class="px-5 py-5 sm:px-6">
                                <legend class="sr-only">Subjects for {{ $teacher->name }}</legend>

                                @if ($choices->isEmpty())
                                    <p class="text-sm text-ink-500 dark:text-ink-400">There are no active subjects to assign yet.</p>
                                @else
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        @foreach ($choices as $subject)
                                            @php
                                                $isAssigned = in_array($subject->id, $assignedIds, true);
                                                $classroomCount = $classroomCounts[$teacher->id.':'.$subject->id] ?? 0;
                                                $quizCount = $quizCounts[$teacher->id.':'.$subject->id] ?? 0;
                                                $lockedParts = array_values(array_filter([
                                                    $classroomCount > 0 ? trans_choice(':count classroom|:count classrooms', $classroomCount) : null,
                                                    $quizCount > 0 ? trans_choice(':count quiz|:count quizzes', $quizCount) : null,
                                                ]));
                                                $description = match (true) {
                                                    $isAssigned && ! $subject->is_active => 'Inactive subject. Uncheck to revoke it.',
                                                    $isAssigned && $lockedParts !== [] => 'Revoking removes access to '.implode(' and ', $lockedParts).'.',
                                                    $isAssigned => 'Assigned · no classrooms or quizzes yet.',
                                                    default => 'Not assigned.',
                                                };
                                            @endphp
                                            <div
                                                @class([
                                                    'rounded-xl p-3 ring-1 transition ring-inset',
                                                    'bg-leaf-50/60 ring-leaf-600/20 dark:bg-leaf-400/5 dark:ring-leaf-400/20' => $isAssigned,
                                                    'bg-white ring-ink-900/8 dark:bg-white/3 dark:ring-white/8' => ! $isAssigned,
                                                    'data-adding:bg-leaf-50 data-adding:ring-leaf-500/50 data-revoking:bg-berry-50 data-revoking:ring-berry-500/50 dark:data-adding:bg-leaf-400/10 dark:data-revoking:bg-berry-400/10',
                                                ])
                                                data-setup-assignment-option
                                            >
                                                <x-form.checkbox
                                                    name="subject_ids[]"
                                                    :value="$subject->id"
                                                    :id="'teacher-'.$teacher->id.'-subject-'.$subject->id"
                                                    :checked="$isAssigned"
                                                    :description="$description"
                                                    :bag="$bag"
                                                    :old="$failed"
                                                    data-setup-assigned="{{ $isAssigned ? '1' : '0' }}"
                                                    data-setup-locks="{{ $isAssigned ? $classroomCount : 0 }}"
                                                    data-setup-subject="{{ $subject->name }}"
                                                >
                                                    <span class="inline-flex flex-wrap items-center gap-2">
                                                        <span>{{ $subject->name }}</span>
                                                        <span class="font-mono text-xs font-normal text-ink-500 dark:text-ink-400">{{ $subject->code }}</span>
                                                        @unless ($subject->is_active)
                                                            <x-badge color="slate" size="sm">Inactive</x-badge>
                                                        @endunless
                                                    </span>
                                                </x-form.checkbox>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if (filled($formError))
                                    <x-form.error :message="$formError" class="mt-3" />
                                @endif
                            </fieldset>

                            <div class="flex flex-col-reverse gap-3 rounded-b-2xl border-t border-ink-900/5 bg-ink-50/60 px-5 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-white/8 dark:bg-white/3">
                                <p class="text-xs text-ink-500 dark:text-ink-400" data-setup-assignment-summary aria-live="polite">
                                    {{ trans_choice(':count subject assigned|:count subjects assigned', count($assignedIds)) }}
                                </p>
                                <x-button type="submit" size="sm" icon="check" :disabled="$choices->isEmpty()">Save subjects</x-button>
                            </div>
                        </form>

                        <x-modal
                            :name="'revoke-subjects-'.$teacher->id"
                            :title="'Revoke subjects from '.$teacher->name.'?'"
                            description="They lose access to these subjects’ classrooms, quizzes and lesson plans until you assign them again. Nothing is deleted."
                            icon="lock"
                            size="sm"
                        >
                            <ul class="flex flex-col gap-1.5 text-sm text-ink-700 dark:text-ink-200" data-setup-revoke-list></ul>
                            <x-slot:footer>
                                <x-button variant="secondary" data-modal-close data-modal-initial-focus>Cancel</x-button>
                                <x-button type="submit" variant="danger" icon="lock" :form="'assignment-form-'.$teacher->id" data-setup-revoke-confirm>Revoke and save</x-button>
                            </x-slot:footer>
                        </x-modal>
                    </x-section>
                @endforeach

                @if ($teachers->hasPages())
                    <div>{{ $teachers->links() }}</div>
                @endif
            @endif
        </div>

        <aside class="flex min-w-0 flex-col gap-6">
            <x-section title="Teachers per subject" description="Active teachers assigned to each subject." icon="chart-bar" color="grape">
                <x-chart.hbar
                    label="Teachers per subject"
                    label-heading="Subject"
                    value-heading="Teachers"
                    :items="$teachersPerSubject"
                    empty-text="Create subjects to see how they are staffed."
                />
            </x-section>

            <x-section title="How subject access works" icon="shield-check" color="sky">
                <ul class="flex flex-col gap-3 text-sm text-ink-600 dark:text-ink-300">
                    <li class="flex gap-2.5"><x-icon name="circle-check" class="mt-0.5 size-4 shrink-0 text-leaf-600 dark:text-leaf-400" />Assigned teachers can open classrooms, build quizzes and share bank questions for the subject.</li>
                    <li class="flex gap-2.5"><x-icon name="lock" class="mt-0.5 size-4 shrink-0 text-ink-500 dark:text-ink-400" />Revoking locks the teacher’s classrooms, quizzes and lesson plans of that subject. Nothing is deleted: assigning it again restores access.</li>
                    <li class="flex gap-2.5"><x-icon name="eye-off" class="mt-0.5 size-4 shrink-0 text-ink-500 dark:text-ink-400" />Inactive subjects can’t be newly assigned. Existing assignments stay until you revoke them.</li>
                </ul>
            </x-section>
        </aside>
    </div>
</x-layouts.app>
