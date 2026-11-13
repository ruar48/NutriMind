<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\IndexAnalyticsRequest;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Analytics\AnalyticsScope;
use App\Services\Analytics\LearningAnalytics;
use App\Services\Analytics\PeriodOptions;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    /**
     * Show the learning analytics of the teacher's classrooms for the chosen classroom, subject and period.
     *
     * Only the teacher's accessible classrooms and assigned subjects are ever in scope.
     */
    #[Authorize(Permission::ViewAnalytics)]
    public function __invoke(IndexAnalyticsRequest $request, LearningAnalytics $analytics, PeriodOptions $periods): View
    {
        $teacher = $request->user();
        $period = $request->period();
        $scope = AnalyticsScope::forTeacher($teacher, $period, $request->classroomId(), $request->subjectId());

        $classrooms = Classroom::query()
            ->accessibleBy($teacher)
            ->orderByRaw('CASE WHEN archived_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $subjects = $teacher->assignedSubjects()->orderBy('name')->orderBy('subjects.id')->get();

        $classroom = $classrooms->find($request->classroomId());
        $subject = $subjects->find($request->subjectId());

        return view('teacher.analytics.index', [
            'period' => $period,
            'scope' => $scope,
            'classroom' => $request->classroomId() === null ? null : $classroom,
            'subject' => $request->subjectId() === null ? null : $subject,
            'filters' => [
                'classroom' => $request->classroomId(),
                'subject' => $request->subjectId(),
                'period' => $period->key,
                'from' => $period->from->toDateString(),
                'to' => $period->to->toDateString(),
            ],
            'classroomOptions' => array_filter([
                'Active classrooms' => $classrooms->whereNull('archived_at')->mapWithKeys(fn (Classroom $option): array => [
                    $option->getKey() => $option->name.' · '.$option->grade_level->label(),
                ])->all(),
                'Archived classrooms' => $classrooms->whereNotNull('archived_at')->mapWithKeys(fn (Classroom $option): array => [
                    $option->getKey() => $option->name.' · '.$option->grade_level->label(),
                ])->all(),
            ]),
            'subjectOptions' => $subjects->pluck('name', 'id')->all(),
            'periodOptions' => $periods->options(),
            'hasClassrooms' => $classrooms->isNotEmpty(),
            'overview' => $analytics->overview($scope),
            'trend' => $analytics->weeklyTrend($scope),
            'distribution' => $analytics->scoreDistribution($scope),
            'bands' => $analytics->bandDistribution($scope),
            'subjectPerformance' => $analytics->subjectPerformance($scope),
            'classrooms' => $analytics->classroomRanking($scope, 10),
            'modules' => $analytics->moduleMastery($scope),
            'missedQuestions' => $analytics->mostMissedQuestions($scope),
            'atRisk' => $analytics->atRiskLearners($scope, 8),
            'leaderboard' => $analytics->leaderboard($scope),
            'weekdays' => $analytics->weekdayEngagement($scope),
            'completion' => $analytics->completion($scope),
            'syncHealth' => $analytics->syncHealth($scope),
            'learnerUrl' => fn (User $student): string => route('teacher.students.show', $student),
            'classroomUrl' => fn (Classroom $classroom): string => route('teacher.classrooms.show', $classroom),
            'itemAnalysisUrl' => fn (Quiz $quiz): ?string => $quiz->teacher()->is($teacher)
                ? route('teacher.quizzes.results.show', $quiz)
                : null,
        ]);
    }
}
