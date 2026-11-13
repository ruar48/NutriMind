<?php

namespace App\Http\Requests\Admin;

use App\Enums\GuardianRelationship;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\ErrorBag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[ErrorBag('linkLearner')]
class StoreGuardianLinkRequest extends FormRequest
{
    /**
     * The message shown when the learner is already linked to the parent.
     */
    public const ALREADY_LINKED_MESSAGE = 'Already linked.';

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
            'student_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Student->value)],
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('student_id')) {
                    return;
                }

                /** @var User $guardian */
                $guardian = $this->route('guardian');

                if ($guardian->linkedStudents()->whereKey($this->integer('student_id'))->exists()) {
                    $validator->errors()->add('student_id', self::ALREADY_LINKED_MESSAGE);
                }
            },
        ];
    }

    /**
     * Get the messages that read better than the defaults.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Choose a learner to link.',
            'student_id.integer' => 'Choose a learner to link.',
            'student_id.exists' => 'Choose a learner to link.',
            'relationship.required' => 'Choose how the parent is related to the learner.',
            'relationship.enum' => 'Choose how the parent is related to the learner.',
        ];
    }
}
