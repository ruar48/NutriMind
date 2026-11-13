<?php

namespace App\Services\Reports\Types;

use App\Enums\ProgressStatus;
use App\Enums\ReportType;
use App\Models\Classroom;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Subject;
use App\Services\Reports\ColumnType;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportRow;
use App\Services\Reports\ReportScope;
use App\Services\Reports\ReportStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mission progress: one row per mission of the covered classrooms' subjects and grades.
 *
 * Active missions are always listed; inactive ones only once learners made progress on them. A learner is eligible
 * for a mission when enrolled in a covered classroom of the mission's subject whose grade matches the mission's
 * grade (missions without a grade are for both grades). Only eligible learners' progress is counted: completions by
 * completion date and missions in progress by start date when a period is set.
 */
final class MissionProgressReport
{
    /**
     * Build the report for the scope.
     */
    public function build(ReportScope $scope): Report
    {
        $missions = $this->missions($scope->classrooms());
        $missionIds = $missions->modelKeys();
        $eligible = $this->eligibleLearners($scope, $missionIds);
        $completed = $this->progress($scope, $missionIds, ProgressStatus::Completed, 'mission_progress.completed_at');
        $inProgress = $this->progress($scope, $missionIds, ProgressStatus::InProgress, 'mission_progress.started_at');

        $rows = $missions->map(function (Mission $mission) use ($scope, $eligible, $completed, $inProgress): ReportRow {
            $eligibleLearners = (int) ($eligible[$mission->id] ?? 0);
            $completions = (int) ($completed->get($mission->id)?->learners ?? 0);

            return new ReportRow(
                cells: [
                    'mission' => $mission->title,
                    'subject' => $mission->subject,
                    'type' => $mission->type,
                    'eligible' => $eligibleLearners,
                    'completed' => $completions,
                    'in_progress' => (int) ($inProgress->get($mission->id)?->learners ?? 0),
                    'completion_rate' => $eligibleLearners === 0 ? null : round($completions / $eligibleLearners * 100, 1),
                    'points' => (int) ($completed->get($mission->id)?->points ?? 0) + (int) ($inProgress->get($mission->id)?->points ?? 0),
                ],
                url: $scope->forAdmin() ? null : route('teacher.missions.show', $mission),
                tags: $mission->is_active ? [] : [['label' => 'Inactive', 'color' => 'slate', 'icon' => 'eye-off']],
            );
        })->values();

        return new Report(
            type: ReportType::MissionProgress,
            title: ReportType::MissionProgress->label(),
            description: ReportType::MissionProgress->description(),
            filters: $scope->describeClassrooms(),
            summary: $this->summary($rows),
            columns: $this->columns(),
            rows: $rows,
            notes: array_values(array_filter([
                'Eligible learners are enrolled in a covered classroom of the mission’s subject and grade; only their progress is counted.',
                'Inactive missions are listed only when learners made progress on them.',
                $scope->filters->hasPeriod() ? 'Completions are counted by completion date and missions in progress by start date.' : null,
            ])),
            breakdown: $this->breakdown($scope, $rows),
        );
    }

