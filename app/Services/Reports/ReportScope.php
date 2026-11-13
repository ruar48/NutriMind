<?php

namespace App\Services\Reports;

use App\Enums\GradeLevel;
use App\Models\Classroom;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Who a report is generated for and which classrooms it covers.
 *
 * Every classroom report is limited to a set of classrooms: all classrooms for administrators, the teacher's
 * accessible classrooms (own classrooms in subjects still assigned to them) for teachers. Without a classroom
 * filter only active (not archived) classrooms are covered; choosing an archived classroom in the classroom
 * filter reports on it. The subject, grade and (administrators only) teacher filters narrow the set further.
 */
final class ReportScope
{
    /**
     * The covered classrooms, loaded on first use.
     *
     * @var Collection<int, Classroom>|null
     */
    private ?Collection $classrooms = null;

    public function __construct(
        public readonly User $viewer,
        public readonly ReportFilters $filters,
    ) {}

    /**
     * Determine whether the report is generated for an administrator (the whole school).
     */
    public function forAdmin(): bool
    {
        return $this->viewer->isAdmin();
    }

    /**
     * Get the covered classrooms, ordered by name, with their subject loaded.
     *
     * @return Collection<int, Classroom>
     */
    public function classrooms(): Collection
    {
        return $this->classrooms ??= Classroom::query()
            ->when(! $this->forAdmin(), fn (EloquentBuilder $query) => $query->accessibleBy($this->viewer))
            ->when(
                $this->filters->classroomId,
                fn (EloquentBuilder $query, int $classroomId) => $query->whereKey($classroomId),
                fn (EloquentBuilder $query) => $query->active(),
            )
            ->when($this->filters->subjectId, fn (EloquentBuilder $query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when($this->filters->gradeLevel, fn (EloquentBuilder $query, GradeLevel $grade) => $query->where('grade_level', $grade))
            ->when($this->forAdmin() ? $this->filters->teacherId : null, fn (EloquentBuilder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->with('subject')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Get the ids of the covered classrooms.
     *
     * @return list<int>
     */
    public function classroomIds(): array
    {
        return $this->classrooms()->modelKeys();
    }

    /**
     * Start a query over the submitted quiz attempts made in the covered classrooms during the report period.
     */
    public function submittedAttempts(): QueryBuilder
    {
        $query = QuizAttempt::query()
            ->toBase()
            ->whereNotNull('quiz_attempts.submitted_at')
            ->whereIn('quiz_attempts.classroom_id', $this->classroomIds());

        $this->applyPeriod($query, 'quiz_attempts.submitted_at');

        return $query;
    }

    /**
     * Limit a query to the report period on the given timestamp column (no limit without a period).
     */
    public function applyPeriod(Builder $query, string $column): void
    {
        if ($this->filters->from !== null) {
            $query->where($column, '>=', $this->filters->from);
        }

        if ($this->filters->to !== null) {
            $query->where($column, '<=', $this->filters->to);
        }
    }

    /**
     * Describe the classrooms and filters a classroom report covers, label => value.
     *
     * @return array<string, string>
     */
    public function describeClassrooms(): array
    {
        $classroom = $this->filters->classroomId !== null ? $this->classrooms()->first() : null;

        $described = ['Classrooms' => match (true) {
            $classroom !== null => $classroom->name.($classroom->isArchived() ? ' (archived)' : ''),
            $this->forAdmin() => 'All active classrooms',
            default => 'Your active classrooms',
        }];

        if ($this->filters->subjectId !== null) {
            $described['Subject'] = (string) Subject::query()->whereKey($this->filters->subjectId)->value('name');
        }

        if ($this->filters->gradeLevel !== null) {
            $described['Grade'] = $this->filters->gradeLevel->label();
        }

        if ($this->forAdmin() && $this->filters->teacherId !== null) {
            $described['Teacher'] = (string) User::query()->whereKey($this->filters->teacherId)->value('name');
        }

        return $this->withPeriod($described);
    }

    /**
     * Describe the accounts the user accounts report covers, label => value.
     *
     * @return array<string, string>
     */
    public function describeAccounts(): array
    {
        $described = ['Accounts' => $this->filters->role?->label() ?? 'All roles'];

        if ($this->filters->status !== null) {
            $described['Status'] = ReportFilters::STATUSES[$this->filters->status];
        }

        return $this->withPeriod($described);
    }

    /**
     * Add the period to a description.
     *
     * @param  array<string, string>  $described
     * @return array<string, string>
     */
    private function withPeriod(array $described): array
    {
        $period = $this->filters->periodLabel();

        return $period === null ? $described : $described + ['Period' => $period];
    }
}
