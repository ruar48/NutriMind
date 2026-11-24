<?php

use App\Enums\QuestionType;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Create a published quiz assigned to the classroom.
 *
 * @param  array<string, mixed>  $quizAttributes
 * @param  array<string, mixed>  $assignment  classroom_quiz pivot values
 */
function gameQuizIn(Classroom $classroom, array $quizAttributes = [], array $assignment = []): Quiz
{
    $quiz = Quiz::factory()->published()->create(['subject_id' => $classroom->subject_id, ...$quizAttributes]);
    $quiz->classrooms()->attach($classroom, $assignment);

    return $quiz;
}

describe('index', function () {
    it('lists only the published quizzes of the learner\'s open classrooms', function () {
        $classroom = Classroom::factory()->create();
        $archivedClassroom = Classroom::factory()->archived()->create();
        $otherClassroom = Classroom::factory()->create();
        $student = User::factory()->student()->enrolledIn($classroom, $archivedClassroom)->create();

        $available = gameQuizIn($classroom, ['title' => 'Food Groups Check']);
        $opened = gameQuizIn($classroom, ['title' => 'Opened Yesterday'], ['available_from' => now()->subDay()]);
        gameQuizIn($classroom, ['title' => 'Not Yet Open'], ['available_from' => now()->addDay()]);
        gameQuizIn($classroom, ['title' => 'Closed Quiz', 'status' => 'closed']);
        Quiz::factory()->create(['title' => 'Draft Quiz'])->classrooms()->attach($classroom);
        gameQuizIn($archivedClassroom, ['title' => 'Archived Class Quiz']);
        gameQuizIn($otherClassroom, ['title' => 'Other Class Quiz']);
        Sanctum::actingAs($student);

        $this->getJson(route('game.quizzes.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.questions');

        expect(collect($this->getJson(route('game.quizzes.index'))->json('data'))->pluck('id')->sort()->values()->all())
            ->toBe(collect([$available->id, $opened->id])->sort()->values()->all());
    });

    it('counts the learner\'s own submitted attempts and shows the earliest due date', function () {
        $classroom = Classroom::factory()->create();
        $secondClassroom = Classroom::factory()->create(['subject_id' => $classroom->subject_id]);
        $student = User::factory()->student()->enrolledIn($classroom, $secondClassroom)->create();
        $quiz = gameQuizIn($classroom, ['max_attempts' => 3], ['due_at' => '2026-12-05 17:00:00']);
        $quiz->classrooms()->attach($secondClassroom, ['due_at' => '2026-12-01 17:00:00']);
        QuizQuestion::factory()->count(4)->for($quiz)->create();
        QuizAttempt::factory()->for($quiz)->create(['student_id' => $student->id]);
        QuizAttempt::factory()->for($quiz)->inProgress()->create(['student_id' => $student->id]);
        QuizAttempt::factory()->for($quiz)->create();
        Sanctum::actingAs($student);

        $this->getJson(route('game.quizzes.index'))
            ->assertOk()
            ->assertJsonPath('data.0.attempts_used', 1)
            ->assertJsonPath('data.0.attempts_left', 2)
            ->assertJsonPath('data.0.question_count', 4)
            ->assertJsonPath('data.0.due_at', '2026-12-01T17:00:00+08:00');
    });
});

describe('show', function () {
    it('shows the quiz questions without their correct answers', function () {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();
        $quiz = gameQuizIn($classroom, ['title' => 'Food Groups Check']);
        $multipleChoice = QuizQuestion::factory()->for($quiz)->create([
            'type' => QuestionType::MultipleChoice,
            'prompt' => 'Which food is a good source of protein?',
            'choices' => ['Rice', 'Fish', 'Candy'],
            'correct_answer' => '1',
            'points' => 2,
        ]);
        QuizQuestion::factory()->for($quiz)->create([
            'type' => QuestionType::Identification,
            'prompt' => 'Name the vitamin in oranges.',
            'choices' => null,
            'correct_answer' => 'Vitamin C',
        ]);
        Sanctum::actingAs($student);

        $this->getJson(route('game.quizzes.show', $quiz))
            ->assertOk()
            ->assertJsonPath('data.title', 'Food Groups Check')
            ->assertJsonPath('data.questions.0', [
                'id' => $multipleChoice->id,
                'type' => 'multiple_choice',
                'prompt' => 'Which food is a good source of protein?',
                'choices' => ['Rice', 'Fish', 'Candy'],
                'points' => 2,
                'image_url' => null,
                'video_url' => null,
            ])
            ->assertJsonPath('data.questions.1.choices', null)
            ->assertJsonMissingPath('data.questions.0.correct_answer')
            ->assertJsonMissingPath('data.questions.1.correct_answer');
    });

    it('answers 404 for a quiz the learner may not take', function (Closure $makeQuiz) {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();
        $quiz = $makeQuiz($classroom);
        Sanctum::actingAs($student);

        $this->getJson(route('game.quizzes.show', $quiz))->assertNotFound();
    })->with([
        'another classroom\'s quiz' => fn (Classroom $classroom): Quiz => gameQuizIn(Classroom::factory()->create()),
        'a draft' => fn (Classroom $classroom): Quiz => tap(Quiz::factory()->create(), fn (Quiz $quiz) => $quiz->classrooms()->attach($classroom)),
        'a quiz that opens tomorrow' => fn (Classroom $classroom): Quiz => gameQuizIn($classroom, [], ['available_from' => now()->addDay()]),
    ]);
});
