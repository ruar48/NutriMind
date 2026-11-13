@props([
    'classroom',
    'action',
    'cancel',
    'schoolYear' => null,
    'subjectOptions' => [],
    'subjectShortNames' => [],
    'subjectShortName' => null,
])

@php
    $isEditing = $classroom->exists;
@endphp

<form
    method="POST"
    action="{{ $action }}"
    {{ $attributes->class('flex flex-col gap-8') }}
    data-submit-once
    data-classrooms-name-suggest
    @if ($isEditing)
        data-classrooms-fixed-subject="{{ $subjectShortName }}"
        data-classrooms-fixed-grade="{{ $classroom->grade_level->value }}"
    @else
        data-classrooms-subject-names="{{ json_encode((object) $subjectShortNames) }}"
    @endif
>
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif

    <x-form.section
        title="Class details"
        :description="$isEditing
            ? 'The subject and grade stay as they were created, so quiz results and progress keep their meaning.'
            : 'Choose one of your subjects and the section you teach it to. Each section has one classroom per subject each school year.'"
        columns="2"
    >
        @if ($isEditing)
            <x-form.input name="subject_display" label="Subject" :value="$classroom->subject->name" prefix-icon="lock" hint="Fixed after the classroom is created." :old="false" disabled />
            <x-form.input name="grade_display" label="Grade level" :value="$classroom->grade_level->label()" prefix-icon="lock" hint="Fixed after the classroom is created." :old="false" disabled />
        @else
            <x-form.select
                name="subject_id"
                label="Subject"
                :options="$subjectOptions"
                :value="$classroom->subject_id"
                placeholder="Choose a subject"
                hint="Only active subjects assigned to you are listed."
                required
                data-classrooms-subject
            />
            <x-form.select
                name="grade_level"
                label="Grade level"
                :options="\App\Enums\GradeLevel::options()"
                :value="$classroom->grade_level"
                placeholder="Choose a grade"
                required
                data-classrooms-grade
            />
        @endif

        <x-form.input name="section" label="Section" :value="$classroom->section" maxlength="50" placeholder="e.g. Sampaguita" autocomplete="off" required data-classrooms-section />
        <x-form.input name="room" label="Room" :value="$classroom->room" maxlength="50" placeholder="e.g. Room 204" autocomplete="off" optional />
        <x-form.input
            name="name"
            label="Classroom name"
            :value="$classroom->name"
            maxlength="100"
            placeholder="e.g. Health 5 – Sampaguita"
            hint="Learners see this name in the game. It follows the subject, grade and section until you type your own."
            autocomplete="off"
            required
            class="col-span-full"
            data-classrooms-name
        />
        <x-form.textarea
            name="description"
            label="Description"
            :value="$classroom->description"
            :max-length="500"
            rows="3"
            hint="A short note for yourself, such as the schedule or adviser."
            optional
            class="col-span-full"
        />
    </x-form.section>

    <x-form.section title="Learning mode" description="How the NutriMind game opens worlds, missions and activities for this class. You can change it at any time.">
        <x-form.radio-cards name="learning_mode" label="Choose a learning mode" :options="\App\Enums\LearningMode::cases()" :value="$classroom->learning_mode" columns="1" required class="col-span-full" />
    </x-form.section>

    <x-form.actions
        :cancel="$cancel"
        :submit="$isEditing ? 'Save changes' : 'Create classroom'"
        :submit-icon="$isEditing ? 'check' : 'plus'"
    >
        @if ($schoolYear)
            <x-icon name="calendar-range" class="size-4" />
            <span>School year {{ $schoolYear->name }}</span>
        @endif
    </x-form.actions>
</form>
