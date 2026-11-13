@php
    $isEditing = $question->exists;
    $typeUrls = collect(\App\Enums\QuestionType::cases())->mapWithKeys(fn ($case) => [
        $case->value => $isEditing
            ? route('teacher.quizzes.questions.edit', [$quiz, $question, 'type' => $case->value])
            : route('teacher.quizzes.questions.create', [$quiz, 'type' => $case->value]),
    ])->all();
@endphp

<x-layouts.app :title="($isEditing ? 'Edit question ' : 'New question ').$number.' · '.$quiz->title">
    <x-page-header
        eyebrow="Quiz builder"
        :title="$isEditing ? 'Edit question '.$number : 'Question '.$number"
        :description="$quiz->title.' · '.$quiz->subject->name.' · '.$quiz->grade_level->label()"
        :breadcrumbs="[
            ['label' => 'Quizzes', 'url' => route('teacher.quizzes.index')],
            ['label' => $quiz->title, 'url' => route('teacher.quizzes.show', $quiz)],
            ['label' => $isEditing ? 'Edit question '.$number : 'New question'],
        ]"
    >
        <x-slot:meta>
            <x-status-badge :status="$type" />
            @if ($quiz->isReviewed())
                <x-badge color="sun" icon="triangle-alert">Saving asks for a new review</x-badge>
            @endif
        </x-slot:meta>
    </x-page-header>

    <form
        method="POST"
        action="{{ $isEditing ? route('teacher.quizzes.questions.update', [$quiz, $question]) : route('teacher.quizzes.questions.store', $quiz) }}"
        enctype="multipart/form-data"
        class="flex flex-col gap-8"
        data-submit-once
    >
        @csrf
        @if ($isEditing)
            @method('PUT')
        @else
            {{-- Pressing Enter in a field submits with the first submit button: keep that the plain "Add question". --}}
            <button type="submit" class="sr-only" tabindex="-1" aria-hidden="true">Add question</button>
        @endif

        <x-quiz.question-fields :question="$question" :type="$type" :type-urls="$typeUrls" :media-limits="$mediaLimits">
            <x-slot:details>
                <x-form.input name="points" type="number" label="Points" :value="$question->points" min="1" max="10" inputmode="numeric" suffix="pts" hint="1 to 10 points for a correct answer." required />
            </x-slot:details>
        </x-quiz.question-fields>

        <x-form.actions
            :cancel="route('teacher.quizzes.show', $quiz).($isEditing ? '#question-'.$question->id : '')"
            :submit="$isEditing ? 'Save question' : 'Add question'"
            :submit-icon="$isEditing ? 'check' : 'plus'"
        >
            @unless ($isEditing)
                <x-button type="submit" name="next" value="another" variant="ghost" icon="list-plus">Save and add another</x-button>
            @else
                <x-icon name="info" class="size-4" />
                Changes are saved to this quiz only.
            @endunless
        </x-form.actions>
    </form>
</x-layouts.app>
