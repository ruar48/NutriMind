<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\ReportType;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccess;
use Illuminate\Auth\Access\Response;

/**
 * Access to the generated reports (P2-Reports).
 *
 * Administrators generate every report for the whole school; teachers generate the reports available to teachers,
 * scoped to their own classrooms. Exporting (CSV or the print view) also needs the ExportReports permission, which
 * administrators always hold.
 */
class ReportTypePolicy
{
    use AuthorizesAccess;

    /**
     * Determine whether the user can open the report catalog.
     */
    public function viewAny(User $user): Response
    {
        return $user->isAdmin() || $user->isTeacher() ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can generate and preview the report. Reports the user cannot generate are
     * reported as missing.
     */
    public function view(User $user, ReportType $report): Response
    {
        return $user->isAdmin() || ($user->isTeacher() && $report->availableToTeachers())
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can export the report as CSV or open its print view.
     */
    public function export(User $user, ReportType $report): Response
    {
        return $this->every(
            fn (): Response => $this->view($user, $report),
            fn (): Response => $this->permission($user, Permission::ExportReports),
        );
    }
}
