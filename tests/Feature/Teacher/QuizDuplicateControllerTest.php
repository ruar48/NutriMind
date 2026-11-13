<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\QuizStatus;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Policies\QuizPolicy;
use App\Services\RolePermissions;
use Illuminate\Support\Facades\Storage;

it('sends guests to the sign-in page', function () {
    $this->post(route('teacher.quizzes.duplicate.store', Quiz::factory()->create()))->assertRedirectToRoute('login');
});

it('forbids other roles', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->post(route('teacher.quizzes.duplicate.store', Quiz::factory()->create()))
        ->assertForbidden();
})->with(['admin', 'parent', 'student']);

it('copies the quiz as an unassigned, unreviewed draft with copies of its questions and media', function () {
    Storage::fake('public');
    $teacher = User::factory()->teaching()->create();
    $subject = $teacher->assignedSubjects->first();
    $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create([
        'title' => 'Food Label Challenge',
        'grade_level' => GradeLevel::Six,
        'instructions' => 'Read the labels.',
        'time_limit_minutes' => 15,
        'passing_score' => 80,
        'max_attempts' => null,
        'shuffle_questions' => true,
        'show_correct_answers' => false,
    ]);
    $quiz->classrooms()->attach(Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Six]));
    $bankItem = QuestionBankItem::factory()->shared()->for($subject)->create(['times_used' => 4]);
    Storage::disk('public')->put('quiz-media/images/label.png', 'image');
    $second = QuizQuestion::factory()->for($quiz)->create(['sort_order' => 7, 'points' => 3, 'question_bank_item_id' => $bankItem->id]);
    $first = QuizQuestion::factory()->trueFalse()->for($quiz)->create(['sort_order' => 2, 'image_path' => 'quiz-media/images/label.png', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ']);
    QuizAttempt::factory()->for($quiz)->create();

    $response = $this->actingAs($teacher)->post(route('teacher.quizzes.duplicate.store', $quiz));

    $copy = Quiz::query()->whereKeyNot($quiz->id)->sole();
    $response->assertRedirectToRoute('teacher.quizzes.show', $copy)
        ->assertSessionHas('status', 'Quiz duplicated. The copy is a draft without classrooms: review it, assign classrooms, then publish.');
    expect($copy)
        ->title->toBe('Copy of Food Label Challenge')
        ->teacher_id->toBe($teacher->id)
        ->subject_id->toBe($subject->id)
        ->grade_level->toBe(GradeLevel::Six)
        ->instructions->toBe('Read the labels.')
        ->time_limit_minutes->toBe(15)
        ->passing_score->toBe(80)
        ->max_attempts->toBeNull()
        ->shuffle_questions->toBeTrue()
        ->show_correct_answers->toBeFalse()
        ->status->toBe(QuizStatus::Draft)
        ->reviewed_at->toBeNull()
        ->published_at->toBeNull()
        ->and($copy->classrooms()->count())->toBe(0)
        ->and($copy->attempts()->count())->toBe(0);

    [$copiedFirst, $copiedSecond] = $copy->questions()->get()->all();
    expect($copiedFirst)
        ->prompt->toBe($first->prompt)
        ->choices->toBe(['True', 'False'])
        ->correct_answer->toBe($first->correct_answer)
        ->sort_order->toBe(1)
        ->video_url->toBe('https://youtu.be/dQw4w9WgXcQ')
        ->and($copiedFirst->image_path)->not->toBe('quiz-media/images/label.png')->toStartWith('quiz-media/images/')
        ->and($copiedSecond)
        ->prompt->toBe($second->prompt)
        ->points->toBe(3)
        ->sort_order->toBe(2)
        ->question_bank_item_id->toBe($bankItem->id);
    Storage::disk('public')->assertExists([$copiedFirst->image_path, 'quiz-media/images/label.png']);
    expect($bankItem->refresh()->times_used)->toBe(5)
        ->and($quiz->refresh()->status)->toBe(QuizStatus::Published);
});

it('keeps the copy’s title within 150 characters', function () {
    $teacher = User::factory()->teaching()->create();
    $quiz = Quiz::factory()->for($teacher, 'teacher')->for($teacher->assignedSubjects->first())->create(['title' => str_repeat('Healthy ', 18).'Quiz']);

    $this->actingAs($teacher)->post(route('teacher.quizzes.duplicate.store', $quiz));

    $copy = Quiz::query()->whereKeyNot($quiz->id)->sole();
    expect(mb_strlen($copy->title))->toBeLessThanOrEqual(150)
        ->and($copy->title)->toStartWith('Copy of Healthy');
});

it('leaves out a media file that no longer exists', function () {
    Storage::fake('public');
    $teacher = User::factory()->teaching()->create();
    $quiz = Quiz::factory()->for($teacher, 'teacher')->for($teacher->assignedSubjects->first())->create();
    QuizQuestion::factory()->for($quiz)->create(['image_path' => 'quiz-media/images/missing.png']);

    $this->actingAs($teacher)->post(route('teacher.quizzes.duplicate.store', $quiz))->assertSessionHasNoErrors();

    expect(Quiz::query()->whereKeyNot($quiz->id)->sole()->questions()->sole()->image_path)->toBeNull();
});

it('hides other teachers’ quizzes', function () {
    $quiz = Quiz::factory()->create();

    $this->actingAs(User::factory()->teaching()->create())
        ->post(route('teacher.quizzes.duplicate.store', $quiz))
        ->assertNotFound();

    expect(Quiz::query()->count())->toBe(1);
});

it('forbids the owner once the subject was revoked', function () {
    $quiz = Quiz::factory()->revokedSubject()->create();

    $this->actingAs($quiz->teacher)
        ->post(route('teacher.quizzes.duplicate.store', $quiz))
        ->assertForbidden()
        ->assertSeeText(QuizPolicy::SUBJECT_REVOKED_MESSAGE);
});

it('forbids teachers without the quiz permission', function () {
    $quiz = Quiz::factory()->create();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes);

    $this->actingAs($quiz->teacher)
        ->post(route('teacher.quizzes.duplicate.store', $quiz))
        ->assertForbidden();

    expect(Quiz::query()->count())->toBe(1);
});
