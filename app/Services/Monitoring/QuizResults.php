<?php

namespace App\Services\Monitoring;

use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Builds a quiz's results page and CSV export: headline figures, score histogram, attempts, learners who have not
 * attempted yet and the item analysis.
 *
 * Only submitted attempts count. "In a classroom" means the attempt was made in that classroom, or the game recorded
 * no classroom and the learner is enrolled there. Every figure is computed from a bounded number of queries; best
 * attempts and buckets are computed in PHP so the SQL stays database-agnostic.
 */
final class QuizResults
{
    /**
     * The number of histogram buckets of best percentages (each 10 points wide; the last one includes 100).
     */
    public const HISTOGRAM_BUCKETS = 10;

    /**
     * The number of attempts per page of the attempts table.
     */
    public const ATTEMPTS_PER_PAGE = 20;

    /**
     * The sortable attempt columns and their default direction.
     *
     * @var array<string, string>
     */
    public const SORTS = [
        'date' => 'desc',
        'score' => 'desc',
    ];

    /**
     * Get the quiz's classrooms the teacher can access (archived ones included), with subject, ordered by name.
     *
     * @return EloquentCollection<int, Classroom>
     */
    public function classroomsFor(Quiz $quiz, User $teacher): EloquentCollection
    {
        return $quiz->classrooms()
            ->accessibleBy($teacher)
            ->with('subject')
            ->orderBy('classrooms.name')
            ->orderBy('classrooms.id')
            ->get();
    }

