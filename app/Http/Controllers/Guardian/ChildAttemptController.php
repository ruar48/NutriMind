<?php

namespace App\Http\Controllers\Guardian;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

/**
 * A linked learner's submitted quiz attempt, question by question, for the parent.
 */
class ChildAttemptController extends Controller
{
    /**
     * Show the attempt's answers; the correct answers appear only when the quiz reveals them to learners.
     *
     * The route is not scoped, so an attempt by another learner, or one that was never submitted, is reported as
     * missing.
     */
    #[Authorize('viewAsGuardian', 'student')]
    #[Authorize(Permission::ViewQuizDetails)]
    public function show(User $student, QuizAttempt $attempt): View
    {
        abort_unless($attempt->student()->is($student) && $attempt->submitted_at !== null, 404);

        $attempt->load(['quiz.subject', 'quiz.questions', 'answers', 'classroom']);

        $answersByQuestion = $attempt->answers->keyBy('quiz_question_id');

        return view('parent.children.attempts.show', [
            'student' => $student,
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'answersByQuestion' => $answersByQuestion,
            'correctCount' => $attempt->answers->where('is_correct', true)->count(),
            'otherAttempts' => $student->quizAttempts()
                ->submitted()
                ->whereBelongsTo($attempt->quiz)
                ->orderBy('attempt_number')
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->get(),
        ]);
    }
}
