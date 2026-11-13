<?php

use App\Enums\Difficulty;
use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\LearningModule;
use App\Models\QuestionBankItem;
use App\Models\Subject;
use App\Models\User;
use App\Services\RolePermissions;
use Illuminate\Support\Facades\Storage;

it('sends guests to the sign-in page', function () {
    $this->post(route('teacher.question-bank.duplicate.store', QuestionBankItem::factory()->create()))->assertRedirectToRoute('login');
});

it('forbids other roles', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->post(route('teacher.question-bank.duplicate.store', QuestionBankItem::factory()->create()))
        ->assertForbidden();
})->with(['admin', 'parent', 'student']);

it('copies a shared item into the teacher’s bank as a private, unreviewed, unused item with its own media', function () {
    Storage::fake('public');
    $module = LearningModule::factory()->create(['grade_level' => GradeLevel::Six]);
    $teacher = User::factory()->teaching($module->subject)->create();
    Storage::disk('public')->put('quiz-media/videos/source.webm', 'video');
    $source = QuestionBankItem::factory()->shared()->reviewed()->identification()->for($module, 'module')->create([
        'prompt' => 'What mineral in milk builds strong bones?',
        'correct_answer' => 'Calcium',
        'topic' => 'Minerals',
        'competency_code' => 'H6N-Ia-2',
        'difficulty' => Difficulty::Hard,
        'times_used' => 12,
        'video_path' => 'quiz-media/videos/source.webm',
    ]);

    $response = $this->actingAs($teacher)->post(route('teacher.question-bank.duplicate.store', $source));

    $copy = QuestionBankItem::query()->whereKeyNot($source->id)->sole();
    $response->assertRedirectToRoute('teacher.question-bank.edit', $copy)
        ->assertSessionHas('status', 'Question copied to your bank. Edit it to make it your own.');
    expect($copy)
        ->teacher_id->toBe($teacher->id)
        ->subject_id->toBe($module->subject_id)
        ->learning_module_id->toBe($module->id)
        ->grade_level->toBe(GradeLevel::Six)
        ->prompt->toBe('What mineral in milk builds strong bones?')
        ->correct_answer->toBe('Calcium')
        ->topic->toBe('Minerals')
        ->competency_code->toBe('H6N-Ia-2')
        ->difficulty->toBe(Difficulty::Hard)
        ->is_shared->toBeFalse()
        ->reviewed_at->toBeNull()
        ->times_used->toBe(0)
        ->and($copy->video_path)->toStartWith('quiz-media/videos/')->toEndWith('.webm')->not->toBe('quiz-media/videos/source.webm');
    Storage::disk('public')->assertExists([$copy->video_path, 'quiz-media/videos/source.webm']);
    expect($source->refresh()->times_used)->toBe(12);
});

it('duplicates the teacher’s own item', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $own = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create();

    $this->actingAs($teacher)
        ->post(route('teacher.question-bank.duplicate.store', $own))
        ->assertSessionHasNoErrors();

    expect($teacher->questionBankItems()->count())->toBe(2);
});

it('hides items the teacher may not see', function (Closure $makeItem) {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();

    $this->actingAs($teacher)
        ->post(route('teacher.question-bank.duplicate.store', $makeItem($subject)))
        ->assertNotFound();

    expect($teacher->questionBankItems()->count())->toBe(0);
})->with([
    'another teacher’s private item' => [fn (Subject $subject) => QuestionBankItem::factory()->for($subject)->create()],
    'a shared item of another subject' => [fn () => QuestionBankItem::factory()->shared()->create()],
]);

it('forbids teachers who may not use the question bank', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $item = QuestionBankItem::factory()->shared()->for($subject)->create();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageQuestionBank);

    $this->actingAs($teacher)
        ->post(route('teacher.question-bank.duplicate.store', $item))
        ->assertForbidden();

    expect($teacher->questionBankItems()->count())->toBe(0);
});
