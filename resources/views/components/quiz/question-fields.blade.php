{{--
    The question content fields shared by the quiz question form and the question bank form: question type tabs
    (links that switch the form to another type), prompt, answers for the type, explanation and media.
    typeUrls: [QuestionType value => URL of this form for that type]; mediaLimits: ['image' => '2 MB', 'video' => '5 MB'].
    Slot "details" adds fields at the end of the first section (e.g. points).
--}}
@props([
    'question',
    'type',
    'typeUrls' => [],
    'mediaLimits' => ['image' => '', 'video' => ''],
])

@php
    $sameType = $question->type === $type;
    $storedChoices = $sameType && is_array($question->choices) ? array_values($question->choices) : ['', '', '', ''];
    $oldChoices = old('choices');
    $choiceRows = is_array($oldChoices) ? array_values($oldChoices) : $storedChoices;
    $correctAnswer = (string) old('correct_answer', $sameType ? $question->correct_answer : '');
    $acceptedAnswers = $sameType && $type === \App\Enums\QuestionType::Identification ? implode("\n", $question->acceptedAnswers()) : '';
    $promptPlaceholders = [
        'multiple_choice' => 'Which food group gives our body energy to work and play?',
        'true_false' => 'Fruits and vegetables are Glow foods.',
        'identification' => 'What vitamin is found in large amounts in oranges and calamansi?',
    ];
    $answerDescriptions = [
        'multiple_choice' => 'Add 2 to 6 choices and mark the correct one.',
        'true_false' => 'Choose whether the statement is true or false.',
        'identification' => 'List every answer that counts as correct.',
    ];
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
@endphp

<x-form.section title="Question" :description="$type->description()" columns="2">
    <div class="col-span-full flex min-w-0 flex-col gap-2">
        <p class="text-sm font-medium text-ink-800 dark:text-ink-100" id="question-type-label">Question type</p>
        <x-tabs label="Question type">
            @foreach (\App\Enums\QuestionType::cases() as $case)
                <x-tab :href="$typeUrls[$case->value] ?? '#'" :active="$case === $type" :icon="$case->icon()">{{ $case->label() }}</x-tab>
            @endforeach
        </x-tabs>
        @if ($question->exists && ! $sameType)
            <x-alert variant="warning" icon="triangle-alert">
                Saving changes this question from {{ strtolower($question->type->label()) }} to {{ strtolower($type->label()) }}. Its previous answers are replaced.
            </x-alert>
        @endif
        <input type="hidden" name="type" value="{{ $type->value }}">
        <x-form.error for="type" />
    </div>

    <x-form.textarea
        name="prompt"
        :label="$type === \App\Enums\QuestionType::TrueFalse ? 'Statement' : 'Question'"
        :value="$question->prompt"
        :max-length="2000"
        rows="3"
        :placeholder="$promptPlaceholders[$type->value]"
        :hint="$type === \App\Enums\QuestionType::TrueFalse ? 'Write a statement that learners mark as true or false.' : null"
        required
        class="col-span-full"
    />

    {{ $details ?? '' }}
</x-form.section>

