<?php

namespace App\Services\Monitoring;

use App\Enums\MissionType;
use App\Enums\ProgressStatus;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Builds the teacher's mission progress pages.
 *
 * A learner can play a mission when they are enrolled in one of the classrooms in scope whose subject is the mission's
 * subject and whose grade matches the mission's grade (a mission without a grade is open to both grades). Counts are
 * grouped queries over distinct learners, so a learner in two matching classrooms counts once.
 */
final class MissionProgressReport
{
    /**
     * The number of missions per page.
     */
    public const PER_PAGE = 15;

    /**
     * The number of learners per page on a mission's page.
     */
    public const LEARNERS_PER_PAGE = 25;

    /**
     * Get one page of active missions: those of the classroom when one is given, otherwise those of the teacher's
     * assigned subjects. Missions are ordered by subject name, then their game order and title.
     *
     * @return LengthAwarePaginator<int, Mission>
     */
    public function missions(User $teacher, ?Classroom $classroom, ?MissionType $type, ?string $search): LengthAwarePaginator
    {
        return Mission::query()
            ->active()
            ->when(
                $classroom !== null,
                fn (Builder $query) => $query->where('missions.subject_id', $classroom->subject_id)->forGrade($classroom->grade_level),
                fn (Builder $query) => $query->whereIn('missions.subject_id', $teacher->assignedSubjectIds()),
            )
            ->when($type !== null, fn (Builder $query) => $query->where('missions.type', $type))
            ->when(filled($search), fn (Builder $query) => $query->whereLike('missions.title', '%'.trim((string) $search).'%'))
            ->with(['subject', 'module'])
            ->orderBy(Subject::query()->select('name')->whereColumn('subjects.id', 'missions.subject_id')->limit(1))
            ->orderBy('missions.sort_order')
            ->orderBy('missions.title')
            ->orderBy('missions.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Count eligible, completed and in-progress learners of each mission in the classrooms.
     *
     * @param  iterable<Mission>  $missions
     * @param  list<int>  $classroomIds
     * @return array<int, MissionCompletion> keyed by mission id (every given mission present)
     */
    public function completionFor(iterable $missions, array $classroomIds): array
    {
        $missionIds = collect($missions)->map(fn (Mission $mission): int => $mission->getKey())->values()->all();

        if ($missionIds === []) {
            return [];
        }

        $eligible = Mission::query()
            ->toBase()
            ->join('classrooms', 'classrooms.subject_id', '=', 'missions.subject_id')
            ->join('classroom_student', 'classroom_student.classroom_id', '=', 'classrooms.id')
            ->whereIn('missions.id', $missionIds)
            ->whereIn('classrooms.id', $classroomIds)
            ->where($this->gradeMatches(...))
            ->groupBy('missions.id')
            ->select('missions.id as mission_id')
            ->selectRaw('count(distinct classroom_student.student_id) as aggregate')
            ->pluck('aggregate', 'mission_id');

        $statuses = MissionProgress::query()
            ->toBase()
            ->join('missions', 'missions.id', '=', 'mission_progress.mission_id')
            ->join('classroom_student', 'classroom_student.student_id', '=', 'mission_progress.student_id')
            ->join('classrooms', 'classrooms.id', '=', 'classroom_student.classroom_id')
            ->whereIn('mission_progress.mission_id', $missionIds)
            ->whereIn('classrooms.id', $classroomIds)
            ->whereColumn('classrooms.subject_id', 'missions.subject_id')
            ->where($this->gradeMatches(...))
            ->whereIn('mission_progress.status', [ProgressStatus::Completed->value, ProgressStatus::InProgress->value])
            ->groupBy('mission_progress.mission_id', 'mission_progress.status')
            ->select(['mission_progress.mission_id', 'mission_progress.status'])
            ->selectRaw('count(distinct mission_progress.student_id) as aggregate')
            ->get();

        $completion = [];

        foreach ($missionIds as $missionId) {
            $missionStatuses = $statuses->filter(fn (object $row): bool => (int) $row->mission_id === $missionId);

            $completion[$missionId] = new MissionCompletion(
                eligible: (int) ($eligible[$missionId] ?? 0),
                completed: (int) $missionStatuses->where('status', ProgressStatus::Completed->value)->sum('aggregate'),
                inProgress: (int) $missionStatuses->where('status', ProgressStatus::InProgress->value)->sum('aggregate'),
            );
        }

        return $completion;
    }

    /**
     * Get each classroom's completion over the active missions its learners can play: eligible and completed
     * learner–mission pairs.
     *
     * @param  EloquentCollection<int, Classroom>  $classrooms  classrooms with students_count
     * @return list<array{classroom: Classroom, missions: int, completion: MissionCompletion}>
     */
    public function classroomCompletion(EloquentCollection $classrooms): array
    {
        if ($classrooms->isEmpty()) {
            return [];
        }

        $missions = Mission::query()
            ->active()
            ->whereIn('subject_id', $classrooms->pluck('subject_id')->unique()->values()->all())
            ->get(['id', 'subject_id', 'grade_level']);

        $pairs = MissionProgress::query()
            ->toBase()
            ->join('missions', 'missions.id', '=', 'mission_progress.mission_id')
            ->join('classroom_student', 'classroom_student.student_id', '=', 'mission_progress.student_id')
            ->join('classrooms', 'classrooms.id', '=', 'classroom_student.classroom_id')
            ->whereIn('classrooms.id', $classrooms->modelKeys())
            ->where('missions.is_active', true)
            ->whereColumn('classrooms.subject_id', 'missions.subject_id')
            ->where($this->gradeMatches(...))
            ->whereIn('mission_progress.status', [ProgressStatus::Completed->value, ProgressStatus::InProgress->value])
            ->groupBy('classroom_student.classroom_id', 'mission_progress.status')
            ->select(['classroom_student.classroom_id', 'mission_progress.status'])
            ->selectRaw('count(*) as aggregate')
            ->get();

        return $classrooms->map(function (Classroom $classroom) use ($missions, $pairs): array {
            $missionCount = $missions
                ->filter(fn (Mission $mission): bool => $this->isPlayableIn($mission, $classroom))
                ->count();
            $classroomPairs = $pairs->filter(fn (object $row): bool => (int) $row->classroom_id === $classroom->getKey());

            return [
                'classroom' => $classroom,
                'missions' => $missionCount,
                'completion' => new MissionCompletion(
                    eligible: $missionCount * (int) $classroom->students_count,
                    completed: (int) $classroomPairs->where('status', ProgressStatus::Completed->value)->sum('aggregate'),
                    inProgress: (int) $classroomPairs->where('status', ProgressStatus::InProgress->value)->sum('aggregate'),
                ),
            ];
        })->values()->all();
    }

    /**
     * Get the classrooms whose learners can play the mission.
     *
     * @param  EloquentCollection<int, Classroom>  $classrooms
     * @return EloquentCollection<int, Classroom>
     */
    public function eligibleClassrooms(Mission $mission, EloquentCollection $classrooms): EloquentCollection
    {
        return $classrooms->filter(fn (Classroom $classroom): bool => $this->isPlayableIn($mission, $classroom))->values();
    }

    /**
     * Get one page of the learners of the classrooms with their progress on the mission, optionally by status.
     *
     * @param  list<int>  $classroomIds  classrooms whose learners can play the mission
     * @return LengthAwarePaginator<int, User>
     */
    public function learners(Mission $mission, array $classroomIds, ?ProgressStatus $status): LengthAwarePaginator
    {
        $missionProgress = fn (Builder $query) => $query->where('mission_progress.mission_id', $mission->getKey());
        $started = [ProgressStatus::InProgress->value, ProgressStatus::Completed->value];

        return User::query()
            ->ofRole(UserRole::Student)
            ->whereHas('enrolledClassrooms', fn (Builder $query) => $query->whereIn('classrooms.id', $classroomIds))
            ->when(
                $status === ProgressStatus::NotStarted,
                fn (Builder $query) => $query->whereDoesntHave('missionProgress', fn (Builder $query) => $missionProgress($query)
                    ->whereIn('mission_progress.status', $started)),
            )
            ->when(
                $status !== null && $status !== ProgressStatus::NotStarted,
                fn (Builder $query) => $query->whereHas('missionProgress', fn (Builder $query) => $missionProgress($query)
                    ->where('mission_progress.status', $status)),
            )
            ->with([
                'studentProfile',
                'missionProgress' => fn (HasMany $query) => $query->where('mission_progress.mission_id', $mission->getKey()),
                'enrolledClassrooms' => fn (BelongsToMany $query) => $query
                    ->whereIn('classrooms.id', $classroomIds)
                    ->orderBy('classrooms.name')
                    ->orderBy('classrooms.id'),
            ])
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate(self::LEARNERS_PER_PAGE)
            ->withQueryString();
    }

    /**
     * Determine whether the classroom's learners can play the mission.
     */
    public function isPlayableIn(Mission $mission, Classroom $classroom): bool
    {
        return (int) $mission->subject_id === (int) $classroom->subject_id
            && ($mission->grade_level === null || $mission->grade_level === $classroom->grade_level);
    }

    /**
     * Match missions open to the classroom's grade (missions without a grade are open to both).
     */
    private function gradeMatches(QueryBuilder $query): void
    {
        $query->whereNull('missions.grade_level')->orWhereColumn('missions.grade_level', 'classrooms.grade_level');
    }
}
