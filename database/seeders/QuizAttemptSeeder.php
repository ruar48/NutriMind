<?php

namespace Database\Seeders;

use App\Enums\QuestionType;
use App\Enums\QuizStatus;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use Database\Seeders\Support\BulkInsert;
use Database\Seeders\Support\Catalog\QuestionCatalog;
use Database\Seeders\Support\DemoClock;
use Database\Seeders\Support\DemoRandom;
use Database\Seeders\Support\LearnerProfile;
use Database\Seeders\Support\LearnerProfiles;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the learners' quiz attempts and answers.
 *
 * Each learner answers every question correctly with a probability that follows their ability, the question's
 * difficulty and type, and a gentle improvement over the weeks, so scores, answers and performance bands agree.
 * About 85% of the assigned learners attempt each quiz, some retake failed quizzes, and about one attempt in ten is
 * taken offline and synced later.
 */
class QuizAttemptSeeder extends Seeder
{
    /**
     * The share of attempts taken offline and synced later.
     */
    private const OFFLINE_SHARE = 0.11;

    /**
     * How much easier or harder each question difficulty is than the learner's ability.
     */
    private const DIFFICULTY_ADJUSTMENT = ['easy' => 0.05, 'medium' => 0.0, 'hard' => -0.15];

    /**
     * How much easier or harder each question type is (true or false can be guessed, identification needs recall).
     */
    private const TYPE_ADJUSTMENT = ['true_false' => 0.03, 'multiple_choice' => 0.0, 'identification' => -0.07];

    /**
     * How much better learners do on a second and third attempt, after reviewing.
     */
    private const RETAKE_BONUS = [1 => 0.0, 2 => 0.06, 3 => 0.09];

    /**
     * The seeded random stream of this seeder.
     */
    private DemoRandom $random;

    /**
     * The anchored clock of this run.
     */
    private DemoClock $clock;

    /**
     * The catalog entries of the questions, keyed by prompt.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $catalog = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->random = DemoRandom::stream('quiz-attempts');
        $this->clock = DemoClock::start();
        $this->catalog = QuestionCatalog::byPrompt();

        $profiles = LearnerProfiles::load($this->clock->now);
        $quizzes = Quiz::query()
            ->whereIn('status', [QuizStatus::Published, QuizStatus::Closed])
            ->orderBy('id')
            ->get(['id', 'passing_score', 'max_attempts', 'time_limit_minutes', 'learning_module_id'])
            ->keyBy('id');
        $questions = QuizQuestion::query()
            ->whereIn('quiz_id', $quizzes->modelKeys())
            ->orderBy('quiz_id')
            ->orderBy('sort_order')
            ->get(['id', 'quiz_id', 'type', 'prompt', 'choices', 'correct_answer', 'points', 'sort_order'])
            ->groupBy('quiz_id');
        $assignments = (new Quiz)->classrooms()->newPivotStatement()
            ->whereIn('quiz_id', $quizzes->modelKeys())
            ->orderBy('quiz_id')
            ->orderBy('classroom_id')
            ->get(['quiz_id', 'classroom_id', 'available_from', 'due_at']);
        $enrollments = (new Classroom)->students()->newPivotStatement()
            ->orderBy('classroom_id')
            ->orderBy('student_id')
            ->get(['classroom_id', 'student_id', 'enrolled_at'])
            ->groupBy('classroom_id');

        $quizAdjustments = [];

        foreach ($quizzes as $quiz) {
            $quizAdjustments[$quiz->id] = $this->random->between(-0.04, 0.03) - ($quiz->learning_module_id === null ? 0.02 : 0.0);
        }

        $attempts = [];

        foreach ($assignments as $assignment) {
            $quiz = $quizzes[$assignment->quiz_id];

            foreach ($enrollments[$assignment->classroom_id] ?? [] as $enrollment) {
                array_push($attempts, ...$this->attemptsOf(
                    $quiz, $questions[$quiz->id], $profiles[$enrollment->student_id], (int) $assignment->classroom_id,
                    (int) $enrollment->student_id, $assignment, DemoClock::at($enrollment->enrolled_at), $quizAdjustments[$quiz->id],
                ));
            }
        }

        BulkInsert::models(QuizAttempt::class, array_column($attempts, 'row'));

        $this->seedAnswers($attempts);
    }

    /**
     * Simulate one learner's attempts at an assigned quiz (none when the learner skipped it).
     *
     * @param  Collection<int, QuizQuestion>  $questions
     * @return list<array{row: array<string, mixed>, answers: list<array<string, mixed>>}>
     */
    private function attemptsOf(
        Quiz $quiz,
        Collection $questions,
        LearnerProfile $profile,
        int $classroomId,
        int $studentId,
        object $assignment,
        int $enrolledAt,
        float $quizAdjustment,
    ): array {
        if (! $profile->hasPlayed) {
            return [];
        }

        $availableFrom = DemoClock::at($assignment->available_from);
        $dueAt = DemoClock::at($assignment->due_at);
        $from = max($availableFrom, $enrolledAt);
        $until = min($dueAt, $this->clock->now - 10 * 60, $profile->activeUntil($this->clock->now));

        if ($until - $from < DemoClock::HOUR || ! $this->random->chance($this->participation($profile, $from, $dueAt))) {
            return [];
        }

        $lean = max(-0.8, min(0.8, ($profile->engagement - 0.8) * 3));
        $attempts = [];

        for ($number = 1; $number <= 3; $number++) {
            if ($number > 1) {
                $previous = $attempts[$number - 2];

                if (! $this->retakes($quiz, $profile, $number, $previous['row'])) {
                    break;
                }

                $from = DemoClock::at($previous['row']['submitted_at']) + 4 * DemoClock::HOUR;
                $until = min($until, $from + 4 * DemoClock::DAY);
            }

            $attempt = $this->attempt($quiz, $questions, $profile, $classroomId, $studentId, $number, $from, $until, $lean, $quizAdjustment);

            if ($attempt === null) {
                break;
            }

            $attempts[] = $attempt;
        }

        return $attempts;
    }

