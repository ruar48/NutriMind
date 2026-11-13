<?php

namespace App\Http\Requests\Admin;

use App\Services\StudentImport\ImportColumns;
use App\Services\StudentImport\StudentImports;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStudentImportRequest extends FormRequest
{
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
            'file' => ImportColumns::fileRules(),
            'classroom_id' => ['nullable', 'integer', Rule::exists('classrooms', 'id')->whereNull('archived_at')],
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
            ...ImportColumns::fileMessages(),
            'classroom_id.integer' => 'Choose a classroom from the list.',
            'classroom_id.exists' => 'Choose a classroom that is not archived.',
        ];
    }

    /**
     * Check the file's columns and row count once the basic rules pass.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(StudentImports $imports): array
    {
        return [
            function (Validator $validator) use ($imports): void {
                if ($validator->errors()->hasAny(['file', 'classroom_id'])) {
                    return;
                }

                foreach ($imports->problemsWith($this->file('file'), $this->filled('classroom_id')) as $problem) {
                    $validator->errors()->add('file', $problem);
                }
            },
        ];
    }
}