    /**
     * Get the missions of the classrooms' subjects and grades, ordered by subject name, then position.
     *
     * @param  EloquentCollection<int, Classroom>  $classrooms
     * @return EloquentCollection<int, Mission>
     */
    private function missions(EloquentCollection $classrooms): EloquentCollection
    {
        $subjectIds = $classrooms->pluck('subject_id')->unique()->values()->all();
        $grades = $classrooms->map(fn (Classroom $classroom): int => $classroom->grade_level->value)->unique()->values()->all();

        return Mission::query()
            ->whereIn('subject_id', $subjectIds)
            ->where(fn (Builder $query) => $query->whereNull('grade_level')->orWhereIn('grade_level', $grades))
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereHas('progress'))
            ->with('subject')
            ->orderBy(Subject::query()->select('name')->whereColumn('subjects.id', 'missions.subject_id'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Count the distinct eligible learners of each mission, keyed by mission id.
     *
     * @param  list<int>  $missionIds
     * @return Collection<int|string, int|string>
     */
    private function eligibleLearners(ReportScope $scope, array $missionIds): Collection
    {
        return DB::table('missions')
            ->join('classrooms', 'classrooms.subject_id', '=', 'missions.subject_id')
            ->join('classroom_student', 'classroom_student.classroom_id', '=', 'classrooms.id')
            ->whereIn('missions.id', $missionIds)
            ->whereIn('classrooms.id', $scope->classroomIds())
            ->where(fn (QueryBuilder $query) => $query->whereNull('missions.grade_level')->orWhereColumn('missions.grade_level', 'classrooms.grade_level'))
            ->groupBy('missions.id')
            ->selectRaw('missions.id as mission_id, COUNT(DISTINCT classroom_student.student_id) as learners')
            ->pluck('learners', 'mission_id');
    }

    /**
     * Count the eligible learners with the given progress status on each mission and sum their points, keyed by
     * mission id; the period applies to the given timestamp column.
     *
     * @param  list<int>  $missionIds
     * @return Collection<int|string, object{mission_id: int, learners: int|string, points: int|string|null}>
     */
    private function progress(ReportScope $scope, array $missionIds, ProgressStatus $status, string $periodColumn): Collection
    {
        $classroomIds = $scope->classroomIds();

        $query = MissionProgress::query()
            ->toBase()
            ->join('missions', 'missions.id', '=', 'mission_progress.mission_id')
            ->whereIn('mission_progress.mission_id', $missionIds)
            ->where('mission_progress.status', $status->value)
            ->whereExists(fn (QueryBuilder $enrollment) => $enrollment
                ->selectRaw('1')
                ->from('classroom_student')
                ->join('classrooms', 'classrooms.id', '=', 'classroom_student.classroom_id')
                ->whereColumn('classroom_student.student_id', 'mission_progress.student_id')
                ->whereColumn('classrooms.subject_id', 'missions.subject_id')
                ->where(fn (QueryBuilder $grade) => $grade->whereNull('missions.grade_level')->orWhereColumn('missions.grade_level', 'classrooms.grade_level'))
                ->whereIn('classrooms.id', $classroomIds))
            ->groupBy('mission_progress.mission_id')
            ->selectRaw('mission_progress.mission_id as mission_id, COUNT(*) as learners, SUM(mission_progress.points_earned) as points');

        $scope->applyPeriod($query, $periodColumn);

        return $query->get()->keyBy('mission_id');
    }

    /**
     * Build the summary tiles.
     *
     * @param  Collection<int, ReportRow>  $rows
     * @return list<ReportStat>
     */
    private function summary(Collection $rows): array
    {
        $eligible = (int) $rows->sum(fn (ReportRow $row): int => $row->value('eligible'));
        $completed = (int) $rows->sum(fn (ReportRow $row): int => $row->value('completed'));
        $inProgress = (int) $rows->sum(fn (ReportRow $row): int => $row->value('in_progress'));

        return [
            ReportStat::count('Missions', $rows->count(), 'flag', 'carrot', 'Missions of the covered subjects and grades'),
            ReportStat::count('Completions', $completed, 'circle-check', 'leaf', trans_choice(':count learner still in progress|:count learners still in progress', $inProgress)),
            ReportStat::percent('Completion rate', $eligible === 0 ? null : round($completed / $eligible * 100, 1), 'gauge', 'sky', $eligible === 0 ? 'No eligible learners yet' : 'Of every eligible learner and mission'),
            ReportStat::count('Points awarded', (int) $rows->sum(fn (ReportRow $row): int => $row->value('points')), 'sparkles', 'sun', 'XP earned on these missions'),
        ];
    }

    /**
     * Build the progress distribution over every eligible learner and mission.
     *
     * @param  Collection<int, ReportRow>  $rows
     * @return array{title: string, description: string, segments: list<array{label: string, value: int, color: string}>}
     */
    private function breakdown(ReportScope $scope, Collection $rows): array
    {
        $completed = (int) $rows->sum(fn (ReportRow $row): int => $row->value('completed'));
        $inProgress = (int) $rows->sum(fn (ReportRow $row): int => $row->value('in_progress'));
        $none = (int) $rows->sum(fn (ReportRow $row): int => max(0, $row->value('eligible') - $row->value('completed') - $row->value('in_progress')));

        return [
            'title' => 'Learner progress',
            'description' => 'Every eligible learner on every listed mission',
            'segments' => [
                ['label' => ProgressStatus::Completed->label(), 'value' => $completed, 'color' => ProgressStatus::Completed->color()],
                ['label' => ProgressStatus::InProgress->label(), 'value' => $inProgress, 'color' => ProgressStatus::InProgress->color()],
                ['label' => $scope->filters->hasPeriod() ? 'No progress in the period' : ProgressStatus::NotStarted->label(), 'value' => $none, 'color' => ProgressStatus::NotStarted->color()],
            ],
        ];
    }

    /**
     * Get the report columns.
     *
     * @return list<ReportColumn>
     */
    private function columns(): array
    {
        return [
            new ReportColumn('mission', 'Mission'),
            new ReportColumn('subject', 'Subject', ColumnType::Subject),
            new ReportColumn('type', 'Type', ColumnType::Badge),
            new ReportColumn('eligible', 'Eligible learners', ColumnType::Number),
            new ReportColumn('completed', 'Completed', ColumnType::Number),
            new ReportColumn('in_progress', 'In progress', ColumnType::Number),
            new ReportColumn('completion_rate', 'Completion rate', ColumnType::Rate),
            new ReportColumn('points', 'Points awarded', ColumnType::Number),
        ];
    }
}
