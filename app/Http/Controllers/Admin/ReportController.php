<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\FilterReportRequest;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportFilterOptions;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Show the catalog of school reports.
     */
    #[Authorize('viewAny', ReportType::class)]
    public function index(): View
    {
        return view('admin.reports.index', [
            'reportTypes' => ReportType::cases(),
        ]);
    }

    /**
     * Preview a school-wide report with its filters.
     */
    #[Authorize('view', 'report')]
    public function show(FilterReportRequest $request, ReportType $report, ReportBuilder $reports, ReportFilterOptions $options): View
    {
        $generated = $reports->build($report, $request->user(), $request->filters());

        return view('admin.reports.show', [
            'reportType' => $report,
            'reportTypes' => ReportType::cases(),
            'report' => $generated,
            'rows' => $generated->paginate(),
            'options' => $options->for($report, $request->user()),
        ]);
    }
}
