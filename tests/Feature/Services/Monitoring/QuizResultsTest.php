<?php

use App\Enums\GradeLevel;
use App\Enums\QuestionType;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\User;
use App\Services\Monitoring\ItemAnalysis;
use App\Services\Monitoring\ItemDifficulty;
use App\Services\Monitoring\QuizResults;

/**
 * A quiz (passing score 75) assigned to one classroom, with a helper that records a submitted attempt and its answers.
 *
 * @return array{teacher: User, classroom: Classroom, quiz: Quiz, attempt: Closure(User, float, array<int, string|null>, array<string, mixed>): QuizAttempt}
 */
function quizResultsServiceSetup(): array
{
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
    $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['passing_score' => 75]);
    $quiz->classrooms()->attach($classroom);

    $attempt = function (User $student, float $percentage, array $answers = [], array $attributes = []) use ($quiz, $classroom): QuizAttempt {
        $attempt = QuizAttempt::factory()->for($quiz)->for($student, 'student')->create([
            'classroom_id' => $classroom->id,
            'score' => (int) round($percentage),
            'max_score' => 100,
            'percentage' => $percentage,
            'passed' => $percentage >= 75,
        ] + $attributes);

        foreach ($answers as $questionId => $answer) {
            $question = QuizQuestion::query()->findOrFail($questionId);
            QuizAttemptAnswer::factory()->for($attempt, 'attempt')->for($question, 'question')->create([
                'answer' => $answer,
                'is_correct' => $question->isCorrectAnswer($answer),
                'points_awarded' => $question->isCorrectAnswer($answer) ? $question->points : 0,
            ]);
        }

        return $attempt;
    };

    return compact('teacher', 'classroom', 'quiz', 'attempt');
}

function quizResultsLearner(Classroom $classroom, string $name = 'Learner'): User
{
    return User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create(['name' => $name]);
}

it('buckets each learner’s best percentage into ten 10-point ranges', function () {
    $data = quizResultsServiceSetup();
    foreach ([0, 9.99, 10, 74.99, 75, 89.99, 90, 100] as $index => $percentage) {
        ($data['attempt'])(quizResultsLearner($data['classroom'], 'Learner '.$index), $percentage);
    }

    $summary = app(QuizResults::class)->summarize($data['quiz'], $data['quiz']->classrooms()->get());

    expect($summary->histogram)->toBe([
        ['label' => '0–9', 'value' => 2],
        ['label' => '10–19', 'value' => 1],
        ['label' => '20–29', 'value' => 0],
        ['label' => '30–39', 'value' => 0],
        ['label' => '40–49', 'value' => 0],
        ['label' => '50–59', 'value' => 0],
        ['label' => '60–69', 'value' => 0],
        ['label' => '70–79', 'value' => 2],
        ['label' => '80–89', 'value' => 1],
        ['label' => '90–100', 'value' => 2],
    ]);
});

it('uses each learner’s best attempt: the highest percentage, then a passing one', function () {
    $data = quizResultsServiceSetup();
    $ana = quizResultsLearner($data['classroom'], 'Ana');
    $ben = quizResultsLearner($data['classroom'], 'Ben');
    ($data['attempt'])($ana, 40);
    ($data['attempt'])($ana, 80, [], ['attempt_number' => 2]);
    ($data['attempt'])($ana, 70, [], ['attempt_number' => 3]);
    ($data['attempt'])($ben, 70, [], ['passed' => false]);
    ($data['attempt'])($ben, 70, [], ['attempt_number' => 2, 'passed' => true]);

    $summary = app(QuizResults::class)->summarize($data['quiz'], $data['quiz']->classrooms()->get());

    expect($summary->averageScore)->toBe(75.0)
        ->and($summary->passRate)->toBe(100.0)
        ->and($summary->learnersWithResults)->toBe(2)
        ->and($summary->submittedAttempts)->toBe(5);
});

