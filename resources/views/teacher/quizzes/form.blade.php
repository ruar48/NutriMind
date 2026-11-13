@php
    $isEditing = $quiz->exists;
    $lockedSettings = $isEditing && $hasAttempts;
    $gradeValue = $quiz->grade_level instanceof \App\Enums\GradeLevel ? $quiz->grade_level->value : $quiz->grade_level;
    $moduleGroups = $modules->groupBy(fn ($module) => $module->subject_id.'-'.$module->grade_level->value);
    $hasOldInput = is_array(old()) && old() !== [];
    $oldClassroomIds = array_map('intval', (array) old('classroom_ids', []));
@endphp

<x-layouts.app :title="$isEditing ? 'Edit '.$quiz->title : 'New quiz'">
    <x-page-header
        eyebrow="Assessments"
        :title="$isEditing ? 'Edit quiz settings' : 'New quiz'"
        :description="$isEditing ? 'Change how learners take “'.$quiz->title.'” and which classes see it.' : 'Questions are added on the next screen. New quizzes start as drafts.'"
        :breadcrumbs="array_values(array_filter([
            ['label' => 'Quizzes', 'url' => route('teacher.quizzes.index')],
            $isEditing ? ['label' => $quiz->title, 'url' => route('teacher.quizzes.show', $quiz)] : null,
            ['label' => $isEditing ? 'Edit settings' : 'New quiz'],
        ]))"
    />

    @if ($lockedSettings)
        <x-alert variant="info" icon="lock" title="Some settings are locked">
            Learners have already taken this quiz, so its subject, grade, passing score, time limit, attempts and question order stay as they are, and classrooms with attempts stay assigned. Duplicate the quiz to change them.
        </x-alert>
    @endif

    <form
        method="POST"
        action="{{ $isEditing ? route('teacher.quizzes.update', $quiz) : route('teacher.quizzes.store') }}"
        class="flex flex-col gap-8"
        data-submit-once
        data-quiz-filter
        @if ($lockedSettings) data-quiz-subject="{{ $quiz->subject_id }}" data-quiz-grade="{{ $gradeValue }}" @endif
    >
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <x-form.section title="Quiz details" description="Learners see the title and instructions before they start." columns="2">
            <x-form.input name="title" label="Title" :value="$quiz->title" maxlength="150" placeholder="Go, Grow and Glow Foods Quiz" required class="col-span-full" />

            @if ($lockedSettings)
                <x-definition-list class="col-span-full">
                    <x-detail-row label="Subject" icon="lock"><x-subject-chip :subject="$quiz->subject" size="sm" /></x-detail-row>
                    <x-detail-row label="Grade level" icon="lock" :value="$quiz->grade_level->label()" />
                </x-definition-list>
            @else
                @if ($subjectOptions === [])
                    <x-alert variant="warning" icon="triangle-alert" class="col-span-full">
                        None of your assigned subjects is active. Ask your school administrator to assign an active subject.
                    </x-alert>
                @endif
                <x-form.select name="subject_id" label="Subject" :options="$subjectOptions" :value="$quiz->subject_id" placeholder="Choose a subject" required data-quiz-subject-select />
                <x-form.select name="grade_level" label="Grade level" :options="$gradeOptions" :value="$gradeValue" placeholder="Choose a grade" required data-quiz-grade-select />
            @endif

            <x-form.select name="learning_module_id" label="Learning module" :value="$quiz->learning_module_id" placeholder="Not linked to a module" hint="Only modules of the chosen subject and grade can be linked." optional data-quiz-module-select>
                @foreach ($moduleGroups as $group)
                    @php $first = $group->first(); @endphp
                    <optgroup label="{{ $first->subject->name }} · {{ $first->grade_level->label() }}" data-quiz-match-subject="{{ $first->subject_id }}" data-quiz-match-grade="{{ $first->grade_level->value }}">
                        @foreach ($group as $module)
                            <option value="{{ $module->id }}" @selected((string) old('learning_module_id', $quiz->learning_module_id) === (string) $module->id)>{{ $module->title }}{{ $module->world_name ? ' — '.$module->world_name : '' }}{{ $module->is_published ? '' : ' (unpublished)' }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </x-form.select>
            <x-form.select name="academic_term_id" label="Quarter" :options="$termOptions" :value="$quiz->academic_term_id" placeholder="No quarter" :hint="$termOptions === [] ? 'No school year is active yet.' : null" optional />
            <x-form.textarea name="instructions" label="Instructions" :value="$quiz->instructions" :max-length="2000" rows="4" placeholder="Read each question carefully and choose the best answer." optional class="col-span-full" />
        </x-form.section>

        <x-form.section title="Rules" description="How learners take the quiz and when they pass." columns="2">
            @if ($lockedSettings)
                <x-definition-list class="col-span-full">
                    <x-detail-row label="Passing score" icon="lock" :value="$quiz->passing_score.'%'" />
                    <x-detail-row label="Time limit" icon="lock" :value="$quiz->time_limit_minutes ? trans_choice(':count minute|:count minutes', $quiz->time_limit_minutes) : 'No time limit'" />
                    <x-detail-row label="Attempts allowed" icon="lock" :value="$quiz->max_attempts ? number_format($quiz->max_attempts) : 'Unlimited'" />
                    <x-detail-row label="Question order" icon="lock" :value="$quiz->shuffle_questions ? 'Shuffled for each learner' : 'Same order for everyone'" />
                </x-definition-list>
            @else
                <x-form.input name="passing_score" type="number" label="Passing score" :value="$quiz->passing_score" min="1" max="100" suffix="%" inputmode="numeric" required />
                <x-form.input name="time_limit_minutes" type="number" label="Time limit" :value="$quiz->time_limit_minutes" min="1" max="180" suffix="min" inputmode="numeric" hint="1 to 180 minutes. Leave empty for no limit." optional />
                <x-form.input name="max_attempts" type="number" label="Attempts allowed" :value="$quiz->max_attempts" min="1" max="10" inputmode="numeric" hint="1 to 10. Leave empty for unlimited attempts." optional />
            @endif
            <div class="col-span-full flex flex-col gap-5">
                @unless ($lockedSettings)
                    <x-form.toggle name="shuffle_questions" label="Shuffle questions" description="Each learner sees the questions in a different order." :checked="(bool) $quiz->shuffle_questions" unchecked-value="0" />
                @endunless
                <x-form.toggle name="show_correct_answers" label="Show correct answers" description="Learners and parents see the right answers and explanations after the quiz is submitted." :checked="(bool) $quiz->show_correct_answers" unchecked-value="0" />
            </div>
        </x-form.section>

        <x-form.section title="Classrooms" description="The quiz appears in these classes once it is published. Only your active classrooms of the quiz's subject and grade can be chosen.">
            <fieldset class="flex flex-col gap-3">
                <legend class="sr-only">Classrooms</legend>
                @forelse ($classrooms as $classroom)
                    @php
                        $isAttempted = in_array($classroom->id, $attemptedClassroomIds, true);
                        $isChecked = $isAttempted || ($hasOldInput ? in_array($classroom->id, $oldClassroomIds, true) : in_array($classroom->id, $assignedClassroomIds, true));
                    @endphp
                    <div data-quiz-classroom data-quiz-match-subject="{{ $classroom->subject_id }}" data-quiz-match-grade="{{ $classroom->grade_level->value }}">
                        <x-form.checkbox
                            name="classroom_ids[]"
                            :value="$classroom->id"
                            :label="$classroom->name"
                            :description="$classroom->subject->name.' · '.$classroom->grade_level->label().' · Section '.$classroom->section.($isAttempted ? ' · Has learner attempts' : '')"
                            :checked="$isChecked"
                            :old="false"
                            :disabled="$isAttempted"
                            :data-quiz-locked="$isAttempted ? 'true' : null"
                        />
                        @if ($isAttempted)
                            <input type="hidden" name="classroom_ids[]" value="{{ $classroom->id }}">
                        @endif
                    </div>
                @empty
                    <x-empty-state compact icon="school" title="No active classrooms yet" description="Create a classroom first, then assign this quiz to it. You can also save the quiz now and assign it later.">
                        <x-slot:actions><x-button size="sm" variant="soft" icon="plus" :href="route('teacher.classrooms.create')">New classroom</x-button></x-slot:actions>
                    </x-empty-state>
                @endforelse
                @if ($classrooms->isNotEmpty())
                    <p data-quiz-classrooms-empty hidden class="text-sm text-ink-500 dark:text-ink-400">None of your active classrooms matches the chosen subject and grade.</p>
                @endif
                <x-form.error for="classroom_ids" />
            </fieldset>
        </x-form.section>

        <x-form.section title="Availability" description="When learners in the selected classrooms can start the quiz. The window applies to every selected classroom." columns="2">
            <x-form.input name="available_from" type="datetime-local" label="Opens" :value="$window['available_from']" hint="Leave empty to open as soon as it is published." optional />
            <x-form.input name="due_at" type="datetime-local" label="Due" :value="$window['due_at']" hint="Leave empty for no due date." optional />
        </x-form.section>

        <x-form.actions
            :cancel="$isEditing ? route('teacher.quizzes.show', $quiz) : route('teacher.quizzes.index')"
            :submit="$isEditing ? 'Save changes' : 'Create quiz'"
            :submit-icon="$isEditing ? 'check' : 'plus'"
        >
            <x-icon name="info" class="size-4" />
            {{ $isEditing && ! $quiz->isDraft() ? 'Changes apply to learners right away.' : 'Quizzes stay in Draft until you review and publish them.' }}
        </x-form.actions>
    </form>
</x-layouts.app>
