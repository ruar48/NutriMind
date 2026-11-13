<?php

namespace App\Http\Requests\Admin;

use App\Models\Subject;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSubjectAssignmentRequest extends FormRequest
{
    /**
     * Get the error bag of one teacher's assignment form, so only that form shows errors and old input.
     */
    public static function errorBagFor(User $teacher): string
    {
        return 'assignments'.$teacher->getKey();
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Send the errors to the teacher's own error bag.
     */
    protected function prepareForValidation(): void
    {
        $this->errorBag = self::errorBagFor($this->teacher());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'distinct', 'exists:subjects,id'],
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
            'subject_ids.*.exists' => 'One of the selected subjects no longer exists. Reload the page and try again.',
            'subject_ids.*.distinct' => 'A subject was selected twice.',
        ];
    }

    /**
     * Get the field names used in validation messages, matching the form labels.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'subject_ids' => 'subjects',
            'subject_ids.*' => 'subject',
        ];
    }

    /**
     * Refuse inactive subjects, except ones the teacher already has (those may stay or be revoked).
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $requestedIds = array_map('intval', (array) $this->input('subject_ids', []));
                $assignedIds = $this->teacher()->assignedSubjects()->pluck('subjects.id')->map(fn (mixed $id): int => (int) $id)->all();

                Subject::query()
                    ->whereKey($requestedIds)
                    ->where('is_active', false)
                    ->whereKeyNot($assignedIds)
                    ->orderBy('name')
                    ->get()
                    ->each(fn (Subject $subject) => $validator->errors()->add(
                        'subject_ids',
                        "{$subject->name} is inactive and can't be assigned. Activate it first.",
                    ));
            },
        ];
    }

    /**
     * Return to the teacher's card on the assignment page after a failed validation.
     */
    protected function getRedirectUrl(): string
    {
        return parent::getRedirectUrl().'#teacher-'.$this->teacher()->getKey();
    }

    /**
     * Get the teacher whose subjects are being changed (the route's teacher).
     */
    private function teacher(): User
    {
        return $this->route('teacher');
    }
}
