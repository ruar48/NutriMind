<?php

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Models\User;
use App\Services\Reports\Types\ClassroomSummaryReport;
use App\Services\Reports\Types\MissionProgressReport;
use App\Services\Reports\Types\QuizResultsReport;
use App\Services\Reports\Types\StudentPerformanceReport;
use App\Services\Reports\Types\UserAccountsReport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Gate;

/**
 * Generates every ReportType as a Report {title, description, filters, summary, columns, rows}.
 *
 * Administrators get the whole school; teachers get their accessible classrooms only (see ReportScope). The viewer
 * is authorized again here (ReportTypePolicy::view), so a teacher can never build the user accounts report.
 */
class ReportBuilder
{
    public function __construct(private Container $container) {}

    /**
     * Build the report for the viewer with the validated filters.
     *
     * @throws AuthorizationException when the viewer cannot generate the report (404 status).
     */
    public function build(ReportType $type, User $viewer, ReportFilters $filters): Report
    {
        Gate::forUser($viewer)->authorize('view', $type);

        $generator = match ($type) {
            ReportType::StudentPerformance => StudentPerformanceReport::class,
            ReportType::QuizResults => QuizResultsReport::class,
            ReportType::MissionProgress => MissionProgressReport::class,
            ReportType::ClassroomSummary => ClassroomSummaryReport::class,
            ReportType::UserAccounts => UserAccountsReport::class,
        };

        return $this->container->make($generator)->build(new ReportScope($viewer, $filters));
    }
}
