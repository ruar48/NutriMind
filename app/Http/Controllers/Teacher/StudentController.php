<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\PerformanceBand;
use App\Enums\ProgressStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\Concerns\ReadsMonitoringFilters;
use App\Models\Classroom;
use App\Models\LearnerFeedback;
use App\Models\LearningModule;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use App\Services\LearnerProgressService;
use App\Services\Monitoring\LearnerRoster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class StudentController extends Controller
{
    use ReadsMonitoringFilters;

    /**
     * The number of attempts per page on a learner's page.
     */
    public const ATTEMPTS_PER_PAGE = 10;

    /**
     * The number of feedback messages listed on a learner's page.
     */
    public const FEEDBACK_LIMIT = 5;

    /**
     * List the learners of the teacher's active classrooms with their scores and game activity.
     */
    #[Authorize('viewAny', Classroom::class)]
    public function index(Request $request, LearnerRoster $roster): View
    {
        $classrooms = $roster->classroomsFor($request->user());
        $classroom = $this->classroomFilter($request, $classrooms);
        $band = $this->enumFilter($request, 'band', PerformanceBand::class);
        [$sort, $direction] = $this->sortFilter($request, LearnerRoster::SORTS, 'name');

        return view('teacher.students.index', [
            'classrooms' => $classrooms,
            'classroom' => $classroom,
            'band' => $band,
            'canGiveFeedback' => $request->user()->can('create', LearnerFeedback::class),
            'roster' => $roster->build(
                $classroom === null ? $classrooms : new EloquentCollection([$classroom]),
                $band,
                $this->searchFilter($request),
                $sort,
                $direction,
            ),
        ]);
    }

    /**
     * Show a learner's progress in the subjects of the teacher's classrooms the learner is enrolled in.
     */
    #[Authorize('viewAsTeacher', 'student')]
    public function show(Request $request, User $student, LearnerProgressService $progress): View
    {
        $teacher = $request->user();
        $subjectIds = $progress->teacherSubjectIds($teacher, $student);
        $summary = $progress->summarize($student, $subjectIds);
        $canGiveFeedback = $teacher->can('giveFeedbackTo', $student);

        return view('teacher.students.show', [
            'student' => $student,
            'summary' => $summary,
            'attempts' => $student->quizAttempts()
                ->submitted()
                ->whereHas('quiz', fn (Builder $quiz) => $quiz->whereIn('quizzes.subject_id', $subjectIds))
                ->with(['quiz.subject', 'classroom'])
                ->latest('submitted_at')
                ->orderByDesc('id')
                ->paginate(self::ATTEMPTS_PER_PAGE, pageName: 'attempts')
                ->withQueryString(),
            'attemptUrl' => fn (QuizAttempt $attempt): ?string => $attempt->quiz->teacher()->is($teacher)
                ? route('teacher.attempts.show', $attempt)
                : null,
            'missions' => $student->missionProgress()
                ->whereHas('mission', fn (Builder $mission) => $mission->whereIn('missions.subject_id', $subjectIds))
                ->with('mission.subject')
                ->latest('updated_at')
                ->orderByDesc('id')
                ->get(),
            'modules' => $this->moduleProgress($student, $subjectIds),
            'canGiveFeedback' => $canGiveFeedback,
            'feedback' => $canGiveFeedback
                ? $teacher->feedbackGiven()
                    ->whereBelongsTo($student, 'student')
                    ->with(['classroom.subject', 'quizAttempt.quiz'])
                    ->latest()
                    ->orderByDesc('id')
                    ->limit(self::FEEDBACK_LIMIT)
                    ->get()
                : new EloquentCollection,
            'feedbackCount' => $canGiveFeedback
                ? LearnerFeedback::query()->whereBelongsTo($teacher, 'teacher')->whereBelongsTo($student, 'student')->count()
                : 0,
        ]);
    }

    /**
     * Get the published learning modules of the learner's grade in the subjects, with the published activities the
     * learner can play and has completed, ordered by subject, quarter and game order.
     *
     * @param  list<int>  $subjectIds
     * @return EloquentCollection<int, LearningModule>
     */
    private function moduleProgress(User $student, array $subjectIds): EloquentCollection
    {
        $grade = $student->studentProfile?->grade_level;

        if ($grade === null || $subjectIds === []) {
            return new EloquentCollection;
        }

        return LearningModule::query()
            ->published()
            ->forGrade($grade)
            ->whereIn('learning_modules.subject_id', $subjectIds)
            ->withCount([
                'activities as activities_total' => fn (Builder $query) => $query->where('learning_activities.is_published', true),
                'activities as activities_completed' => fn (Builder $query) => $query
                    ->where('learning_activities.is_published', true)
                    ->whereHas('progress', fn (Builder $progress) => $progress
                        ->where('activity_progress.student_id', $student->getKey())
                        ->where('activity_progress.status', ProgressStatus::Completed)),
            ])
            ->with('subject')
            ->orderBy(Subject::query()->select('name')->whereColumn('subjects.id', 'learning_modules.subject_id')->limit(1))
            ->orderBy('learning_modules.quarter')
            ->orderBy('learning_modules.sort_order')
            ->orderBy('learning_modules.id')
            ->get();
    }
}
