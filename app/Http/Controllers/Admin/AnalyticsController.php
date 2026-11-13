<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAnalyticsRequest;
use App\Models\Subject;
use App\Models\User;
use App\Services\Analytics\AnalyticsScope;
use App\Services\Analytics\LearningAnalytics;
use App\Services\Analytics\PeriodOptions;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    /**
     * Show the school-level learning analytics for the chosen grade, subject and period.
     */
    #[Authorize(Permission::ViewAnalytics)]
    public function __invoke(IndexAnalyticsRequest $request, LearningAnalytics $analytics, PeriodOptions $periods): View
    {
        $period = $request->period();
        $grade = $request->grade();
        $subjects = Subject::query()->orderBy('name')->orderBy('id')->get();
        $subject = $request->subjectId() === null ? null : $subjects->find($request->subjectId());
        $scope = AnalyticsScope::school($period, $subject?->getKey(), $grade);

        return view('admin.analytics.index', [
            'period' => $period,
            'grade' => $grade,
            'subject' => $subject,
            'filters' => [
                'grade' => $grade?->value,
                'subject' => $subject?->getKey(),
                'period' => $period->key,
                'from' => $period->from->toDateString(),
                'to' => $period->to->toDateString(),
            ],
            'gradeOptions' => GradeLevel::options(),
            'subjectOptions' => $subjects->mapWithKeys(fn (Subject $option): array => [
                $option->getKey() => $option->is_active ? $option->name : $option->name.' (inactive)',
            ])->all(),
            'periodOptions' => $periods->options(),
            'overview' => $analytics->overview($scope),
            'trend' => $analytics->weeklyTrend($scope),
            'distribution' => $analytics->scoreDistribution($scope),
            'bands' => $analytics->bandDistribution($scope),
            'subjectPerformance' => $analytics->subjectPerformance($scope),
            'gradePerformance' => $analytics->gradePerformance($scope),
            'classrooms' => $analytics->classroomRanking($scope, 10),
            'modules' => $analytics->moduleMastery($scope),
            'missedQuestions' => $analytics->mostMissedQuestions($scope),
            'atRisk' => $analytics->atRiskLearners($scope, 8),
            'learnerUrl' => fn (User $student): string => route('admin.students.show', $student),
            'leaderboard' => $analytics->leaderboard($scope),
            'weekdays' => $analytics->weekdayEngagement($scope),
            'completion' => $analytics->completion($scope),
            'syncHealth' => $analytics->syncHealth($scope),
            'teachers' => $analytics->teacherActivity($scope),
        ]);
    }
}
