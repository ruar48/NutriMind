<?php

namespace App\Http\Requests\Teacher;

use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * Validates the question bank items chosen to be copied into a draft quiz.
 *
 * Every item must be visible to the teacher (own or shared, in a subject they teach), match the quiz's subject and
 * grade, and not be in the quiz already.
 */
class StoreQuizBankQuestionRequest extends FormRequest
{
    /**
     * The most items added in one go.
     */
    public const MAX_ITEMS = 50;

    /**
     * The message shown when an item is not visible to the teacher or does not match the quiz.
     */
    public const UNAVAILABLE_MESSAGE = 'Some of the selected questions are not available for this quiz. Refresh the list and try again.';

    /**
     * The message shown when an item was already copied into the quiz.
     */
    public const ALREADY_ADDED_MESSAGE = 'Some of the selected questions are already in this quiz.';

    /**
     * Memoized selectable items, keyed by id.
     *
     * @var Collection<int, QuestionBankItem>|null
     */
    private ?Collection $selectableItems = null;

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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'item_ids' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_ITEMS],
            'item_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_ids.required' => 'Select at least one question to add.',
            'item_ids.min' => 'Select at least one question to add.',
            'item_ids.array' => 'Select the questions from the list.',
            'item_ids.list' => 'Select the questions from the list.',
            'item_ids.max' => 'Add at most '.self::MAX_ITEMS.' questions at a time.',
            'item_ids.*.integer' => 'Select the questions from the list.',
            'item_ids.*.distinct' => 'Select each question only once.',
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
                if ($validator->errors()->has('item_ids') || $validator->errors()->has('item_ids.*')) {
                    return;
                }

                $ids = $this->submittedIds();

                if ($this->selectableItems($ids)->count() !== count($ids)) {
                    $validator->errors()->add('item_ids', self::UNAVAILABLE_MESSAGE);
                } elseif ($this->quiz()->questions()->reorder()->whereIn('question_bank_item_id', $ids)->exists()) {
                    $validator->errors()->add('item_ids', self::ALREADY_ADDED_MESSAGE);
                }
            },
        ];
    }

    /**
     * Get the validated items in the order they were selected.
     *
     * @return list<QuestionBankItem>
     */
    public function items(): array
    {
        $ids = array_map(fn (mixed $id): int => (int) $id, $this->validated('item_ids'));
        $items = $this->selectableItems($ids);

        return array_values(array_map(fn (int $id): QuestionBankItem => $items->get($id), $ids));
    }

    /**
     * Get the quiz the questions are added to.
     */
    public function quiz(): Quiz
    {
        return $this->route('quiz');
    }

    /**
     * Get the submitted item ids (read by the after hook once they passed their rules).
     *
     * @return list<int>
     */
    private function submittedIds(): array
    {
        return array_values(array_map(fn (mixed $id): int => (int) $id, Arr::wrap($this->input('item_ids', []))));
    }

    /**
     * Get the given items the teacher may add to the quiz, keyed by id.
     *
     * @param  list<int>  $ids
     * @return Collection<int, QuestionBankItem>
     */
    private function selectableItems(array $ids): Collection
    {
        /** @var User $teacher */
        $teacher = $this->user();
        $quiz = $this->quiz();

        return $this->selectableItems ??= QuestionBankItem::query()
            ->visibleTo($teacher)
            ->where('subject_id', $quiz->subject_id)
            ->where('grade_level', $quiz->grade_level)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }
}