    /**
     * Get the probability that the learner takes the quiz: lower while the quiz is still open, and for learners who
     * stopped playing before it was due.
     */
    private function participation(LearnerProfile $profile, int $from, int $dueAt): float
    {
        $openUntil = min($dueAt, $profile->activeUntil($this->clock->now), $this->clock->now);
        $shareOfWindow = $dueAt > $from ? max(0.0, min(1.0, ($openUntil - $from) / ($dueAt - $from))) : 0.0;

        return $profile->engagement * min(1.0, 0.15 + 0.9 * $shareOfWindow);
    }

    /**
     * Determine whether the learner retakes the quiz: often after failing, rarely to improve a passing score.
     *
     * @param  array{passed: bool, percentage: float}  $previous
     */
    private function retakes(Quiz $quiz, LearnerProfile $profile, int $number, array $previous): bool
    {
        if ($quiz->max_attempts !== null && $number > $quiz->max_attempts) {
            return false;
        }

        if ($previous['passed']) {
            return $previous['percentage'] < 90 && $this->random->chance(0.07);
        }

        return $this->random->chance($number === 2 ? 0.3 + 0.35 * $profile->engagement : 0.3);
    }

    /**
     * Simulate one attempt submitted between the bounds, or null when there is no room for it.
     *
     * @param  Collection<int, QuizQuestion>  $questions
     * @return array{row: array<string, mixed>, answers: list<array<string, mixed>>}|null
     */
    private function attempt(
        Quiz $quiz,
        Collection $questions,
        LearnerProfile $profile,
        int $classroomId,
        int $studentId,
        int $number,
        int $from,
        int $until,
        float $lean,
        float $quizAdjustment,
    ): ?array {
        $duration = $this->duration($quiz, $questions, $profile);

        if ($until - ($from + $duration) < 30 * 60) {
            return null;
        }

        $submittedAt = DemoClock::playTime($this->random, $from + $duration, $until, $lean);
        $ability = $profile->abilityAt($submittedAt, $this->clock->now) + $quizAdjustment + self::RETAKE_BONUS[$number]
            + $this->random->normal(0.0, 0.035);

        $answers = [];
        $score = 0;
        $maxScore = 0;

        foreach ($questions as $question) {
            $entry = $this->catalog[$question->prompt];
            $probability = max(0.04, min(0.985, $ability + self::DIFFICULTY_ADJUSTMENT[$entry['difficulty']] + self::TYPE_ADJUSTMENT[$question->type->value]));
            $answer = $this->random->chance($probability) ? $this->correctAnswer($question, $entry) : $this->wrongAnswer($question, $entry);
            $isCorrect = $question->isCorrectAnswer($answer);

            $answers[] = [
                'quiz_question_id' => $question->id,
                'answer' => $answer,
                'is_correct' => $isCorrect,
                'points_awarded' => $isCorrect ? $question->points : 0,
            ];

            $score += $isCorrect ? $question->points : 0;
            $maxScore += $question->points;
        }

        $percentage = round($score / $maxScore * 100, 2);
        [$isOffline, $syncedAt] = $this->sync($profile, $submittedAt);

        return [
            'row' => [
                'quiz_id' => $quiz->id,
                'student_id' => $studentId,
                'classroom_id' => $classroomId,
                'attempt_number' => $number,
                'score' => $score,
                'max_score' => $maxScore,
                'percentage' => $percentage,
                'passed' => $percentage >= $quiz->passing_score,
                'started_at' => DemoClock::format($submittedAt - $duration),
                'submitted_at' => DemoClock::format($submittedAt),
                'duration_seconds' => $duration,
                'is_offline' => $isOffline,
                'synced_at' => DemoClock::format($syncedAt),
                'created_at' => DemoClock::format($submittedAt - $duration),
                'updated_at' => DemoClock::format($syncedAt),
            ],
            'answers' => $answers,
        ];
    }

