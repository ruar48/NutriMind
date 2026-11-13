<?php

namespace App\Http\Requests\Reports;

use App\Enums\GradeLevel;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Reports\ReportFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * The filters of a report preview, CSV export or print view (administrator and teacher portals).
 *
 * Only the filters relevant to the report are read. Teachers may only choose their accessible classrooms
 * (archived ones included) and their assigned subjects; anything else is a validation error. Administrators may
 * also filter by teacher. A failed validation returns to the report preview without filters.
 */
class FilterReportRequest extends FormRequest
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
        $period = [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];

        if ($this->reportType() === ReportType::UserAccounts) {
            return [
                'role' => ['nullable', Rule::enum(UserRole::class)],
                'status' => ['nullable', Rule::in(array_keys(ReportFilters::STATUSES))],
                ...$period,
            ];
        }

        $viewer = $this->user();

        return [
            'classroom_id' => ['nullable', 'bail', 'integer', $this->classroomRule($viewer)],
            'subject_id' => ['nullable', 'bail', 'integer', $this->subjectRule($viewer)],
            'grade_level' => ['nullable', Rule::enum(GradeLevel::class)],
            ...($viewer->isAdmin() ? [
                'teacher_id' => ['nullable', 'bail', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Teacher->value)],
            ] : []),
            ...$period,
        ];
    }

    /**
     * Get the field names used in validation messages, matching the filter labels.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'classroom_id' => 'classroom',
            'subject_id' => 'subject',
            'grade_level' => 'grade level',
            'teacher_id' => 'teacher',
            'from' => 'start date',
            'to' => 'end date',
            'role' => 'role',
            'status' => 'status',
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.date_format' => 'Enter the start date as a valid date.',
            'to.date_format' => 'Enter the end date as a valid date.',
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }

    /**
     * Get the validated filters.
     */
    public function filters(): ReportFilters
    {
        return ReportFilters::fromValidated($this->validated());
    }

    /**
     * Get the report the request is for.
     */
    public function reportType(): ReportType
    {
        $report = $this->route('report');

        return $report instanceof ReportType ? $report : ReportType::from((string) $report);
    }

    /**
     * Return to the report preview of the same portal, without the rejected filters.
     */
    protected function getRedirectUrl(): string
    {
        $portal = Str::before((string) $this->route()?->getName(), '.reports.');

        return route($portal.'.reports.show', $this->reportType());
    }

    /**
     * The classrooms the viewer may filter by: any classroom for administrators, the teacher's accessible
     * classrooms (own classrooms in subjects still assigned to them, archived included) for teachers.
     */
    private function classroomRule(User $viewer): Exists
    {
        $rule = Rule::exists('classrooms', 'id');

        return $viewer->isAdmin()
            ? $rule
            : $rule->where('teacher_id', $viewer->getKey())->whereIn('subject_id', $viewer->assignedSubjectIds());
    }

    /**
     * The subjects the viewer may filter by: any subject for administrators, the assigned ones for teachers.
     */
    private function subjectRule(User $viewer): Exists
    {
        $rule = Rule::exists('subjects', 'id');

        return $viewer->isAdmin() ? $rule : $rule->whereIn('id', $viewer->assignedSubjectIds());
    }
}