<x-form.section title="Answer" :description="$answerDescriptions[$type->value]">
    @if ($type === \App\Enums\QuestionType::MultipleChoice)
        <fieldset class="col-span-full flex min-w-0 flex-col gap-3" data-repeater="choices" data-repeater-min="2" data-repeater-max="6">
            <legend class="mb-2 flex items-baseline gap-1 text-sm font-medium text-ink-800 dark:text-ink-100">
                Choices <span class="text-berry-600 dark:text-berry-400" aria-hidden="true">*</span>
            </legend>
            <div data-repeater-items class="flex flex-col gap-3">
                @foreach ($choiceRows as $index => $choice)
                    <div data-repeater-item class="flex items-start gap-2 sm:gap-3">
                        <span data-repeater-letter aria-hidden="true" class="grid size-11 shrink-0 place-items-center rounded-xl bg-ink-900/5 font-display font-bold text-ink-700 dark:bg-white/8 dark:text-ink-200">{{ $letters[$index] ?? $index + 1 }}</span>
                        <span id="choices-{{ $index }}-label" class="sr-only">Choice <span data-repeater-letter>{{ $letters[$index] ?? $index + 1 }}</span></span>
                        <x-form.input
                            :name="'choices['.$index.']'"
                            :value="is_string($choice) ? $choice : ''"
                            :old="false"
                            aria-labelledby="choices-{{ $index }}-label"
                            maxlength="300"
                            data-repeater-focus
                            required
                            class="min-w-0 flex-1"
                        />
                        <label class="flex h-11 shrink-0 cursor-pointer items-center gap-2 rounded-xl px-2 text-sm font-medium text-ink-700 transition hover:bg-leaf-50 has-checked:bg-leaf-50 has-checked:text-leaf-800 dark:text-ink-200 dark:hover:bg-leaf-400/10 dark:has-checked:bg-leaf-400/10 dark:has-checked:text-leaf-200">
                            <input type="radio" name="correct_answer" value="{{ $index }}" data-repeater-value class="size-4 accent-leaf-600" @checked($correctAnswer === (string) $index) required>
                            <span class="hidden sm:inline" aria-hidden="true">Correct</span>
                            <span class="sr-only">Choice <span data-repeater-letter>{{ $letters[$index] ?? $index + 1 }}</span> is correct</span>
                        </label>
                        <x-icon-button icon="trash-2" label="Remove choice" variant="danger" data-repeater-remove data-js-only />
                    </div>
                @endforeach
            </div>
            <template data-repeater-template>
                <div data-repeater-item class="flex items-start gap-2 sm:gap-3">
                    <span data-repeater-letter aria-hidden="true" class="grid size-11 shrink-0 place-items-center rounded-xl bg-ink-900/5 font-display font-bold text-ink-700 dark:bg-white/8 dark:text-ink-200"></span>
                    <span id="choices-__INDEX__-label" class="sr-only">Choice <span data-repeater-letter></span></span>
                    <x-form.input
                        name="choices[__INDEX__]"
                        :old="false"
                        aria-labelledby="choices-__INDEX__-label"
                        maxlength="300"
                        data-repeater-focus
                        required
                        class="min-w-0 flex-1"
                    />
                    <label class="flex h-11 shrink-0 cursor-pointer items-center gap-2 rounded-xl px-2 text-sm font-medium text-ink-700 transition hover:bg-leaf-50 has-checked:bg-leaf-50 has-checked:text-leaf-800 dark:text-ink-200 dark:hover:bg-leaf-400/10 dark:has-checked:bg-leaf-400/10 dark:has-checked:text-leaf-200">
                        <input type="radio" name="correct_answer" value="__INDEX__" data-repeater-value class="size-4 accent-leaf-600" required>
                        <span class="hidden sm:inline" aria-hidden="true">Correct</span>
                        <span class="sr-only">Choice <span data-repeater-letter></span> is correct</span>
                    </label>
                    <x-icon-button icon="trash-2" label="Remove choice" variant="danger" data-repeater-remove />
                </div>
            </template>
            <x-button variant="soft" size="sm" icon="plus" data-repeater-add data-js-only class="self-start">Add choice</x-button>
            <x-form.error :message="$errors->first('choices')" />
            <x-form.error :message="$errors->first('correct_answer')" />
        </fieldset>
    @elseif ($type === \App\Enums\QuestionType::TrueFalse)
        <x-form.radio-cards
            name="correct_answer"
            label="Correct answer"
            :value="$sameType ? $question->correct_answer : null"
            :options="[
                ['value' => '0', 'label' => 'True', 'description' => 'The statement is correct.', 'icon' => 'circle-check', 'color' => 'leaf'],
                ['value' => '1', 'label' => 'False', 'description' => 'The statement is wrong.', 'icon' => 'circle-x', 'color' => 'berry'],
            ]"
            columns="2"
            required
            class="col-span-full"
        />
    @else
        <x-form.textarea
            name="accepted_answers"
            label="Accepted answers"
            :value="$acceptedAnswers"
            rows="4"
            placeholder="Vitamin C"
            hint="One answer per line, up to 10. Letter case and extra spaces are ignored when answers are checked."
            required
            class="col-span-full"
        />
    @endif

    <x-form.textarea
        name="explanation"
        label="Explanation"
        :value="$question->explanation"
        :max-length="1000"
        rows="3"
        hint="Shown with the correct answer after learners submit, when the quiz reveals answers."
        optional
        class="col-span-full"
    />
</x-form.section>

<x-form.section title="Image and video" description="Add a picture, a short video or a YouTube or Vimeo link to the question. Learners see them above the answers." columns="2">
    <x-form.file
        name="image"
        label="Image"
        accept="image/jpeg,image/png,image/webp,image/gif"
        preview
        :current="$question->imageUrl()"
        current-label="Current image"
        remove-name="remove_image"
        remove-label="Remove the current image"
        :hint="'JPG, PNG, WebP or GIF, up to '.$mediaLimits['image'].'.'"
        optional
        class="col-span-full"
    />
    <x-form.file
        name="video"
        label="Video file"
        accept="video/mp4,video/webm"
        :current="$question->videoFileUrl()"
        current-label="Current video"
        remove-name="remove_video"
        remove-label="Remove the current video"
        :hint="'MP4 or WebM, up to '.$mediaLimits['video'].'.'"
        optional
        class="col-span-full"
    />
    <x-form.input
        name="video_url"
        type="url"
        label="Video link"
        :value="$question->video_url"
        prefix-icon="link"
        placeholder="https://www.youtube.com/watch?v=…"
        inputmode="url"
        maxlength="500"
        hint="YouTube and Vimeo links play inside the quiz; other links open in a new tab. Use either a video file or a link, not both."
        optional
        class="col-span-full"
    />
</x-form.section>
