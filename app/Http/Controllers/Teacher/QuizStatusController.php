<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\QuizStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\UpdateQuizStatusRequest;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class QuizStatusController extends Controller
{
    /**
     * Publish, close, reopen or move the quiz back to draft.
     */
    #[Authorize('update', 'quiz')]
    public function update(UpdateQuizStatusRequest $request, Quiz $quiz): RedirectResponse
    {
        $previous = $quiz->status;
        $target = $request->targetStatus();

        $quiz->forceFill([
            'status' => $target,
            'published_at' => $target === QuizStatus::Published ? ($quiz->published_at ?? now()) : $quiz->published_at,
        ])->save();

        $message = match ($target) {
            QuizStatus::Published => $previous === QuizStatus::Closed
                ? 'Quiz reopened. Learners can take it again.'
                : 'Quiz published. Learners in the assigned classrooms can take it now.',
            QuizStatus::Closed => 'Quiz closed. Learners can no longer start it.',
            QuizStatus::Draft => 'Quiz moved back to draft. Learners no longer see it, and you can edit its questions.',
        };

        return redirect()->back(fallback: route('teacher.quizzes.show', $quiz))->with('status', $message);
    }
}
