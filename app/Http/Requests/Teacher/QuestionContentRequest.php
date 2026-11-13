<?php

namespace App\Http\Requests\Teacher;

use App\Enums\QuestionType;
use App\Models\QuestionBankItem;
use App\Models\QuizQuestion;
use App\Services\QuestionMedia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validation of a question's content, shared by quiz questions and question bank items.
 *
 * Multiple choice questions send `choices[]` and the index of the correct one in `correct_answer`; true or false
 * questions send `correct_answer` 0 (True) or 1 (False); identification questions send `accepted_answers`, one
 * accepted answer per line. Media: `image`, `remove_image`, `video`, `remove_video` and `video_url`.
 */
abstract class QuestionContentRequest extends FormRequest
{
    /**
     * The message shown when a question would have both an uploaded video and a video link.
     */
    public const BOTH_VIDEOS_MESSAGE = 'Choose either an uploaded video or a video link.';

    /**
     * The fewest choices a multiple choice question may have.
     */
    public const MIN_CHOICES = 2;

    /**
     * The most choices a multiple choice question may have.
     */
    public const MAX_CHOICES = 6;

    /**
     * The most accepted answers an identification question may have.
     */
    public const MAX_ACCEPTED_ANSWERS = 10;

    /**
     * The longest accepted answer.
     */
    public const ACCEPTED_ANSWER_MAX_LENGTH = 100;

    /**
     * The room for every accepted answer together (the correct_answer column holds 500 characters).
     */
    public const ACCEPTED_ANSWERS_TOTAL_LENGTH = 500;

    /**
     * The choices of every true or false question.
     *
     * @var list<string>
     */
    public const TRUE_FALSE_CHOICES = ['True', 'False'];

    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization runs before validation through the controller's #[Authorize] attributes.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the question being edited, or null when a new one is created.
     */
    abstract protected function currentQuestion(): QuizQuestion|QuestionBankItem|null;

    /**
     * Get the question type sent in the form, when it is valid.
     */
    public function questionType(): ?QuestionType
    {
        return $this->enum('type', QuestionType::class);
    }

    /**
     * Collapse repeated whitespace in the choices so that "Go  foods" and "Go foods" count as the same choice.
     */
    protected function prepareForValidation(): void
    {
        $choices = $this->input('choices');

        if (is_array($choices)) {
            $this->merge([
                'choices' => array_map(fn (mixed $choice): mixed => is_string($choice) ? Str::squish($choice) : $choice, $choices),
            ]);
        }
    }

    /**
     * Get the validation rules of the question content.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $media = app(QuestionMedia::class);

        return [
            'type' => ['required', Rule::enum(QuestionType::class)],
            'prompt' => ['required', 'string', 'max:2000'],
            ...$this->answerRules(),
            'explanation' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', $media->imageRule()],
            'remove_image' => ['nullable', 'boolean'],
            'video' => ['nullable', $media->videoRule()],
            'remove_video' => ['nullable', 'boolean'],
            'video_url' => ['nullable', 'string', 'url:http,https', 'max:500'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $media = app(QuestionMedia::class);
        $imageTypes = 'The image must be a JPG, PNG, WebP or GIF file.';
        $videoTypes = 'The video must be an MP4 or WebM file.';
        $isTrueFalse = $this->questionType() === QuestionType::TrueFalse;

        return [
            'type.required' => 'Choose a question type.',
            'type.enum' => 'Choose a valid question type.',
            'choices.required' => 'Add the answer choices.',
            'choices.array' => 'Add the answer choices.',
            'choices.list' => 'Add the answer choices.',
            'choices.min' => 'Add at least '.self::MIN_CHOICES.' choices.',
            'choices.max' => 'Use at most '.self::MAX_CHOICES.' choices.',
            'choices.*.required' => 'Fill in :attribute or remove it.',
            'choices.*.string' => ':Attribute must be text.',
            'choices.*.max' => ':Attribute may not be longer than :max characters.',
            'choices.*.distinct' => ':Attribute repeats another choice.',
            'correct_answer.required' => $isTrueFalse ? 'Choose whether the statement is true or false.' : 'Mark the correct choice.',
            'correct_answer.integer' => $isTrueFalse ? 'Choose whether the statement is true or false.' : 'Mark one of the choices as correct.',
            'correct_answer.in' => $isTrueFalse ? 'Choose whether the statement is true or false.' : 'Mark one of the choices as correct.',
            'accepted_answers.required' => 'Add at least one accepted answer.',
            'image.file' => $imageTypes,
            'image.image' => $imageTypes,
            'image.mimes' => $imageTypes,
            'image.extensions' => $imageTypes,
            'image.max' => 'The image may not be larger than '.$media->imageLimitLabel().'.',
            'image.uploaded' => 'The image could not be uploaded. Choose a file up to '.$media->imageLimitLabel().'.',
            'video.file' => $videoTypes,
            'video.mimes' => $videoTypes,
            'video.extensions' => $videoTypes,
            'video.max' => 'The video may not be larger than '.$media->videoLimitLabel().'.',
            'video.uploaded' => 'The video could not be uploaded. Choose a file up to '.$media->videoLimitLabel().'.',
            'video_url.url' => 'Enter a full video link that starts with http:// or https://.',
        ];
    }

    /**
     * Get the field names used in validation messages, matching the form labels.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $choices = [];

        foreach (array_keys((array) $this->input('choices', [])) as $index) {
            $choices['choices.'.$index] = is_int($index) && $index < 26 ? 'choice '.chr(ord('A') + $index) : 'a choice';
        }

        return [
            'prompt' => 'question',
            'correct_answer' => 'correct answer',
            'accepted_answers' => 'accepted answers',
            'explanation' => 'explanation',
            'video_url' => 'video link',
            ...$choices,
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateSingleVideoSource($validator);

                if ($this->questionType() === QuestionType::Identification && ! $validator->errors()->has('accepted_answers')) {
                    $this->validateAcceptedAnswers($validator);
                }
            },
        ];
    }

    /**
     * Get the validated question content to save: type, prompt, choices, correct answer, explanation and video link.
     *
     * @return array{type: QuestionType, prompt: string, choices: list<string>|null, correct_answer: string, explanation: ?string, video_url: ?string}
     */
    public function questionAttributes(): array
    {
        $validated = $this->validated();
        $type = QuestionType::from($validated['type']);

        [$choices, $correctAnswer] = match ($type) {
            QuestionType::MultipleChoice => [
                array_values(array_map(fn (mixed $choice): string => Str::squish((string) $choice), $validated['choices'])),
                (string) (int) $validated['correct_answer'],
            ],
            QuestionType::TrueFalse => [self::TRUE_FALSE_CHOICES, (string) (int) $validated['correct_answer']],
            QuestionType::Identification => [null, implode('|', $this->acceptedAnswerLines())],
        };

        return [
            'type' => $type,
            'prompt' => $validated['prompt'],
            'choices' => $choices,
            'correct_answer' => $correctAnswer,
            'explanation' => $validated['explanation'] ?? null,
            'video_url' => $validated['video_url'] ?? null,
        ];
    }

