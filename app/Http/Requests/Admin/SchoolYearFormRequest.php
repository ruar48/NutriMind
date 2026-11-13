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
 * The fields shared by the create and edit school year forms.
 */
abstract class SchoolYearFormRequest extends FormRequest
{
    /**
     * Get the school year being edited, or null while creating one.
     */
    abstract protected function schoolYear(): ?SchoolYear;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the name: squish whitespace and write a year span with an en dash ("2027-2028" becomes "2027–2028").
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (! is_string($name)) {
            return;
        }

        $name = (string) preg_replace('/\s+/u', ' ', trim($name));

        $this->merge([
            'name' => (string) preg_replace('/^(\d{4})\s*[-–—]\s*(\d{4})$/u', '$1–$2', $name),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('school_years', 'name')->ignore($this->schoolYear())],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'make_active' => ['nullable', 'boolean'],
            'create_quarters' => ['nullable', 'boolean'],
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
            'name.unique' => 'Another school year already uses this name.',
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
            'name' => 'school year name',
            'starts_on' => 'start date',
            'ends_on' => 'end date',
            'make_active' => 'make active',
            'create_quarters' => 'create quarters',
        ];
    }

    /**
     * Get the checks that compare the dates with the other school years and with this year's quarters.
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

                $startsOn = Carbon::parse($this->string('starts_on')->toString())->startOfDay();
                $endsOn = Carbon::parse($this->string('ends_on')->toString())->startOfDay();

                $this->checkOverlap($validator, $startsOn, $endsOn);
                $this->checkQuarters($validator, $startsOn, $endsOn);
            },
        ];
    }

    /**
     * Refuse dates that overlap another school year.
     */
    private function checkOverlap(Validator $validator, Carbon $startsOn, Carbon $endsOn): void
    {
        $overlapping = app(AcademicCalendar::class)->overlappingSchoolYear($startsOn, $endsOn, $this->schoolYear()?->getKey());

        if ($overlapping === null) {
            return;
        }

        $field = $overlapping->starts_on->greaterThan($startsOn) ? 'ends_on' : 'starts_on';

        $validator->errors()->add($field, "This school year overlaps {$overlapping->name}.");
    }

    /**
     * Keep the existing quarters inside the new dates, and make sure a new set of quarters fits.
     */
    private function checkQuarters(Validator $validator, Carbon $startsOn, Carbon $endsOn): void
    {
        $terms = $this->schoolYear()?->terms()->get() ?? collect();

        if ($this->boolean('create_quarters')) {
            if ($terms->isNotEmpty()) {
                $validator->errors()->add('create_quarters', 'This school year already has quarters. Add or edit them on the school year page.');
            } elseif ((int) $startsOn->diffInDays($endsOn) + 1 < AcademicCalendar::QUARTERS_PER_YEAR) {
                $validator->errors()->add('ends_on', 'A school year must be at least 4 days long to split it into quarters.');
            }
        }

        $startingEarlier = $terms->filter(fn (AcademicTerm $term): bool => $term->starts_on->format('Y-m-d') < $startsOn->format('Y-m-d'));
        $endingLater = $terms->filter(fn (AcademicTerm $term): bool => $term->ends_on->format('Y-m-d') > $endsOn->format('Y-m-d'));

        if ($startingEarlier->isNotEmpty()) {
            $validator->errors()->add('starts_on', 'These quarters start before the new start date: '.$startingEarlier->pluck('name')->join(', ').'. Edit or delete them first.');
        }

        if ($endingLater->isNotEmpty()) {
            $validator->errors()->add('ends_on', 'These quarters end after the new end date: '.$endingLater->pluck('name')->join(', ').'. Edit or delete them first.');
        }
    }
}