    /**
     * Get how long an attempt takes: longer for typed answers and less able learners, within the time limit.
     *
     * @param  Collection<int, QuizQuestion>  $questions
     */
    private function duration(Quiz $quiz, Collection $questions, LearnerProfile $profile): int
    {
        $seconds = $this->random->int(20, 60);

        foreach ($questions as $question) {
            $seconds += $this->random->int(25, 70) * ($question->type === QuestionType::Identification ? 1.5 : 1.0) * (1.25 - 0.4 * $profile->ability);
        }

        if ($quiz->time_limit_minutes !== null) {
            $seconds = min($seconds, $quiz->time_limit_minutes * 60 - $this->random->int(5, 45));
        }

        return max(60, (int) round($seconds));
    }

    /**
     * Decide whether an attempt was taken offline and when it reached the server.
     *
     * @return array{0: bool, 1: int}
     */
    private function sync(LearnerProfile $profile, int $submittedAt): array
    {
        $latestSync = min($this->clock->now - 60, $profile->activeUntil($this->clock->now));

        if (! $this->random->chance(self::OFFLINE_SHARE)) {
            return [false, $submittedAt];
        }

        $syncedAt = min($submittedAt + $this->random->int(60, 30 * 60) * 60, $latestSync);

        return $syncedAt - $submittedAt < 10 * 60 ? [false, $submittedAt] : [true, $syncedAt];
    }

    /**
     * Get a correct answer: the choice index, or one of the accepted answers typed in any letter case.
     *
     * @param  array{accepted: list<string>}  $entry
     */
    private function correctAnswer(QuizQuestion $question, array $entry): string
    {
        if ($question->type !== QuestionType::Identification) {
            return $question->correct_answer;
        }

        return match ($this->random->weighted(['first' => 0.7, 'any' => 0.15, 'lowercase' => 0.15])) {
            'first' => $entry['accepted'][0],
            'any' => $this->random->pick($entry['accepted']),
            default => Str::lower($entry['accepted'][0]),
        };
    }

    /**
     * Get a wrong answer: usually the most tempting wrong choice or a typical wrong answer, sometimes a misspelling
     * or no answer at all.
     *
     * @param  array{choices: list<string>|null, accepted: list<string>, wrong: list<string>}  $entry
     */
    private function wrongAnswer(QuizQuestion $question, array $entry): ?string
    {
        if ($question->type === QuestionType::TrueFalse) {
            return $question->correct_answer === '0' ? '1' : '0';
        }

        if ($question->type === QuestionType::MultipleChoice) {
            if ($this->random->chance(0.02)) {
                return null;
            }

            $tempting = array_search($entry['choices'][1], $question->choices, true);
            $weights = [];

            foreach (array_keys($question->choices) as $index) {
                if ((string) $index !== $question->correct_answer) {
                    $weights[$index] = $index === $tempting ? 3 : 1;
                }
            }

            return (string) $this->random->weighted($weights);
        }

        return match ($this->random->weighted(['typical' => 0.85, 'misspelled' => 0.12, 'blank' => 0.03])) {
            'typical' => $entry['wrong'][$this->random->weighted(array_map(fn (int $index): int => $index === 0 ? 3 : 1, array_keys($entry['wrong'])))],
            'misspelled' => $this->misspelling($question, $entry),
            default => null,
        };
    }

    /**
     * Misspell the main accepted answer by dropping one letter (falling back to a typical wrong answer when the
     * misspelling would still count as correct).
     *
     * @param  array{accepted: list<string>, wrong: list<string>}  $entry
     */
    private function misspelling(QuizQuestion $question, array $entry): string
    {
        $answer = $entry['accepted'][0];

        if (mb_strlen($answer) < 4) {
            return $entry['wrong'][0];
        }

        $position = $this->random->int(1, mb_strlen($answer) - 2);
        $misspelled = mb_substr($answer, 0, $position).mb_substr($answer, $position + 1);

        return $question->isCorrectAnswer($misspelled) ? $entry['wrong'][0] : $misspelled;
    }

    /**
     * Insert the answers of the inserted attempts.
     *
     * @param  list<array{row: array<string, mixed>, answers: list<array<string, mixed>>}>  $attempts
     */
    private function seedAnswers(array $attempts): void
    {
        $attemptIds = [];

        foreach (QuizAttempt::query()->toBase()->get(['id', 'quiz_id', 'student_id', 'attempt_number']) as $attempt) {
            $attemptIds[$attempt->quiz_id.'|'.$attempt->student_id.'|'.$attempt->attempt_number] = (int) $attempt->id;
        }

        $rows = [];

        foreach ($attempts as $attempt) {
            $attemptId = $attemptIds[$attempt['row']['quiz_id'].'|'.$attempt['row']['student_id'].'|'.$attempt['row']['attempt_number']];

            foreach ($attempt['answers'] as $answer) {
                $rows[] = [
                    'quiz_attempt_id' => $attemptId,
                    ...$answer,
                    'created_at' => $attempt['row']['synced_at'],
                    'updated_at' => $attempt['row']['synced_at'],
                ];
            }
        }

        BulkInsert::models(QuizAttemptAnswer::class, $rows);
    }
}
