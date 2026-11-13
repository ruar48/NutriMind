<?php

namespace App\Http\Requests\Teacher;

use App\Enums\LearningMode;
use App\Http\Requests\Teacher\Concerns\ValidatesClassroomSection;
use App\Models\Classroom;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClassroomRequest extends FormRequest
{
    use ValidatesClassroomSection;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request; the subject and grade level are not editable.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'section' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'room' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'learning_mode' => ['required', Rule::enum(LearningMode::class)],
        ];
    }

    /**
     * Get the "after" validation callables: the section must stay free in the classroom's school year.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('section')) {
                    return;
                }

                /** @var Classroom $classroom */
                $classroom = $this->route('classroom');
                $classroom->loadMissing('subject');
                $section = (string) $this->string('section');

                if ($this->sectionIsTaken($classroom->school_year_id, $classroom->subject_id, $classroom->grade_level, $section, $classroom->id)) {
                    $validator->errors()->add('section', $this->duplicateSectionMessage($classroom->subject, $classroom->grade_level, $section));
                }
            },
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
            'learning_mode' => 'learning mode',
        ];
    }
}