it('computes completion, duration and offline figures', function () {
    $data = quizResultsServiceSetup();
    $ana = quizResultsLearner($data['classroom'], 'Ana');
    $ben = quizResultsLearner($data['classroom'], 'Ben');
    quizResultsLearner($data['classroom'], 'Carlo');
    $outsider = User::factory()->student()->create(['name' => 'Moved Away']);
    ($data['attempt'])($ana, 90, [], ['duration_seconds' => 100, 'is_offline' => true]);
    ($data['attempt'])($ana, 60, [], ['attempt_number' => 2, 'duration_seconds' => 201]);
    ($data['attempt'])($ben, 50, [], ['duration_seconds' => null]);
    ($data['attempt'])($outsider, 100, [], ['duration_seconds' => 300]);
    ($data['attempt'])(quizResultsLearner($data['classroom'], 'Dina'), 99, [], ['submitted_at' => null]);

    $summary = app(QuizResults::class)->summarize($data['quiz'], $data['quiz']->classrooms()->get());

    expect($summary->assignedLearners)->toBe(4)
        ->and($summary->attemptedLearners)->toBe(2)
        ->and($summary->completionRate)->toBe(50.0)
        ->and($summary->learnersWithResults)->toBe(3)
        ->and($summary->averageScore)->toBe(80.0)
        ->and($summary->passRate)->toBe(66.7)
        ->and($summary->averageDurationSeconds)->toBe(200)
        ->and($summary->offlineSubmissions)->toBe(1)
        ->and($summary->notAttempted->pluck('name')->all())->toBe(['Carlo', 'Dina']);
});

it('counts an attempt without a recorded classroom for the classroom its learner is enrolled in', function () {
    $data = quizResultsServiceSetup();
    $other = Classroom::factory()->for($data['teacher'], 'teacher')->for($data['quiz']->subject)->create();
    $data['quiz']->classrooms()->attach($other);
    $ana = quizResultsLearner($data['classroom'], 'Ana');
    $ben = quizResultsLearner($other, 'Ben');
    ($data['attempt'])($ana, 80, [], ['classroom_id' => null]);
    ($data['attempt'])($ben, 60, [], ['classroom_id' => $other->id]);
    ($data['attempt'])($ben, 100, [], ['classroom_id' => $data['classroom']->id, 'attempt_number' => 2]);

    $results = app(QuizResults::class);

    expect($results->attempts($data['quiz'], $data['classroom'])->pluck('percentage')->map(fn ($value) => (float) $value)->sort()->values()->all())->toBe([80.0, 100.0])
        ->and($results->attempts($data['quiz'], $other)->count())->toBe(1)
        ->and($results->attempts($data['quiz'])->count())->toBe(3);
});

it('returns empty figures for a quiz without attempts or learners', function () {
    $data = quizResultsServiceSetup();

    $summary = app(QuizResults::class)->summarize($data['quiz'], $data['quiz']->classrooms()->get());

    expect($summary->assignedLearners)->toBe(0)
        ->and($summary->completionRate)->toBeNull()
        ->and($summary->averageScore)->toBeNull()
        ->and($summary->passRate)->toBeNull()
        ->and($summary->averageDurationSeconds)->toBeNull()
        ->and(array_sum(array_column($summary->histogram, 'value')))->toBe(0);
});

