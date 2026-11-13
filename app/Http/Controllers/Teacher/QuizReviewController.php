<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class QuizReviewController extends Controller
{
    /**
     * Preview the quiz the way learners see it, optionally with the answer key (?answers=1).
     */
    #[Authorize('view', 'quiz')]
    public function show(Request $request, Quiz $quiz): View
    {
        $quiz->load([
            'subject',
            'questions',
            'classrooms' => fn ($classrooms) => $classrooms->orderBy('classrooms.name')->orderBy('classrooms.id'),
        ]);

        return view('teacher.quizzes.review', [
            'quiz' => $quiz,
            'showAnswers' => $request->boolean('answers'),
            'checklist' => QuizController::publishChecklist($quiz),
            'totalPoints' => (int) $quiz->questions->sum('points'),
        ]);
    }

    /**
     * Mark the draft as reviewed: its current questions are ready for learners.
     */
    #[Authorize('update', 'quiz')]
    public function store(Quiz $quiz): RedirectResponse
    {
        if (! $quiz->isDraft()) {
            return redirect()->route('teacher.quizzes.show', $quiz)
                ->with('error', 'Only draft quizzes are reviewed. This quiz is already '.strtolower($quiz->status->label()).'.');
        }

        if ($quiz->questions()->reorder()->doesntExist()) {
            return redirect()->route('teacher.quizzes.review.show', $quiz)
                ->with('error', 'Add at least one question before marking the quiz as reviewed.');
        }

        $quiz->forceFill(['reviewed_at' => now()])->save();

        $nextStep = $quiz->classrooms()->active()->exists()
            ? 'You can publish it now.'
            : 'Assign it to a classroom to publish it.';

        return redirect()->route('teacher.quizzes.show', $quiz)->with('status', 'Quiz marked as reviewed. '.$nextStep);
    }
}
