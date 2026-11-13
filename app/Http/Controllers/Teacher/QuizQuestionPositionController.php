<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\Concerns\ManagesQuizQuestions;
use App\Http\Requests\Teacher\UpdateQuizQuestionPositionRequest;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;

class QuizQuestionPositionController extends Controller
{
    use ManagesQuizQuestions;

    /**
     * Swap the question with its neighbour above or below and number every question 1, 2, 3… again.
     */
    #[Authorize('update', 'quiz')]
    public function update(UpdateQuizQuestionPositionRequest $request, Quiz $quiz, QuizQuestion $question): RedirectResponse
    {
        if ($redirect = $this->redirectIfQuestionsLocked($quiz)) {
            return $redirect;
        }

        $questions = $quiz->questions()->get(['id', 'quiz_id', 'sort_order'])->values();
        $position = $questions->search(fn (QuizQuestion $candidate): bool => $candidate->is($question));
        $target = $request->movesUp() ? $position - 1 : $position + 1;

        if ($position === false || ! $questions->has($target)) {
            return redirect()->to($this->builderUrl($quiz, $question))
                ->with('error', $request->movesUp() ? 'This question is already the first one.' : 'This question is already the last one.');
        }

        $ordered = $questions->all();
        [$ordered[$position], $ordered[$target]] = [$ordered[$target], $ordered[$position]];

        DB::transaction(function () use ($quiz, $ordered): void {
            foreach ($ordered as $index => $item) {
                if ($item->sort_order !== $index + 1) {
                    QuizQuestion::query()->whereKey($item->id)->update(['sort_order' => $index + 1]);
                }
            }

            $this->markQuestionsChanged($quiz);
        });

        return redirect()->to($this->builderUrl($quiz, $question))
            ->with('status', $request->movesUp() ? 'Question moved up.' : 'Question moved down.');
    }
}