it('analyzes each question’s difficulty and most-chosen wrong answer', function () {
    $data = quizResultsServiceSetup();
    $choice = QuizQuestion::factory()->for($data['quiz'])->create([
        'type' => QuestionType::MultipleChoice, 'choices' => ['Go', 'Grow', 'Glow', 'Fats'], 'correct_answer' => '1', 'sort_order' => 1,
    ]);
    $trueFalse = QuizQuestion::factory()->for($data['quiz'])->create([
        'type' => QuestionType::TrueFalse, 'choices' => ['True', 'False'], 'correct_answer' => '0', 'sort_order' => 2,
    ]);
    $typed = QuizQuestion::factory()->for($data['quiz'])->create([
        'type' => QuestionType::Identification, 'choices' => null, 'correct_answer' => 'water|tubig', 'sort_order' => 3,
    ]);
    $answers = [
        ['1', '0', 'Water'],
        ['2', '0', 'Juice'],
        ['2', '1', '  juice '],
        ['0', '0', 'SODA'],
        ['1', null, 'soda'],
    ];
    foreach ($answers as $index => [$choiceAnswer, $trueFalseAnswer, $typedAnswer]) {
        ($data['attempt'])(quizResultsLearner($data['classroom'], 'Learner '.$index), 50, array_filter([
            $choice->id => $choiceAnswer,
            $trueFalse->id => $trueFalseAnswer,
            $typed->id => $typedAnswer,
        ], fn (?string $answer): bool => $answer !== null));
    }

    $items = collect(app(QuizResults::class)->itemAnalysis($data['quiz']))->keyBy(fn (ItemAnalysis $item): int => $item->number);

    expect($items[1])
        ->responses->toBe(5)
        ->correct->toBe(2)
        ->difficultyIndex->toBe(0.4)
        ->difficulty->toBe(ItemDifficulty::Moderate)
        ->mostChosenWrongAnswer->toBe('C. Glow')
        ->mostChosenWrongCount->toBe(2)
        ->and($items[1]->percentCorrect())->toBe(40.0);
    expect($items[2])
        ->correct->toBe(3)
        ->difficultyIndex->toBe(0.6)
        ->mostChosenWrongAnswer->toBe('B. False')
        ->mostChosenWrongCount->toBe(1);
    expect($items[3])
        ->correct->toBe(1)
        ->difficulty->toBe(ItemDifficulty::Difficult)
        ->mostChosenWrongAnswer->toBe('juice')
        ->mostChosenWrongCount->toBe(2);
});

it('breaks ties between wrong answers by choice order or alphabetically', function () {
    $data = quizResultsServiceSetup();
    $choice = QuizQuestion::factory()->for($data['quiz'])->create([
        'type' => QuestionType::MultipleChoice, 'choices' => ['Go', 'Grow', 'Glow', 'Fats'], 'correct_answer' => '0', 'sort_order' => 1,
    ]);
    $typed = QuizQuestion::factory()->for($data['quiz'])->create([
        'type' => QuestionType::Identification, 'choices' => null, 'correct_answer' => 'water', 'sort_order' => 2,
    ]);
    ($data['attempt'])(quizResultsLearner($data['classroom']), 0, [$choice->id => '3', $typed->id => 'milk']);
    ($data['attempt'])(quizResultsLearner($data['classroom']), 0, [$choice->id => '2', $typed->id => 'juice']);

    $items = app(QuizResults::class)->itemAnalysis($data['quiz']);

    expect($items[0]->mostChosenWrongAnswer)->toBe('C. Glow')
        ->and($items[1]->mostChosenWrongAnswer)->toBe('juice');
});

it('reports no difficulty before anyone attempted the quiz', function () {
    $data = quizResultsServiceSetup();
    QuizQuestion::factory()->for($data['quiz'])->create();

    $items = app(QuizResults::class)->itemAnalysis($data['quiz']);

    expect($items)->toHaveCount(1)
        ->and($items[0]->difficultyIndex)->toBeNull()
        ->and($items[0]->difficulty)->toBeNull()
        ->and($items[0]->percentCorrect())->toBeNull()
        ->and($items[0]->mostChosenWrongAnswer)->toBeNull();
});

it('labels difficulty indexes at the boundaries', function (float $index, ItemDifficulty $difficulty) {
    expect(ItemDifficulty::fromIndex($index))->toBe($difficulty);
})->with([
    'all correct' => [1.0, ItemDifficulty::Easy],
    'exactly 0.75' => [0.75, ItemDifficulty::Easy],
    'just below 0.75' => [0.7499, ItemDifficulty::Moderate],
    'exactly 0.40' => [0.40, ItemDifficulty::Moderate],
    'just below 0.40' => [0.3999, ItemDifficulty::Difficult],
    'none correct' => [0.0, ItemDifficulty::Difficult],
]);

it('formats durations as minutes and seconds', function (?int $seconds, string $expected) {
    expect(QuizResults::formatDuration($seconds))->toBe($expected);
})->with([
    'unknown' => [null, '—'],
    'zero' => [0, '0:00'],
    'under a minute' => [7, '0:07'],
    'minutes and seconds' => [305, '5:05'],
    'over an hour' => [3725, '62:05'],
]);
