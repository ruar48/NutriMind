<?php

namespace App\Services\Analytics;

use App\Enums\GradeLevel;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a set of analytics figures covers: a period plus the classrooms, subjects and grade it is limited to.
 *
 * Learners in scope are active learner accounts of the grade (when set) that are enrolled in the scoped classrooms
 * (teacher view) or, school-wide with a subject filter, in a non-archived classroom of those subjects. Quiz results
 * must also come from the scoped classrooms and subjects; mission and activity progress from the scoped subjects.
 */
final readonly class AnalyticsScope
{
    /**
     * @param  list<int>|null  $classroomIds  Null covers every classroom (school-wide); a list, even an empty one, only those.
     * @param  list<int>|null  $subjectIds  Null covers every subject; a list, even an empty one, only those.
     */
    public function __construct(
        public AnalyticsPeriod $period,
        public ?array $classroomIds = null,
        public ?array $subjectIds = null,
        public ?GradeLevel $grade = null,
    ) {}

    /**
     * The whole school, optionally limited to one subject and one grade (the administrator's view).
     */
    public static function school(AnalyticsPeriod $period, ?int $subjectId = null, ?GradeLevel $grade = null): self
    {
        return new self($period, null, $subjectId === null ? null : [$subjectId], $grade);
    }

    /**
     * The teacher's classrooms (never anyone else's), optionally one classroom and one assigned subject.
     *
     * Without a classroom the scope covers the teacher's non-archived accessible classrooms; a given classroom is
     * kept only when it is accessible to the teacher (archived ones included), otherwise nothing is in scope.
     * Subjects are always limited to the teacher's assigned subjects. Runs one query (plus the teacher's subjects
     * when they are not loaded yet).
     */
    public static function forTeacher(User $teacher, AnalyticsPeriod $period, ?int $classroomId = null, ?int $subjectId = null): self
    {
        $assignedSubjectIds = $teacher->assignedSubjectIds();
        $subjectIds = $subjectId === null
            ? $assignedSubjectIds
            : array_values(array_intersect($assignedSubjectIds, [$subjectId]));

        $classroomIds = Classroom::query()
            ->accessibleBy($teacher)
            ->when(
                $classroomId === null,
                fn (Builder $query) => $query->active(),
                fn (Builder $query) => $query->whereKey($classroomId),
            )
            ->whereIn('subject_id', $subjectIds)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return new self($period, $classroomIds, $subjectIds);
    }

    /**
     * Get the same scope over another period.
     */
    public function withPeriod(AnalyticsPeriod $period): self
    {
        return new self($period, $this->classroomIds, $this->subjectIds, $this->grade);
    }

    /**
     * Get the same scope over the comparison period.
     */
    public function previous(): self
    {
        return $this->withPeriod($this->period->previous());
    }

    /**
     * Determine whether the scope covers the whole school rather than a set of classrooms.
     */
    public function isSchoolWide(): bool
    {
        return $this->classroomIds === null;
    }

    /**
     * Get a string that identifies which learners the scope covers (independent of the period).
     */
    public function populationKey(): string
    {
        return implode('|', [
            $this->classroomIds === null ? '*' : implode(',', $this->classroomIds),
            $this->subjectIds === null ? '*' : implode(',', $this->subjectIds),
            $this->grade?->value ?? '*',
        ]);
    }

    /**
     * Get a string that identifies the whole scope (used to memoize figures).
     */
    public function cacheKey(): string
    {
        return $this->populationKey().'|'.$this->period->cacheKey();
    }
}
