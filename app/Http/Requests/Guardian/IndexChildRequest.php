<?php

namespace App\Http\Requests\Guardian;

use App\Enums\GradeLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The filters of the parent's "My children" list.
 *
 * Every value comes from the page's own filter form, so a value that breaks its rule (a tampered or outdated
 * link) is ignored instead of reported: the list is simply not filtered by it.
 */
class IndexChildRequest extends FormRequest
{
    /**
     * The longest search term the list accepts.
     */
    public const SEARCH_MAX_LENGTH = 100;

    /**
     * Determine if the user is authorized to make this request.
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
            'search' => ['nullable', 'string', 'max:'.self::SEARCH_MAX_LENGTH],
            'grade' => ['nullable', Rule::enum(GradeLevel::class)],
        ];
    }

    /**
     * Ignore invalid filter values instead of redirecting back with errors (see the class description).
     */
    protected function failedValidation(Validator $validator): void {}

    /**
     * Get the trimmed search term, or null when there is none.
     */
    public function searchTerm(): ?string
    {
        $search = $this->validFilter('search');

        return is_string($search) && trim($search) !== '' ? trim($search) : null;
    }

    /**
     * Get the grade level to show, or null for every grade.
     */
    public function gradeLevel(): ?GradeLevel
    {
        $grade = $this->validFilter('grade');

        return $grade === null ? null : GradeLevel::tryFrom((int) $grade);
    }

    /**
     * Get a filter value that passed its rule, or null.
     */
    private function validFilter(string $key): mixed
    {
        $value = $this->validator->valid()[$key] ?? null;

        return filled($value) ? $value : null;
    }
}
