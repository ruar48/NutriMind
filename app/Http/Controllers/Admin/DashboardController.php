<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsPeriod;
use App\Services\Analytics\AnalyticsScope;
use App\Services\Analytics\Greeting;
use App\Services\Analytics\LearningAnalytics;
use App\Services\Analytics\PeriodOptions;
use App\Services\Analytics\SchoolOverview;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the administrator's school-wide dashboard: setup checklist, head counts, the last 30 days of learning
     * and the queues that need an administrator.
     */
    #[Authorize(Permission::ViewAnalytics)]
    public function __invoke(
        Request $request,
        LearningAnalytics $analytics,
        SchoolOverview $school,
        PeriodOptions $periods,
        Settings $settings,
    ): View {
        $month = AnalyticsScope::school(AnalyticsPeriod::lastDays(30));
        $week = AnalyticsScope::school(AnalyticsPeriod::lastDays(7));
        $currentTerm = $periods->currentTerm();
        $headcounts = $school->headcounts();

        return view('admin.dashboard.index', [
            'greeting' => Greeting::for($request->user()->name),
            'schoolName' => (string) $settings->get('school_name'),
            'currentTerm' => $currentTerm,
            'headcounts' => $headcounts,
            'checklist' => $school->setupChecklist($headcounts, $currentTerm),
            'month' => $month,
            'monthOverview' => $analytics->overview($month),
            'weekOverview' => $analytics->overview($week),
            'dailyAttempts' => $analytics->dailyAttempts($week),
            'completion' => $analytics->completion($month),
            'atRisk' => $analytics->atRiskLearners($month, 0),
            'engagement' => $analytics->weeklyTrend(AnalyticsScope::school(AnalyticsPeriod::lastWeeks(8))),
            'subjects' => $analytics->subjectPerformance($month),
            'bands' => $analytics->bandDistribution($month),
            'topClassrooms' => $analytics->classroomRanking($month, 5),
            'syncHealth' => $analytics->syncHealth($month),
            'recentSignIns' => $school->recentSignIns(),
            'moderation' => $school->moderationQueue(),
        ]);
    }
}
