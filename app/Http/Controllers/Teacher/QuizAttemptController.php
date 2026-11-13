<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class QuizAttemptController extends Controller
{
    /**
     * Show a learner's attempt at one of the teacher's quizzes, answer by answer.
     *
     * The attempt is visible to the teacher who can see the quiz's results (its owner, while they teach its subject);
     * anyone else gets 404, and the owner of a quiz whose subject was revoked gets 403.
     */
    #[Authorize('viewAny', Quiz::class)]
    public function show(Request $request, QuizAttempt $attempt): View
    {
        Gate::authorize('viewResults', $attempt->quiz);

        $teacher = $request->user();
        $attempt->load(['quiz.subject', 'quiz.questions', 'answers', 'student.studentProfile', 'classroom.subject']);

        return view('teacher.attempts.show', [
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'student' => $attempt->student,
            'answersByQuestion' => $attempt->answers->keyBy('quiz_question_id'),
            'otherAttempts' => $attempt->quiz->attempts()
                ->whereBelongsTo($attempt->student, 'student')
                ->whereKeyNot($attempt->getKey())
                ->submitted()
                ->orderBy('attempt_number')
                ->orderBy('id')
                ->get(),
            'canViewLearner' => $teacher->can('viewAsTeacher', $attempt->student),
            'canGiveFeedback' => $teacher->can('giveFeedbackTo', $attempt->student),
        ]);
    }
}
