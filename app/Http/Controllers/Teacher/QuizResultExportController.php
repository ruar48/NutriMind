<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\Concerns\ReadsMonitoringFilters;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\CsvExport;
use App\Services\Monitoring\QuizResults;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuizResultExportController extends Controller
{
    use ReadsMonitoringFilters;

    /**
     * The CSV columns, in order.
     *
     * @var list<string>
     */
    public const HEADINGS = [
        'Learner',
        'Username',
        'LRN',
        'Classroom',
        'Attempt',
        'Score',
        'Max score',
        'Percentage',
        'Performance band',
        'Passed',
        'Duration (m:ss)',
        'Duration (seconds)',
        'Started',
        'Submitted',
        'Played offline',
        'Synced',
    ];

    /**
     * Download the quiz's submitted attempts as CSV (all classrooms, or the ?classroom= one), oldest first.
     */
    #[Authorize('viewResults', 'quiz')]
    #[Authorize(Permission::ExportReports)]
    public function __invoke(Request $request, Quiz $quiz, QuizResults $results, CsvExport $csv): StreamedResponse
    {
        $classroom = $this->classroomFilter($request, $results->classroomsFor($quiz, $request->user()));

        $rows = $results->attempts($quiz, $classroom)
            ->with(['student.studentProfile', 'classroom'])
            ->orderBy('quiz_attempts.submitted_at')
            ->orderBy('quiz_attempts.id')
            ->lazy()
            ->map(fn (QuizAttempt $attempt): array => [
                $attempt->student->name,
                $attempt->student->username,
                $attempt->student->studentProfile?->lrn,
                $attempt->classroom?->name,
                $attempt->attempt_number,
                $attempt->score,
                $attempt->max_score,
                (float) $attempt->percentage,
                $attempt->band(),
                $attempt->passed,
                QuizResults::formatDuration($attempt->duration_seconds),
                $attempt->duration_seconds,
                $attempt->started_at,
                $attempt->submitted_at,
                $attempt->is_offline,
                $attempt->synced_at,
            ]);

        $name = $quiz->title.' results'.($classroom === null ? '' : ' '.$classroom->name).' '.now()->format('Y-m-d');

        return $csv->download($name, self::HEADINGS, $rows);
    }
}
