<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\RolePermissions;

/**
 * Create a reviewed draft quiz whose three questions have the sort orders 1, 5 and 9.
 *
 * @return array{0: User, 1: Quiz, 2: list<QuizQuestion>}
 */
function quizPositionScenario(): array
{
    $teacher = User::factory()->teaching()->create();
    $quiz = Quiz::factory()->reviewed()->for($teacher, 'teacher')->for($teacher->assignedSubjects->first())->create();
    $questions = collect([1, 5, 9])->map(fn (int $sortOrder) => QuizQuestion::factory()->for($quiz)->create(['sort_order' => $sortOrder]))->all();

    return [$teacher, $quiz, $questions];
}

/**
 * Get the ids of the quiz's questions in display order.
 *
 * @return list<int>
 */
function quizPositionOrder(Quiz $quiz): array
{
    return $quiz->questions()->pluck('id')->all();
}

it('sends guests to the sign-in page', function () {
    $question = QuizQuestion::factory()->create();

    $this->put(route('teacher.quizzes.questions.position.update', [$question->quiz, $question]), ['direction' => 'up'])
        ->assertRedirectToRoute('login');
});

it('forbids other roles', function (string $role) {
    $question = QuizQuestion::factory()->create();

    $this->actingAs(User::factory()->{$role}()->create())
        ->put(route('teacher.quizzes.questions.position.update', [$question->quiz, $question]), ['direction' => 'up'])
        ->assertForbidden();
})->with(['admin', 'parent', 'student']);

it('moves a question up, numbers the questions again and asks for a new review', function () {
    [$teacher, $quiz, [$first, $second, $third]] = quizPositionScenario();

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.questions.position.update', [$quiz, $third]), ['direction' => 'up'])
        ->assertRedirect(route('teacher.quizzes.show', $quiz).'#question-'.$third->id)
        ->assertSessionHas('status', 'Question moved up.');

    expect(quizPositionOrder($quiz))->toBe([$first->id, $third->id, $second->id])
        ->and($quiz->questions()->pluck('sort_order')->all())->toBe([1, 2, 3])
        ->and($quiz->refresh()->reviewed_at)->toBeNull();
});

it('moves a question down', function () {
    [$teacher, $quiz, [$first, $second, $third]] = quizPositionScenario();

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.questions.position.update', [$quiz, $first]), ['direction' => 'down'])
        ->assertSessionHas('status', 'Question moved down.');

    expect(quizPositionOrder($quiz))->toBe([$second->id, $first->id, $third->id]);
});

it('refuses to move the first question up or the last one down', function (int $index, string $direction, string $message) {
    [$teacher, $quiz, $questions] = quizPositionScenario();
    $before = quizPositionOrder($quiz);

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.questions.position.update', [$quiz, $questions[$index]]), ['direction' => $direction])
        ->assertRedirect(route('teacher.quizzes.show', $quiz).'#question-'.$questions[$index]->id)
        ->assertSessionHas('error', $message);

    expect(quizPositionOrder($quiz))->toBe($before)
        ->and($quiz->refresh()->reviewed_at)->not->toBeNull();
})->with([
    'first up' => [0, 'up', 'This question is already the first one.'],
    'last down' => [2, 'down', 'This question is already the last one.'],
]);

it('requires a direction', function (?string $direction) {
    [$teacher, $quiz, $questions] = quizPositionScenario();

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.questions.position.update', [$quiz, $questions[1]]), ['direction' => $direction])
        ->assertSessionHasErrors(['direction' => 'Choose whether to move the question up or down.']);
})->with(['missing' => [null], 'sideways' => ['left']]);

it('does not reorder the questions of a published quiz', function () {
    [$teacher, $quiz, $questions] = quizPositionScenario();
    $quiz->forceFill(['status' => 'published'])->save();

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.questions.position.update', [$quiz, $questions[1]]), ['direction' => 'up'])
        ->assertRedirectToRoute('teacher.quizzes.show', $quiz)
        ->assertSessionHas('error', 'Unpublish the quiz to edit its questions.');

    expect($quiz->questions()->pluck('sort_order')->all())->toBe([1, 5, 9]);
});

it('does not reorder the questions once learners attempted the quiz', function () {
    [$teacher, $quiz, $questions] = quizPositionScenario();
    QuizAttempt::factory()->for($quiz)->create();

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.questions.position.update', [$quiz, $questions[1]]), ['direction' => 'up'])
        ->assertSessionHas('error', 'Learners have already taken this quiz. Duplicate it to make changes.');
});

it('returns 404 for a question of another quiz', function () {
    [$teacher, $quiz] = quizPositionScenario();
    $foreign = QuizQuestion::factory()->for(Quiz::factory()->for($teacher, 'teacher')->for($quiz->subject))->create();

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.questions.position.update', [$quiz, $foreign]), ['direction' => 'up'])
        ->assertNotFound();
});

it('hides other teachers’ quizzes', function () {
    [, $quiz, $questions] = quizPositionScenario();

    $this->actingAs(User::factory()->teaching()->create())
        ->put(route('teacher.quizzes.questions.position.update', [$quiz, $questions[1]]), ['direction' => 'up'])
        ->assertNotFound();
});

it('forbids teachers without the quiz permission', function () {
    [$teacher, $quiz, $questions] = quizPositionScenario();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes);

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.questions.position.update', [$quiz, $questions[1]]), ['direction' => 'up'])
        ->assertForbidden();

    expect($quiz->questions()->pluck('sort_order')->all())->toBe([1, 5, 9]);
});
