<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademicTerm;
use App\Models\SchoolYear;
use App\Services\Setup\AcademicCalendar;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The fields shared by the add and edit quarter forms.
 */
abstract class AcademicTermFormRequest extends FormRequest
{
    /**
     * Get the school year the quarter belongs to.
     */
    abstract protected function schoolYear(): SchoolYear;

    /**
     * Get the quarter being edited, or null while adding one.
     */
    abstract protected function term(): ?AcademicTerm;

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
        $schoolYearId = $this->schoolYear()->getKey();

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('academic_terms', 'name')->where('school_year_id', $schoolYearId)->ignore($this->term()),
            ],
            'sequence' => [
                'required',
                'integer',
                'between:1,'.AcademicCalendar::MAX_TERMS,
                Rule::unique('academic_terms', 'sequence')->where('school_year_id', $schoolYearId)->ignore($this->term()),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $schoolYearName = $this->schoolYear()->name;

        return [
            'name.unique' => "Another quarter of {$schoolYearName} already has this name.",
            'sequence.unique' => "Another quarter of {$schoolYearName} is already number :input.",
            'ends_on.after' => 'The end date must be after the start date.',
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
            'name' => 'quarter name',
            'sequence' => 'quarter number',
            'starts_on' => 'start date',
            'ends_on' => 'end date',
        ];
    }

    /**
     * Get the checks that keep the quarter inside its school year and apart from the other quarters.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['starts_on', 'ends_on'])) {
                    return;
                }

                $schoolYear = $this->schoolYear();
                $startsOn = Carbon::parse($this->string('starts_on')->toString())->startOfDay();
                $endsOn = Carbon::parse($this->string('ends_on')->toString())->startOfDay();
                $outsideYear = false;

                if ($startsOn->format('Y-m-d') < $schoolYear->starts_on->format('Y-m-d')) {
                    $validator->errors()->add('starts_on', "The start date must be on or after {$schoolYear->starts_on->format('M j, Y')}, when {$schoolYear->name} begins.");
                    $outsideYear = true;
                }

                if ($endsOn->format('Y-m-d') > $schoolYear->ends_on->format('Y-m-d')) {
                    $validator->errors()->add('ends_on', "The end date must be on or before {$schoolYear->ends_on->format('M j, Y')}, when {$schoolYear->name} ends.");
                    $outsideYear = true;
                }

                if ($outsideYear) {
                    return;
                }

                $overlapping = app(AcademicCalendar::class)->overlappingTerm($schoolYear, $startsOn, $endsOn, $this->term()?->getKey());

                if ($overlapping !== null) {
                    $validator->errors()->add('starts_on', "These dates overlap {$overlapping->name} ({$overlapping->starts_on->format('M j, Y')} – {$overlapping->ends_on->format('M j, Y')}).");
                }
            },
        ];
    }
}
