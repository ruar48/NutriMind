<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Permission;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\FilterReportRequest;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportFilterOptions;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Show the catalog of reports a teacher can generate for their classrooms.
     */
    #[Authorize('viewAny', ReportType::class)]
    public function index(Request $request): View
    {
        $teacher = $request->user();

        return view('teacher.reports.index', [
            'reportTypes' => $this->teacherReportTypes(),
            'canExport' => $teacher->hasPermission(Permission::ExportReports),
            'hasSubjects' => $teacher->assignedSubjectIds() !== [],
        ]);
    }

    /**
     * Preview a report of the teacher's accessible classrooms with its filters.
     */
    #[Authorize('view', 'report')]
    public function show(FilterReportRequest $request, ReportType $report, ReportBuilder $reports, ReportFilterOptions $options): View
    {
        $teacher = $request->user();
        $generated = $reports->build($report, $teacher, $request->filters());

        return view('teacher.reports.show', [
            'reportType' => $report,
            'reportTypes' => $this->teacherReportTypes(),
            'report' => $generated,
            'rows' => $generated->paginate(),
            'options' => $options->for($report, $teacher),
            'canExport' => $teacher->hasPermission(Permission::ExportReports),
            'hasSubjects' => $teacher->assignedSubjectIds() !== [],
        ]);
    }

    /**
     * Get the reports teachers can generate.
     *
     * @return list<ReportType>
     */
    private function teacherReportTypes(): array
    {
        return array_values(array_filter(ReportType::cases(), fn (ReportType $type): bool => $type->availableToTeachers()));
    }
}
