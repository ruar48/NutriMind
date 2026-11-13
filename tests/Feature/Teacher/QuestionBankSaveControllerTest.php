<?php

use App\Enums\Difficulty;
use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\LearningModule;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Policies\QuizPolicy;
use App\Services\RolePermissions;
use Illuminate\Support\Facades\Storage;

it('sends guests to the sign-in page', function () {
    $question = QuizQuestion::factory()->create();

    $this->post(route('teacher.quizzes.questions.bank.store', [$question->quiz, $question]))->assertRedirectToRoute('login');
});

it('forbids other roles', function (string $role) {
    $question = QuizQuestion::factory()->create();

    $this->actingAs(User::factory()->{$role}()->create())
        ->post(route('teacher.quizzes.questions.bank.store', [$question->quiz, $question]))
        ->assertForbidden();
})->with(['admin', 'parent', 'student']);

it('saves a copy of the question to the teacher’s bank with copies of its media', function () {
    Storage::fake('public');
    $this->freezeSecond();
    $module = LearningModule::factory()->create(['grade_level' => GradeLevel::Six]);
    $teacher = User::factory()->teaching($module->subject)->create();
    $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($module, 'module')->create();
    Storage::disk('public')->put('quiz-media/images/plate.png', 'image');
    Storage::disk('public')->put('quiz-media/videos/plate.mp4', 'video');
    $question = QuizQuestion::factory()->identification()->for($quiz)->create([
        'prompt' => 'Name a Glow food.',
        'correct_answer' => 'Carrot|Malunggay',
        'explanation' => 'Vegetables are Glow foods.',
        'image_path' => 'quiz-media/images/plate.png',
        'video_path' => 'quiz-media/videos/plate.mp4',
        'video_url' => null,
    ]);

    $this->actingAs($teacher)
        ->post(route('teacher.quizzes.questions.bank.store', [$quiz, $question]))
        ->assertRedirect(route('teacher.quizzes.show', $quiz).'#question-'.$question->id)
        ->assertSessionHas('status', 'Question saved to your question bank.');

    $item = QuestionBankItem::query()->sole();
    expect($item)
        ->teacher_id->toBe($teacher->id)
        ->subject_id->toBe($module->subject_id)
        ->grade_level->toBe(GradeLevel::Six)
        ->learning_module_id->toBe($module->id)
        ->type->toBe(QuestionType::Identification)
        ->prompt->toBe('Name a Glow food.')
        ->correct_answer->toBe('Carrot|Malunggay')
        ->explanation->toBe('Vegetables are Glow foods.')
        ->difficulty->toBe(Difficulty::Easy)
        ->is_shared->toBeFalse()
        ->times_used->toBe(0)
        ->and($item->reviewed_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($item->image_path)->not->toBe('quiz-media/images/plate.png')->toStartWith('quiz-media/images/')
        ->and($item->video_path)->not->toBe('quiz-media/videos/plate.mp4')->toStartWith('quiz-media/videos/');
    Storage::disk('public')->assertExists([$item->image_path, $item->video_path, 'quiz-media/images/plate.png', 'quiz-media/videos/plate.mp4']);
    expect($question->refresh()->question_bank_item_id)->toBeNull();
});

it('saves the question as not reviewed when the quiz was not reviewed', function () {
    $teacher = User::factory()->teaching()->create();
    $quiz = Quiz::factory()->for($teacher, 'teacher')->for($teacher->assignedSubjects->first())->create();
    $question = QuizQuestion::factory()->for($quiz)->create();

    $this->actingAs($teacher)->post(route('teacher.quizzes.questions.bank.store', [$quiz, $question]));

    expect(QuestionBankItem::query()->sole()->reviewed_at)->toBeNull();
});

it('returns 404 for a question of another quiz', function () {
    $teacher = User::factory()->teaching()->create();
    $subject = $teacher->assignedSubjects->first();
    $quiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create();
    $foreign = QuizQuestion::factory()->for(Quiz::factory()->for($teacher, 'teacher')->for($subject))->create();

    $this->actingAs($teacher)
        ->post(route('teacher.quizzes.questions.bank.store', [$quiz, $foreign]))
        ->assertNotFound();

    expect(QuestionBankItem::query()->count())->toBe(0);
});

it('hides other teachers’ quizzes', function () {
    $question = QuizQuestion::factory()->create();

    $this->actingAs(User::factory()->teaching()->create())
        ->post(route('teacher.quizzes.questions.bank.store', [$question->quiz, $question]))
        ->assertNotFound();
});

it('forbids teachers who may not use the question bank', function () {
    $question = QuizQuestion::factory()->create();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageQuestionBank);

    $this->actingAs($question->quiz->teacher)
        ->post(route('teacher.quizzes.questions.bank.store', [$question->quiz, $question]))
        ->assertForbidden()
        ->assertSeeText(RolePermissions::deniedMessage(Permission::ManageQuestionBank));

    expect(QuestionBankItem::query()->count())->toBe(0);
});

it('forbids the owner once the subject was revoked', function () {
    $quiz = Quiz::factory()->revokedSubject()->create();
    $question = QuizQuestion::factory()->for($quiz)->create();

    $this->actingAs($quiz->teacher)
        ->post(route('teacher.quizzes.questions.bank.store', [$quiz, $question]))
        ->assertForbidden()
        ->assertSeeText(QuizPolicy::SUBJECT_REVOKED_MESSAGE);
});
