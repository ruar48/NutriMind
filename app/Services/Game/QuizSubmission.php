<?php

namespace App\Services\Game;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\DB;

/**
 * Grades a quiz the learner answered in the game and records it as a submitted attempt.
 *
 * The server grades every question of the quiz, so the game never needs the correct answers before submitting:
 * a question the game did not answer counts as wrong. A multiple choice or true/false answer is the index of the
 * chosen choice ("0" for the first), an identification answer is the typed text.
 */
#[Scoped]
class QuizSubmission
{
    public function __construct(private StudentPoints $points) {}

    /**
     * Grade and store the attempt, then recalculate the learner's points.
     *
     * @param  array<int, string|null>  $answers  the answer of each question, keyed by quiz question ID
     */
    public function submit(
        User $student,
        Quiz $quiz,
        array $answers,
        ?CarbonInterface $startedAt = null,
        ?int $durationSeconds = null,
        ?CarbonInterface $submittedAt = null,
        bool $isOffline = false,
    ): QuizAttempt {
        $submittedAt ??= now();
        $durationSeconds ??= $startedAt === null ? null : (int) max(0, $startedAt->diffInSeconds($submittedAt));

        return DB::transaction(function () use ($student, $quiz, $answers, $startedAt, $durationSeconds, $submittedAt, $isOffline): QuizAttempt {
            $questions = $quiz->questions()->get();

            $gradedAnswers = $questions->map(function (QuizQuestion $question) use ($answers): array {
                $answer = $answers[$question->id] ?? null;
                $isCorrect = $question->isCorrectAnswer($answer);

                return [
                    'quiz_question_id' => $question->id,
                    'answer' => $answer,
                    'is_correct' => $isCorrect,
                    'points_awarded' => $isCorrect ? $question->points : 0,
                ];
            });

            $score = (int) $gradedAnswers->sum('points_awarded');
            $maxScore = (int) $questions->sum('points');
            $percentage = $maxScore > 0 ? round($score / $maxScore * 100, 2) : 0.0;

            $previousAttempts = $quiz->attempts()->where('student_id', $student->getKey())->submitted()->count();

            /** @var QuizAttempt $attempt */
            $attempt = $student->quizAttempts()->create([
                'quiz_id' => $quiz->id,
                'classroom_id' => $quiz->classroomsOpenTo($student)->value('classrooms.id'),
                'attempt_number' => $previousAttempts + 1,
                'score' => $score,
                'max_score' => $maxScore,
                'percentage' => $percentage,
                'passed' => $percentage >= $quiz->passing_score,
                'started_at' => $startedAt ?? ($durationSeconds === null ? null : $submittedAt->copy()->subSeconds($durationSeconds)),
                'submitted_at' => $submittedAt,
                'duration_seconds' => $durationSeconds,
                'is_offline' => $isOffline,
                'synced_at' => now(),
            ]);

            $attempt->answers()->createMany($gradedAnswers->all());

            $student->studentProfile()->update(['last_active_at' => now()]);
            $this->points->recalculate($student);

            return $attempt->setRelation('quiz', $quiz);
        });
    }
}