    /**
     * Start a query of the quiz's submitted attempts, optionally limited to one classroom.
     *
     * @return HasMany<QuizAttempt, Quiz>
     */
    public function attempts(Quiz $quiz, ?Classroom $classroom = null): HasMany
    {
        return $quiz->attempts()
            ->submitted()
            ->when($classroom !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('quiz_attempts.classroom_id', $classroom->getKey())
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('quiz_attempts.classroom_id')
                    ->whereIn('quiz_attempts.student_id', DB::table('classroom_student')
                        ->select('student_id')
                        ->where('classroom_id', $classroom->getKey())))));
    }

    /**
     * Get one page of submitted attempts with learner, profile and classroom, sorted by date or score.
     *
     * @param  key-of<self::SORTS>  $sort
     * @param  'asc'|'desc'  $direction
     * @return LengthAwarePaginator<int, QuizAttempt>
     */
    public function attemptsPage(Quiz $quiz, ?Classroom $classroom, string $sort, string $direction): LengthAwarePaginator
    {
        $query = $this->attempts($quiz, $classroom)->with(['student.studentProfile', 'classroom']);

        if ($sort === 'score') {
            $query->orderBy('quiz_attempts.percentage', $direction)->orderByDesc('quiz_attempts.submitted_at');
        } else {
            $query->orderBy('quiz_attempts.submitted_at', $direction);
        }

        return $query->orderBy('quiz_attempts.id', $sort === 'score' ? 'desc' : $direction)
            ->paginate(self::ATTEMPTS_PER_PAGE)
            ->withQueryString();
    }

    /**
     * Summarize the quiz's results for the classrooms in scope.
     *
     * @param  EloquentCollection<int, Classroom>  $classrooms  the quiz's classrooms in scope
     */
    public function summarize(Quiz $quiz, EloquentCollection $classrooms, ?Classroom $classroom = null): QuizResultsSummary
    {
        $classroomIds = $classroom === null ? $classrooms->modelKeys() : [$classroom->getKey()];

        $assigned = User::query()
            ->ofRole(UserRole::Student)
            ->whereHas('enrolledClassrooms', fn (Builder $query) => $query->whereIn('classrooms.id', $classroomIds))
            ->with([
                'studentProfile',
                'enrolledClassrooms' => fn (BelongsToMany $query) => $query
                    ->whereIn('classrooms.id', $classroomIds)
                    ->orderBy('classrooms.name')
                    ->orderBy('classrooms.id'),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $attempts = $this->attempts($quiz, $classroom)->get([
            'quiz_attempts.id',
            'quiz_attempts.student_id',
            'quiz_attempts.percentage',
            'quiz_attempts.passed',
            'quiz_attempts.duration_seconds',
            'quiz_attempts.is_offline',
        ]);

        $best = $this->bestAttemptPerLearner($attempts);
        $attemptedIds = $best->keys()->all();
        $attemptedAssigned = $assigned->filter(fn (User $student): bool => in_array($student->getKey(), $attemptedIds, true))->count();
        $durations = $attempts->pluck('duration_seconds')->filter(fn (mixed $seconds): bool => $seconds !== null);

        return new QuizResultsSummary(
            assignedLearners: $assigned->count(),
            attemptedLearners: $attemptedAssigned,
            completionRate: $assigned->isEmpty() ? null : round($attemptedAssigned / $assigned->count() * 100, 1),
            learnersWithResults: $best->count(),
            submittedAttempts: $attempts->count(),
            averageScore: $best->isEmpty() ? null : round((float) $best->avg(fn (QuizAttempt $attempt): float => (float) $attempt->percentage), 1),
            passRate: $best->isEmpty() ? null : round($best->filter(fn (QuizAttempt $attempt): bool => $attempt->passed)->count() / $best->count() * 100, 1),
            averageDurationSeconds: $durations->isEmpty() ? null : (int) round((float) $durations->avg()),
            offlineSubmissions: $attempts->filter(fn (QuizAttempt $attempt): bool => $attempt->is_offline)->count(),
            histogram: $this->histogram($best),
            notAttempted: $assigned->reject(fn (User $student): bool => in_array($student->getKey(), $attemptedIds, true))->values(),
        );
    }

    /**
     * Analyze every question of the quiz over the submitted attempts in scope.
     *
     * @return list<ItemAnalysis>
     */
    public function itemAnalysis(Quiz $quiz, ?Classroom $classroom = null): array
    {
        $questions = $quiz->questions()->get();
        $responses = $this->attempts($quiz, $classroom)->count();

        $answerCounts = $questions->isEmpty() || $responses === 0
            ? collect()
            : QuizAttemptAnswer::query()
                ->whereIn('quiz_attempt_id', $this->attempts($quiz, $classroom)->getQuery()->select('quiz_attempts.id'))
                ->toBase()
                ->select(['quiz_question_id', 'answer', 'is_correct'])
                ->selectRaw('count(*) as aggregate')
                ->groupBy('quiz_question_id', 'answer', 'is_correct')
                ->get()
                ->groupBy(fn (object $row): int => (int) $row->quiz_question_id);

        return $questions->values()->map(function (QuizQuestion $question, int $index) use ($responses, $answerCounts): ItemAnalysis {
            $rows = $answerCounts->get($question->getKey(), collect());
            $correct = min($responses, (int) $rows->filter(fn (object $row): bool => (bool) $row->is_correct)->sum('aggregate'));
            $difficultyIndex = $responses === 0 ? null : $correct / $responses;
            [$wrongAnswer, $wrongCount] = $this->mostChosenWrongAnswer($question, $rows);

            return new ItemAnalysis(
                question: $question,
                number: $index + 1,
                responses: $responses,
                correct: $correct,
                difficultyIndex: $difficultyIndex,
                difficulty: $difficultyIndex === null ? null : ItemDifficulty::fromIndex($difficultyIndex),
                mostChosenWrongAnswer: $wrongAnswer,
                mostChosenWrongCount: $wrongCount,
            );
        })->all();
    }

    /**
     * Format a duration in seconds as m:ss (minutes are not capped at 59), or "—" when unknown.
     */
    public static function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $seconds = max(0, $seconds);

        return intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Pick each learner's best attempt: the highest percentage, then a passing one, then the latest.
     *
     * @param  EloquentCollection<int, QuizAttempt>  $attempts
     * @return Collection<int, QuizAttempt> keyed by learner id
     */
    private function bestAttemptPerLearner(EloquentCollection $attempts): Collection
    {
        return $attempts->toBase()
            ->groupBy(fn (QuizAttempt $attempt): int => (int) $attempt->student_id)
            ->map(fn (Collection $learnerAttempts): QuizAttempt => $learnerAttempts
                ->sortByDesc(fn (QuizAttempt $attempt): array => [(float) $attempt->percentage, $attempt->passed, $attempt->getKey()])
                ->first());
    }

    /**
     * Count the learners per 10-point bucket of best percentage (90–100 is the last bucket).
     *
     * @param  Collection<int, QuizAttempt>  $bestAttempts
     * @return list<array{label: string, value: int}>
     */
    private function histogram(Collection $bestAttempts): array
    {
        $counts = array_fill(0, self::HISTOGRAM_BUCKETS, 0);

        foreach ($bestAttempts as $attempt) {
            $percentage = max(0.0, min(100.0, (float) $attempt->percentage));
            $counts[min(self::HISTOGRAM_BUCKETS - 1, intdiv((int) floor($percentage), 10))]++;
        }

        return array_map(fn (int $bucket): array => [
            'label' => $bucket === self::HISTOGRAM_BUCKETS - 1 ? '90–100' : ($bucket * 10).'–'.($bucket * 10 + 9),
            'value' => $counts[$bucket],
        ], array_keys($counts));
    }

    /**
     * Find the most frequent non-blank wrong answer of a question and how many attempts gave it.
     *
     * Ties go to the lowest choice index (choice questions) or the alphabetically first text (identification).
     *
     * @param  Collection<int, object{answer: string|null, is_correct: int|bool|string, aggregate: int|string}>  $rows
     * @return array{0: string|null, 1: int}
     */
    private function mostChosenWrongAnswer(QuizQuestion $question, Collection $rows): array
    {
        $isIdentification = $question->type === QuestionType::Identification;

        $wrongAnswers = $rows
            ->reject(fn (object $row): bool => (bool) $row->is_correct || blank($row->answer))
            ->groupBy(fn (object $row): string => $isIdentification ? Str::lower(Str::squish((string) $row->answer)) : trim((string) $row->answer))
            ->map(fn (Collection $group): int => (int) $group->sum('aggregate'));

        if ($wrongAnswers->isEmpty()) {
            return [null, 0];
        }

        $answer = $wrongAnswers->keys()
            ->map(fn (int|string $key): string => (string) $key)
            ->sort(function (string $first, string $second) use ($wrongAnswers, $isIdentification): int {
                $byCount = $wrongAnswers[$second] <=> $wrongAnswers[$first];

                if ($byCount !== 0) {
                    return $byCount;
                }

                return ! $isIdentification && ctype_digit($first) && ctype_digit($second)
                    ? (int) $first <=> (int) $second
                    : strcmp($first, $second);
            })
            ->first();

        $text = $isIdentification ? $answer : $this->choiceText($question, $answer);

        return [$text, $wrongAnswers[$answer]];
    }

    /**
     * Get a stored choice answer as "B. Fish" (or the raw answer when it points at no choice).
     */
    private function choiceText(QuizQuestion $question, string $answer): string
    {
        $isChoice = ctype_digit($answer) && array_key_exists((int) $answer, $question->choices ?? []);

        return $isChoice
            ? $question->choiceLetter((int) $answer).'. '.$question->answerText($answer)
            : $question->answerText($answer);
    }
}
