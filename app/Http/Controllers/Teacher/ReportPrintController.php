<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\FilterReportRequest;
use App\Services\Reports\ReportBuilder;
use App\Services\Settings;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class ReportPrintController extends Controller
{
    /**
     * Show the printable (PDF-ready) version of a report of the teacher's accessible classrooms (needs
     * ExportReports, like the CSV export).
     */
    #[Authorize('export', 'report')]
    public function __invoke(FilterReportRequest $request, ReportType $report, ReportBuilder $reports, Settings $settings): View
    {
        return view('teacher.reports.print', [
            'report' => $reports->build($report, $request->user(), $request->filters()),
            'backUrl' => route('teacher.reports.show', ['report' => $report->value] + array_filter($request->validated(), filled(...))),
            'preparedBy' => $request->user(),
            'school' => [
                'name' => $settings->get('school_name'),
                'address' => $settings->get('school_address'),
                'id' => $settings->get('school_id'),
                'head' => $settings->get('school_head'),
            ],
        ]);
    }
}
