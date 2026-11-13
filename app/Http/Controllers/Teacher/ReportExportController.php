<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\FilterReportRequest;
use App\Services\CsvExport;
use App\Services\Reports\ReportBuilder;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    /**
     * Download every row of a report of the teacher's accessible classrooms as CSV (needs ExportReports).
     */
    #[Authorize('export', 'report')]
    public function __invoke(FilterReportRequest $request, ReportType $report, ReportBuilder $reports, CsvExport $csv): StreamedResponse
    {
        $generated = $reports->build($report, $request->user(), $request->filters());

        return $csv->download($generated->filename(now()), $generated->csvHeadings(), $generated->csvRows());
    }
}
