<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\LearnerFeedback;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Analytics\AnalyticsPeriod;
use App\Services\Analytics\AnalyticsScope;
use App\Services\Analytics\Greeting;
use App\Services\Analytics\LearningAnalytics;
use App\Services\Analytics\PeriodOptions;
use App\Services\Analytics\TeacherOverview;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the teacher's dashboard: their classrooms, what is due, who needs attention and the latest results.
     *
     * Every figure is limited to the teacher's non-archived classrooms in subjects still assigned to them.
     */
    #[Authorize('viewAny', Classroom::class)]
    public function __invoke(Request $request, LearningAnalytics $analytics, TeacherOverview $overview, PeriodOptions $periods): View
    {
        $teacher = $request->user();
        $classrooms = $overview->classrooms($teacher);
        $classroomIds = $classrooms->modelKeys();
        $month = AnalyticsScope::forTeacher($teacher, AnalyticsPeriod::lastDays(30));
        $week = $month->withPeriod(AnalyticsPeriod::thisWeek());
        $completion = $overview->quizCompletion($classroomIds);

        return view('teacher.dashboard.index', [
            'greeting' => Greeting::for($teacher->name),
            'currentTerm' => $periods->currentTerm(),
            'hasSubjects' => $teacher->assignedSubjectIds() !== [],
            'classrooms' => $classrooms,
            'completion' => $completion,
            'completionTotals' => [
                'assigned' => (int) $completion->sum('assigned'),
                'submitted' => (int) $completion->sum('submitted'),
            ],
            'classroomScores' => collect($analytics->classroomRanking($month))
                ->mapWithKeys(fn (array $row): array => [$row['classroom']->getKey() => $row['averageScore']]),
            'monthOverview' => $analytics->overview($month),
            'weekOverview' => $analytics->overview($week),
            'monthTrend' => $analytics->weeklyTrend($month),
            'quizzesDueThisWeek' => $overview->quizzesDueThisWeek($classroomIds),
            'dueSoon' => $overview->dueSoon($classrooms),
            'atRisk' => $analytics->atRiskLearners($month, 5),
            'recentSubmissions' => $overview->recentSubmissions($classroomIds),
            'leaderboard' => $analytics->leaderboard($month),
            'learnerUrl' => fn (User $student): string => route('teacher.students.show', $student),
            'attemptUrl' => fn (QuizAttempt $attempt): ?string => $attempt->quiz->teacher()->is($teacher)
                ? route('teacher.attempts.show', $attempt)
                : null,
            'quizUrl' => fn (Quiz $quiz): ?string => $quiz->teacher()->is($teacher)
                ? route('teacher.quizzes.show', $quiz)
                : null,
            'can' => [
                'createQuiz' => $teacher->can('create', Quiz::class),
                'createClassroom' => $teacher->can('create', Classroom::class),
                'viewAnalytics' => $teacher->hasPermission(Permission::ViewAnalytics),
                'useQuestionBank' => $teacher->can('viewAny', QuestionBankItem::class),
                'giveFeedback' => $teacher->can('create', LearnerFeedback::class),
            ],
        ]);
    }
}
