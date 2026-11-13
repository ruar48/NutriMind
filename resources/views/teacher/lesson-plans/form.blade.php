@php
    $isEditing = $lessonPlan->exists;
@endphp

<x-layouts.app :title="$isEditing ? 'Edit '.$lessonPlan->title : 'New lesson plan'">
    <x-page-header
        eyebrow="Teaching"
        :title="$isEditing ? 'Edit lesson plan' : 'New lesson plan'"
        :description="$isEditing ? $lessonPlan->title : 'You align game activities, quizzes and missions to the plan on the next screen.'"
        :breadcrumbs="array_values(array_filter([
            ['label' => 'Lesson plans', 'url' => route('teacher.lesson-plans.index')],
            $isEditing ? ['label' => $lessonPlan->title, 'url' => route('teacher.lesson-plans.show', $lessonPlan)] : null,
            ['label' => $isEditing ? 'Edit' : 'New lesson plan'],
        ]))"
    />

    @if ($subjectOptions->isEmpty())
        <x-alert variant="warning" title="No active subject to plan for">
            None of your assigned subjects is active right now. Ask your school administrator to assign an active subject.
        </x-alert>
    @endif

    <form method="POST" action="{{ $isEditing ? route('teacher.lesson-plans.update', $lessonPlan) : route('teacher.lesson-plans.store') }}" class="flex flex-col gap-8" data-submit-once data-animate style="--stagger: 1">
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <x-form.section title="Lesson" description="What you will teach and how far along it is." columns="2">
            <x-form.input name="title" label="Title" :value="$lessonPlan->title" maxlength="150" placeholder="e.g. Sorting Go, Grow and Glow Foods" required class="col-span-full" />
            <x-form.select name="subject_id" label="Subject" :options="$subjectOptions" :value="$lessonPlan->subject_id" placeholder="Choose a subject" required />
            <x-form.select name="grade_level" label="Grade level" :options="\App\Enums\GradeLevel::options()" :value="$lessonPlan->grade_level" placeholder="Choose a grade" required />
            <x-form.radio-cards name="status" label="Status" :options="\App\Enums\LessonPlanStatus::cases()" :value="$lessonPlan->status" columns="3" required class="col-span-full" />
            <x-form.textarea name="objectives" label="Objectives" :value="$lessonPlan->objectives" :max-length="2000" rows="4" placeholder="What learners can do by the end of the lesson." optional class="col-span-full" />
        </x-form.section>

        <x-form.section title="Schedule" description="When you teach the lesson." columns="2">
            <x-form.select name="academic_term_id" label="Quarter" :options="$termOptions" :value="$lessonPlan->academic_term_id" placeholder="No quarter"
                :hint="$activeSchoolYear ? 'Quarters of School Year '.$activeSchoolYear->name.'.' : 'No school year is active yet, so no quarter can be chosen.'" optional />
            <x-form.input name="week_number" type="number" label="Week" :value="$lessonPlan->week_number" min="1" max="12" prefix-icon="calendar-days" hint="Week of the quarter (1–12)." optional />
            <x-form.input name="starts_on" type="date" label="Starts on" :value="$lessonPlan->starts_on" optional />
            <x-form.input name="ends_on" type="date" label="Ends on" :value="$lessonPlan->ends_on" optional />
        </x-form.section>

        <x-form.section title="Curriculum and game module" description="The competency you teach and the game module that practices it." columns="2">
            <x-slot:aside>
                <p class="flex items-start gap-2 text-sm text-ink-600 dark:text-ink-300">
                    <x-icon name="lightbulb" class="mt-0.5 size-4 shrink-0 text-sun-600 dark:text-sun-400" />
                    <span>Picking a module fills in its competency for you. The competency code also tells the administrator which game content your lessons need.</span>
                </p>
            </x-slot:aside>
            <x-form.select
                name="learning_module_id"
                label="Learning module"
                :value="$lessonPlan->learning_module_id"
                placeholder="No module"
                hint="Published modules of the chosen subject and grade."
                optional
                class="col-span-full"
                data-content-module-picker
                data-content-subject-field="subject_id"
                data-content-grade-field="grade_level"
            >
                <x-content.module-options :modules="$modules" :selected="old('learning_module_id', $lessonPlan->learning_module_id)" />
            </x-form.select>
            <x-form.input name="competency_code" label="Competency code" :value="$lessonPlan->competency_code" :maxlength="\App\Services\Content\CompetencyCode::MAX_LENGTH" prefix-icon="hash" input-class="font-mono uppercase" placeholder="e.g. H5N-IA-1" autocomplete="off" spellcheck="false" data-content-fill="code" optional />
            <x-form.textarea name="learning_competency" label="Learning competency" :value="$lessonPlan->learning_competency" :max-length="1000" rows="3" data-content-fill="competency" optional class="col-span-full" />
        </x-form.section>

        <x-form.section title="Notes" description="Materials, reminders or reflections for yourself.">
            <x-form.textarea name="notes" label="Notes" :value="$lessonPlan->notes" :max-length="2000" rows="4" optional />
        </x-form.section>

        <x-form.actions
            :cancel="$isEditing ? route('teacher.lesson-plans.show', $lessonPlan) : route('teacher.lesson-plans.index')"
            :submit="$isEditing ? 'Save changes' : 'Create lesson plan'"
            :submit-icon="$isEditing ? 'check' : 'plus'"
        >
            @if ($isEditing)
                <x-icon name="info" class="size-4" />
                Changing the subject or grade removes alignments that no longer match.
            @endif
        </x-form.actions>
    </form>
</x-layouts.app>
