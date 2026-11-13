<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Policies\QuizPolicy;
use App\Services\RolePermissions;

/**
 * Create a teacher with a draft quiz that has one multiple choice question with an explanation.
 *
 * @return array{0: User, 1: Quiz}
 */
function quizReviewScenario(): array
{
    $teacher = User::factory()->teaching()->create();
    $quiz = Quiz::factory()->for($teacher, 'teacher')->for($teacher->assignedSubjects->first())->create([
        'title' => 'Healthy Snacks Quiz',
        'instructions' => 'Pick the healthiest snack.',
        'grade_level' => GradeLevel::Five,
    ]);
    QuizQuestion::factory()->for($quiz)->create([
        'prompt' => 'Which snack is the healthiest choice?',
        'choices' => ['A banana', 'A bag of chips'],
        'correct_answer' => '0',
        'explanation' => 'Fruit has vitamins and fiber.',
        'points' => 2,
    ]);

    return [$teacher, $quiz];
}

describe('show', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.quizzes.review.show', Quiz::factory()->create()))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('teacher.quizzes.review.show', Quiz::factory()->create()))
            ->assertForbidden();
    })->with(['admin', 'parent', 'student']);

    it('previews the quiz as learners see it, without the answers', function () {
        [$teacher, $quiz] = quizReviewScenario();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.review.show', $quiz))
            ->assertOk()
            ->assertViewIs('teacher.quizzes.review')
            ->assertViewHas('showAnswers', false)
            ->assertSeeText('Pick the healthiest snack.')
            ->assertSeeText('Question 1 of 1')
            ->assertSeeText('Which snack is the healthiest choice?')
            ->assertSeeText('A bag of chips')
            ->assertSeeText('2 points')
            ->assertSee('data-answer-key="hidden"', false)
            ->assertDontSee('data-correct-choice', false)
            ->assertDontSeeText('Fruit has vitamins and fiber.')
            ->assertSee(route('teacher.quizzes.review.store', $quiz));
    });

    it('shows the answer key and explanations on request', function () {
        [$teacher, $quiz] = quizReviewScenario();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.review.show', [$quiz, 'answers' => 1]))
            ->assertOk()
            ->assertViewHas('showAnswers', true)
            ->assertSee('data-correct-choice', false)
            ->assertSeeText('Fruit has vitamins and fiber.')
            ->assertSeeText('Hide answers');
    });

    it('shows an empty state for a quiz without questions', function () {
        $teacher = User::factory()->teaching()->create();
        $quiz = Quiz::factory()->for($teacher, 'teacher')->for($teacher->assignedSubjects->first())->create();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.review.show', $quiz))
            ->assertOk()
            ->assertSeeText('Nothing to preview yet');
    });

    it('hides other teachers’ quizzes', function () {
        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.quizzes.review.show', Quiz::factory()->create()))
            ->assertNotFound();
    });

    it('forbids the owner once the subject was revoked', function () {
        $quiz = Quiz::factory()->revokedSubject()->create();

        $this->actingAs($quiz->teacher)
            ->get(route('teacher.quizzes.review.show', $quiz))
            ->assertForbidden()
            ->assertSeeText(QuizPolicy::SUBJECT_REVOKED_MESSAGE);
    });
});

describe('store', function () {
    it('marks the draft as reviewed', function (bool $withClassroom, string $message) {
        $this->freezeSecond();
        [$teacher, $quiz] = quizReviewScenario();
        if ($withClassroom) {
            $quiz->classrooms()->attach(Classroom::factory()->for($teacher, 'teacher')->for($quiz->subject)->create(['grade_level' => GradeLevel::Five]));
        }

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.review.store', $quiz))
            ->assertRedirectToRoute('teacher.quizzes.show', $quiz)
            ->assertSessionHas('status', $message);

        expect($quiz->refresh()->reviewed_at->toDateTimeString())->toBe(now()->toDateTimeString());
    })->with([
        'with a classroom' => [true, 'Quiz marked as reviewed. You can publish it now.'],
        'without a classroom' => [false, 'Quiz marked as reviewed. Assign it to a classroom to publish it.'],
    ]);

    it('needs at least one question', function () {
        $teacher = User::factory()->teaching()->create();
        $quiz = Quiz::factory()->for($teacher, 'teacher')->for($teacher->assignedSubjects->first())->create();

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.review.store', $quiz))
            ->assertRedirectToRoute('teacher.quizzes.review.show', $quiz)
            ->assertSessionHas('error', 'Add at least one question before marking the quiz as reviewed.');

        expect($quiz->refresh()->reviewed_at)->toBeNull();
    });

    it('only reviews drafts', function () {
        $teacher = User::factory()->teaching()->create();
        $quiz = Quiz::factory()->closed()->for($teacher, 'teacher')->for($teacher->assignedSubjects->first())->hasQuestions(1)->create(['reviewed_at' => '2026-10-01 08:00:00']);

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.review.store', $quiz))
            ->assertRedirectToRoute('teacher.quizzes.show', $quiz)
            ->assertSessionHas('error', 'Only draft quizzes are reviewed. This quiz is already closed.');

        expect($quiz->refresh()->reviewed_at->toDateTimeString())->toBe('2026-10-01 08:00:00');
    });

    it('forbids teachers without the quiz permission', function () {
        [$teacher, $quiz] = quizReviewScenario();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes);

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.review.store', $quiz))
            ->assertForbidden();

        expect($quiz->refresh()->reviewed_at)->toBeNull();
    });

    it('hides other teachers’ quizzes', function () {
        [, $quiz] = quizReviewScenario();

        $this->actingAs(User::factory()->teaching()->create())
            ->post(route('teacher.quizzes.review.store', $quiz))
            ->assertNotFound();
    });
});
