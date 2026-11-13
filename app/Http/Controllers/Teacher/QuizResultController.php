<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\Concerns\ReadsMonitoringFilters;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Monitoring\QuizResults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class QuizResultController extends Controller
{
    use ReadsMonitoringFilters;

    /**
     * Show the quiz's results: headline figures, score distribution, attempts, learners who have not attempted yet
     * and the item analysis, for all its classrooms or one of them.
     */
    #[Authorize('viewResults', 'quiz')]
    public function show(Request $request, Quiz $quiz, QuizResults $results): View
    {
        $teacher = $request->user();
        $quiz->load('subject')->loadCount('questions')->loadSum('questions', 'points');

        $classrooms = $results->classroomsFor($quiz, $teacher);
        $classroom = $this->classroomFilter($request, $classrooms);
        [$sort, $direction] = $this->sortFilter($request, QuizResults::SORTS, 'date');
        $attempts = $results->attemptsPage($quiz, $classroom, $sort, $direction);

        return view('teacher.quizzes.results.show', [
            'quiz' => $quiz,
            'classrooms' => $classrooms,
            'classroom' => $classroom,
            'summary' => $results->summarize($quiz, $classrooms, $classroom),
            'attempts' => $attempts,
            'items' => $results->itemAnalysis($quiz, $classroom),
            'linkedLearnerIds' => $this->learnersOfTeacher($teacher, $attempts->getCollection()),
            'canExport' => $teacher->hasPermission(Permission::ExportReports),
        ]);
    }

    /**
     * Get the ids of the attempts' learners the teacher can open (enrolled in one of the teacher's classrooms).
     *
     * @param  iterable<QuizAttempt>  $attempts
     * @return list<int>
     */
    private function learnersOfTeacher(User $teacher, iterable $attempts): array
    {
        $studentIds = collect($attempts)->pluck('student_id')->unique()->values()->all();

        if ($studentIds === []) {
            return [];
        }

        return User::query()
            ->ofRole(UserRole::Student)
            ->whereKey($studentIds)
            ->whereHas('enrolledClassrooms', fn (Builder $query) => $query->accessibleBy($teacher))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
