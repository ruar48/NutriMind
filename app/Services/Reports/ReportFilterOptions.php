<?php

namespace App\Services\Reports;

use App\Enums\GradeLevel;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The choices of a report's filter bar, limited to what the viewer may report on (the same scope the
 * FilterReportRequest validates against).
 */
class ReportFilterOptions
{
    /**
     * Get the filter choices of the report for the viewer.
     *
     * Classroom reports: classrooms (grouped "Active classrooms" / "Archived classrooms"), subjects, grades and,
     * for administrators, teachers. User accounts: roles and statuses.
     *
     * @return array{classrooms?: array<string, array<int, string>>, subjects?: array<int, string>, grades?: array<int, string>, teachers?: array<int, string>, roles?: array<string, string>, statuses?: array<string, string>}
     */
    public function for(ReportType $type, User $viewer): array
    {
        if ($type === ReportType::UserAccounts) {
            return [
                'roles' => UserRole::options(),
                'statuses' => ReportFilters::STATUSES,
            ];
        }

        return array_filter([
            'classrooms' => $this->classrooms($viewer),
            'subjects' => $this->subjects($viewer),
            'grades' => GradeLevel::options(),
            'teachers' => $viewer->isAdmin() ? $this->teachers() : null,
        ], fn (?array $options): bool => $options !== null);
    }

    /**
     * Get the classrooms the viewer may report on, active ones first.
     *
     * @return array<string, array<int, string>>
     */
    private function classrooms(User $viewer): array
    {
        $classrooms = Classroom::query()
            ->when(! $viewer->isAdmin(), fn (Builder $query) => $query->accessibleBy($viewer))
            ->with('schoolYear:id,name')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'school_year_id', 'archived_at']);

        [$archived, $active] = $classrooms->partition(fn (Classroom $classroom): bool => $classroom->isArchived());

        return array_filter([
            'Active classrooms' => $active->mapWithKeys(fn (Classroom $classroom): array => [$classroom->id => $classroom->name])->all(),
            'Archived classrooms' => $archived->mapWithKeys(fn (Classroom $classroom): array => [
                $classroom->id => $classroom->name.' ('.$classroom->schoolYear->name.')',
            ])->all(),
        ]);
    }

    /**
     * Get the subjects the viewer may report on: every subject for administrators, the assigned ones for teachers.
     *
     * @return array<int, string>
     */
    private function subjects(User $viewer): array
    {
        $subjects = $viewer->isAdmin() ? Subject::query() : $viewer->assignedSubjects()->getQuery();

        return $subjects->orderBy('subjects.name')->orderBy('subjects.id')->pluck('subjects.name', 'subjects.id')->all();
    }

    /**
     * Get every teacher account.
     *
     * @return array<int, string>
     */
    private function teachers(): array
    {
        return User::query()->ofRole(UserRole::Teacher)->orderBy('name')->orderBy('id')->pluck('name', 'id')->all();
    }
}
