<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GradeLevel;
use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Services\Content\CompetencyCoverage;
use App\Services\Content\ContentAlignmentReport;
use App\Services\Content\CoverageStatus;
use App\Services\Content\ListFilter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Administrator › Lesson alignment: the competencies in the teachers' lesson plans against the game content.
 *
 * Admin-only: the route group applies role:admin.
 */
class ContentAlignmentController extends Controller
{
    /**
     * Competencies per page.
     */
    private const int PER_PAGE = 20;

    /**
     * Show the coverage of every planned competency with filters (subject, grade, status, search).
     */
    public function __invoke(Request $request, ContentAlignmentReport $report): View
    {
        $filters = [
            'subject' => ListFilter::id($request, 'subject'),
            'grade' => ListFilter::enum($request, 'grade', GradeLevel::class),
            'status' => ListFilter::enum($request, 'status', CoverageStatus::class),
            'search' => ListFilter::search($request),
        ];

        $competencies = $report->competencies($filters['subject'], $filters['grade']);

        if ($filters['search'] !== null) {
            $competencies = $competencies->filter(fn (CompetencyCoverage $coverage): bool => Str::contains(
                $coverage->code.' '.$coverage->learningCompetency,
                $filters['search'],
                ignoreCase: true,
            ))->values();
        }

        $statusCounts = collect(CoverageStatus::cases())
            ->mapWithKeys(fn (CoverageStatus $status): array => [
                $status->value => $competencies->filter(fn (CompetencyCoverage $coverage): bool => $coverage->status === $status)->count(),
            ])
            ->all();

        $listed = $filters['status'] === null
            ? $competencies
            : $competencies->filter(fn (CompetencyCoverage $coverage): bool => $coverage->status === $filters['status'])->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('admin.content-alignment.index', [
            'competencies' => (new LengthAwarePaginator(
                $listed->forPage($page, self::PER_PAGE)->values(),
                $listed->count(),
                self::PER_PAGE,
                $page,
                ['path' => $request->url(), 'pageName' => 'page'],
            ))->withQueryString(),
            'filters' => $filters,
            'statusCounts' => $statusCounts,
            'competencyCount' => $competencies->count(),
            'plannedLessonCount' => (int) $competencies->sum('plansCount'),
            'unmappedPlans' => $report->unmappedPlansCount($filters['subject'], $filters['grade']),
            'subjectOptions' => Subject::query()->orderBy('name')->orderBy('id')->pluck('name', 'id'),
        ]);
    }
}
