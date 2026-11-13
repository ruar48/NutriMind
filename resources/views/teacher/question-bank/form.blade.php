@php
    $isEditing = $item->exists;
    $typeUrls = collect(\App\Enums\QuestionType::cases())->mapWithKeys(fn ($case) => [
        $case->value => $isEditing
            ? route('teacher.question-bank.edit', [$item, 'type' => $case->value])
            : route('teacher.question-bank.create', array_filter(['type' => $case->value, 'subject' => $item->subject_id, 'grade' => $item->grade_level?->value])),
    ])->all();
    $gradeValue = $item->grade_level?->value;
    $moduleGroups = $modules->groupBy(fn ($module) => $module->subject_id.'-'.$module->grade_level->value);
@endphp

<x-layouts.app :title="$isEditing ? 'Edit bank question' : 'New bank question'">
    <x-page-header
        eyebrow="Question bank"
        :title="$isEditing ? 'Edit question' : 'New question'"
        description="Bank questions can be added to any of your quizzes of the same subject and grade. Each quiz gets its own copy."
        :breadcrumbs="array_values(array_filter([
            ['label' => 'Question bank', 'url' => route('teacher.question-bank.index')],
            $isEditing ? ['label' => \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($item->prompt), 40), 'url' => route('teacher.question-bank.show', $item)] : null,
            ['label' => $isEditing ? 'Edit' : 'New question'],
        ]))"
    >
        <x-slot:meta>
            <x-status-badge :status="$type" />
        </x-slot:meta>
    </x-page-header>

    <form
        method="POST"
        action="{{ $isEditing ? route('teacher.question-bank.update', $item) : route('teacher.question-bank.store') }}"
        enctype="multipart/form-data"
        class="flex flex-col gap-8"
        data-submit-once
        data-quiz-filter
    >
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <x-quiz.question-fields :question="$item" :type="$type" :type-urls="$typeUrls" :media-limits="$mediaLimits" />

        <x-form.section title="Filing" description="Where the question belongs, so you and other teachers can find it." columns="2">
            @if ($subjectOptions === [])
                <x-alert variant="warning" icon="triangle-alert" class="col-span-full">
                    None of your assigned subjects is active. Ask your school administrator to assign an active subject.
                </x-alert>
            @endif
            <x-form.select name="subject_id" label="Subject" :options="$subjectOptions" :value="$item->subject_id" placeholder="Choose a subject" required data-quiz-subject-select />
            <x-form.select name="grade_level" label="Grade level" :options="\App\Enums\GradeLevel::options()" :value="$gradeValue" placeholder="Choose a grade" required data-quiz-grade-select />
            <x-form.select name="learning_module_id" label="Learning module" :value="$item->learning_module_id" placeholder="Not linked to a module" hint="Only modules of the chosen subject and grade can be linked." optional data-quiz-module-select class="col-span-full">
                @foreach ($moduleGroups as $group)
                    @php $first = $group->first(); @endphp
                    <optgroup label="{{ $first->subject->name }} · {{ $first->grade_level->label() }}" data-quiz-match-subject="{{ $first->subject_id }}" data-quiz-match-grade="{{ $first->grade_level->value }}">
                        @foreach ($group as $module)
                            <option value="{{ $module->id }}" @selected((string) old('learning_module_id', $item->learning_module_id) === (string) $module->id)>{{ $module->title }}{{ $module->world_name ? ' — '.$module->world_name : '' }}{{ $module->is_published ? '' : ' (unpublished)' }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </x-form.select>
            <x-form.input name="topic" label="Topic" :value="$item->topic" maxlength="100" placeholder="Food groups" optional />
            <x-form.input name="competency_code" label="Competency code" :value="$item->competency_code" maxlength="40" placeholder="H5N-Ia-1" hint="The DepEd learning competency this question assesses." optional />
            <x-form.radio-cards name="difficulty" label="Difficulty" :options="\App\Enums\Difficulty::cases()" :value="$item->difficulty" columns="3" required class="col-span-full" />
        </x-form.section>

        <x-form.section title="Sharing and review" description="Shared questions appear in the bank of every teacher of this subject. Only you can change your questions.">
            @if ($canShare)
                <x-form.toggle name="is_shared" label="Share with other teachers" description="They can add it to their quizzes or copy it into their own bank." :checked="(bool) $item->is_shared" unchecked-value="0" />
            @else
                <x-alert variant="info" icon="lock">
                    Your account cannot share questions, so this question is saved as private.
                </x-alert>
            @endif
            <x-form.toggle name="reviewed" label="Mark as reviewed" description="You checked the question, its answer and its media. Teachers can filter for reviewed questions." :checked="$item->reviewed_at !== null" unchecked-value="0" />
        </x-form.section>

        <x-form.actions
            :cancel="$isEditing ? route('teacher.question-bank.show', $item) : route('teacher.question-bank.index')"
            :submit="$isEditing ? 'Save question' : 'Add to bank'"
            :submit-icon="$isEditing ? 'check' : 'plus'"
        >
            <x-icon name="info" class="size-4" />
            Quizzes that already use this question keep their own copy.
        </x-form.actions>
    </form>
</x-layouts.app>
