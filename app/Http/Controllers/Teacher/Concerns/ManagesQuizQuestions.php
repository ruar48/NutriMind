<?php

namespace App\Http\Controllers\Teacher\Concerns;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\RedirectResponse;

/**
 * Rules shared by the controllers that change a quiz's questions.
 *
 * Questions change only while the quiz is a draft that nobody attempted, and every change asks for a new review.
 */
trait ManagesQuizQuestions
{
    /**
     * The message shown when the questions of a quiz with learner attempts would change.
     */
    public const ATTEMPTED_QUESTIONS_LOCKED_MESSAGE = 'Learners have already taken this quiz. Duplicate it to make changes.';

    /**
     * The message shown when the questions of a published or closed quiz would change.
     */
    public const PUBLISHED_QUESTIONS_LOCKED_MESSAGE = 'Unpublish the quiz to edit its questions.';

    /**
     * Get why the quiz's questions cannot change, or null when they can.
     */
    public static function questionsLockedMessage(Quiz $quiz): ?string
    {
        return match (true) {
            $quiz->attempts()->exists() => self::ATTEMPTED_QUESTIONS_LOCKED_MESSAGE,
            ! $quiz->isDraft() => self::PUBLISHED_QUESTIONS_LOCKED_MESSAGE,
            default => null,
        };
    }

    /**
     * Redirect to the quiz builder with the reason when the quiz's questions cannot change.
     */
    protected function redirectIfQuestionsLocked(Quiz $quiz): ?RedirectResponse
    {
        $message = self::questionsLockedMessage($quiz);

        return $message === null ? null : redirect()->route('teacher.quizzes.show', $quiz)->with('error', $message);
    }

    /**
     * Get the sort order that places a new question after the quiz's other questions.
     */
    protected function nextSortOrder(Quiz $quiz): int
    {
        return (int) QuizQuestion::query()->where('quiz_id', $quiz->getKey())->max('sort_order') + 1;
    }

    /**
     * Record that the questions changed: the quiz needs a new review before it can be published.
     */
    protected function markQuestionsChanged(Quiz $quiz): void
    {
        $quiz->forceFill(['reviewed_at' => null])->save();
    }

    /**
     * Get the builder URL scrolled to the given question.
     */
    protected function builderUrl(Quiz $quiz, ?QuizQuestion $question = null): string
    {
        return route('teacher.quizzes.show', $quiz).($question === null ? '' : '#question-'.$question->getKey());
    }
}
