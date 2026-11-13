<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\Concerns\ManagesQuizQuestions;
use App\Http\Requests\Teacher\StoreQuizQuestionRequest;
use App\Http\Requests\Teacher\UpdateQuizQuestionRequest;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Services\QuestionMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuizQuestionController extends Controller
{
    use ManagesQuizQuestions;

    /**
     * Show the form for a new question of the type picked in the builder (?type=).
     */
    #[Authorize('update', 'quiz')]
    public function create(Request $request, Quiz $quiz, QuestionMedia $media): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfQuestionsLocked($quiz)) {
            return $redirect;
        }

        $type = $this->requestedType($request) ?? QuestionType::MultipleChoice;

        return view('teacher.quizzes.questions.form', [
            'quiz' => $quiz->load('subject'),
            'question' => new QuizQuestion(['type' => $type, 'points' => 1]),
            'type' => $type,
            'number' => QuizQuestion::query()->where('quiz_id', $quiz->id)->count() + 1,
            'mediaLimits' => $this->mediaLimits($media),
        ]);
    }

    /**
     * Add the question at the end of the quiz.
     */
    #[Authorize('update', 'quiz')]
    public function store(StoreQuizQuestionRequest $request, Quiz $quiz, QuestionMedia $media): RedirectResponse
    {
        if ($redirect = $this->redirectIfQuestionsLocked($quiz)) {
            return $redirect;
        }

        $question = $quiz->questions()->make([
            ...$request->quizQuestionAttributes(),
            'sort_order' => $this->nextSortOrder($quiz),
        ]);

        DB::transaction(function () use ($quiz, $question, $request, $media): void {
            $media->save($question, $request->mediaInput());
            $this->markQuestionsChanged($quiz);
        });

        if ($request->wantsAnotherQuestion()) {
            return redirect()
                ->route('teacher.quizzes.questions.create', [$quiz, 'type' => $question->type->value])
                ->with('status', 'Question added. Write the next one.');
        }

        return redirect()->to($this->builderUrl($quiz, $question))->with('status', 'Question added.');
    }

    /**
     * Show the form for editing a question; ?type= switches it to another question type.
     */
    #[Authorize('update', 'quiz')]
    public function edit(Request $request, Quiz $quiz, QuizQuestion $question, QuestionMedia $media): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfQuestionsLocked($quiz)) {
            return $redirect;
        }

        return view('teacher.quizzes.questions.form', [
            'quiz' => $quiz->load('subject'),
            'question' => $question,
            'type' => $this->requestedType($request) ?? $question->type,
            'number' => (int) $quiz->questions()->pluck('id')->search($question->id) + 1,
            'mediaLimits' => $this->mediaLimits($media),
        ]);
    }

    /**
     * Save the question's changes; the quiz then needs a new review.
     */
    #[Authorize('update', 'quiz')]
    public function update(UpdateQuizQuestionRequest $request, Quiz $quiz, QuizQuestion $question, QuestionMedia $media): RedirectResponse
    {
        if ($redirect = $this->redirectIfQuestionsLocked($quiz)) {
            return $redirect;
        }

        $question->fill($request->quizQuestionAttributes());

        DB::transaction(function () use ($quiz, $question, $request, $media): void {
            if ($media->save($question, $request->mediaInput())) {
                $this->markQuestionsChanged($quiz);
            }
        });

        return redirect()->to($this->builderUrl($quiz, $question))->with('status', 'Question saved.');
    }

    /**
     * Delete the question and its media files.
     */
    #[Authorize('update', 'quiz')]
    public function destroy(Quiz $quiz, QuizQuestion $question, QuestionMedia $media): RedirectResponse
    {
        if ($redirect = $this->redirectIfQuestionsLocked($quiz)) {
            return $redirect;
        }

        DB::transaction(function () use ($quiz, $question, $media): void {
            $question->delete();
            $media->delete($question->image_path, $question->video_path);
            $this->markQuestionsChanged($quiz);
        });

        return redirect()->to($this->builderUrl($quiz))->with('status', 'Question deleted.');
    }

    /**
     * Get the upload limits shown in the media field hints.
     *
     * @return array{image: string, video: string}
     */
    private function mediaLimits(QuestionMedia $media): array
    {
        return ['image' => $media->imageLimitLabel(), 'video' => $media->videoLimitLabel()];
    }

    /**
     * Get the question type asked for in the query string, if valid.
     */
    private function requestedType(Request $request): ?QuestionType
    {
        $type = $request->query('type');

        return is_string($type) ? QuestionType::tryFrom($type) : null;
    }
}