    /**
     * Get the media changes for QuestionMedia::save().
     *
     * @return array{image: ?UploadedFile, remove_image: bool, video: ?UploadedFile, remove_video: bool}
     */
    public function mediaInput(): array
    {
        $image = $this->file('image');
        $video = $this->file('video');

        return [
            'image' => $image instanceof UploadedFile ? $image : null,
            'remove_image' => $this->boolean('remove_image'),
            'video' => $video instanceof UploadedFile ? $video : null,
            'remove_video' => $this->boolean('remove_video'),
        ];
    }

    /**
     * Get the accepted answers typed one per line: squished, without blank lines and case-insensitive repeats.
     *
     * @return list<string>
     */
    public function acceptedAnswerLines(): array
    {
        $lines = preg_split('/\R/u', (string) $this->input('accepted_answers', '')) ?: [];
        $answers = [];

        foreach ($lines as $line) {
            $answer = Str::squish($line);

            if ($answer !== '' && ! array_key_exists(Str::lower($answer), $answers)) {
                $answers[Str::lower($answer)] = $answer;
            }
        }

        return array_values($answers);
    }

    /**
     * Get the rules of the answer fields of the chosen question type.
     *
     * @return array<string, array<mixed>>
     */
    private function answerRules(): array
    {
        return match ($this->questionType()) {
            QuestionType::MultipleChoice => [
                'choices' => ['required', 'array', 'list', 'min:'.self::MIN_CHOICES, 'max:'.self::MAX_CHOICES],
                'choices.*' => ['required', 'string', 'max:300', 'distinct:ignore_case'],
                'correct_answer' => ['required', 'integer', Rule::in(is_array($this->input('choices')) ? array_keys($this->input('choices')) : [])],
            ],
            QuestionType::TrueFalse => [
                'correct_answer' => ['required', Rule::in(['0', '1'])],
            ],
            QuestionType::Identification => [
                'accepted_answers' => ['required', 'string'],
            ],
            null => [],
        };
    }

    /**
     * Reject a question that would end up with both an uploaded video and a video link.
     */
    private function validateSingleVideoSource(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['video', 'video_url']) || ! $this->filled('video_url')) {
            return;
        }

        $keepsCurrentVideo = filled($this->currentQuestion()?->video_path) && ! $this->boolean('remove_video');

        if ($this->hasFile('video') || $keepsCurrentVideo) {
            $validator->errors()->add('video', self::BOTH_VIDEOS_MESSAGE);
        }
    }

    /**
     * Check the identification answers: how many, how long, and that they fit the stored answer key.
     */
    private function validateAcceptedAnswers(Validator $validator): void
    {
        $answers = $this->acceptedAnswerLines();

        $message = match (true) {
            $answers === [] => 'Add at least one accepted answer.',
            count($answers) > self::MAX_ACCEPTED_ANSWERS => 'List at most '.self::MAX_ACCEPTED_ANSWERS.' accepted answers.',
            collect($answers)->contains(fn (string $answer): bool => mb_strlen($answer) > self::ACCEPTED_ANSWER_MAX_LENGTH) => 'Each accepted answer may not be longer than '.self::ACCEPTED_ANSWER_MAX_LENGTH.' characters.',
            collect($answers)->contains(fn (string $answer): bool => str_contains($answer, '|')) => 'Accepted answers cannot contain the | character.',
            mb_strlen(implode('|', $answers)) > self::ACCEPTED_ANSWERS_TOTAL_LENGTH => 'The accepted answers are too long together. Keep them under '.self::ACCEPTED_ANSWERS_TOTAL_LENGTH.' characters in total.',
            default => null,
        };

        if ($message !== null) {
            $validator->errors()->add('accepted_answers', $message);
        }
    }
}
