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
use App\Models\Subject;
use App\Models\User;
use App\Policies\QuestionBankItemPolicy;
use App\Services\RolePermissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Build a valid bank item payload for the subject.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function questionBankItemPayload(Subject $subject, array $overrides = []): array
{
    return [
        'type' => QuestionType::MultipleChoice->value,
        'prompt' => 'Which vitamin helps keep our eyes healthy?',
        'choices' => ['Vitamin A', 'Vitamin D', 'Vitamin K'],
        'correct_answer' => '0',
        'explanation' => 'Carrots and squash are rich in Vitamin A.',
        'subject_id' => $subject->id,
        'grade_level' => GradeLevel::Five->value,
        'learning_module_id' => null,
        'topic' => 'Vitamins and minerals',
        'competency_code' => 'H5N-Ib-3',
        'difficulty' => Difficulty::Medium->value,
        'is_shared' => '1',
        'reviewed' => '1',
        ...$overrides,
    ];
}

describe('index', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.question-bank.index'))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('teacher.question-bank.index'))
            ->assertForbidden();
    })->with(['admin', 'parent', 'student']);

    it('lists the teacher’s own items in subjects they teach, with tab counts', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $colleague = User::factory()->teaching($subject)->create();
        $own = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create(['prompt' => 'My own question']);
        QuestionBankItem::factory()->shared()->for($colleague, 'teacher')->for($subject)->create(['prompt' => 'Colleague shared question']);
        QuestionBankItem::factory()->for($colleague, 'teacher')->for($subject)->create(['prompt' => 'Colleague private question']);
        QuestionBankItem::factory()->for($teacher, 'teacher')->revokedSubject()->create(['prompt' => 'Revoked subject question']);

        $response = $this->actingAs($teacher->refresh())->get(route('teacher.question-bank.index'));

        $response->assertOk()
            ->assertViewIs('teacher.question-bank.index')
            ->assertViewHas('tab', 'mine')
            ->assertViewHas('counts', ['mine' => 1, 'shared' => 1])
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->all() === [$own->id])
            ->assertSeeText('My own question')
            ->assertDontSeeText('Colleague shared question')
            ->assertDontSeeText('Colleague private question')
            ->assertDontSeeText('Revoked subject question');
    });

    it('lists the items other teachers share in the teacher’s subjects', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $colleague = User::factory()->teaching($subject)->create(['name' => 'Rowena Bautista']);
        $shared = QuestionBankItem::factory()->shared()->for($colleague, 'teacher')->for($subject)->create(['prompt' => 'Colleague shared question']);
        $orphan = QuestionBankItem::factory()->shared()->for($subject)->create(['teacher_id' => null, 'prompt' => 'Question of a former teacher']);
        QuestionBankItem::factory()->shared()->create(['prompt' => 'Shared in another subject']);
        QuestionBankItem::factory()->shared()->for($teacher, 'teacher')->for($subject)->create(['prompt' => 'My shared question']);

        $response = $this->actingAs($teacher)->get(route('teacher.question-bank.index', ['tab' => 'shared']));

        $response->assertOk()
            ->assertViewHas('tab', 'shared')
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->sort()->values()->all() === collect([$shared->id, $orphan->id])->sort()->values()->all())
            ->assertSeeText('Rowena Bautista')
            ->assertSeeText('A former teacher')
            ->assertSeeText('Copy to my bank')
            ->assertDontSeeText('Shared in another subject')
            ->assertDontSeeText('My shared question');
    });

    it('filters by subject, grade, type, difficulty, review and words', function () {
        $health = Subject::factory()->create();
        $science = Subject::factory()->create();
        $teacher = User::factory()->teaching($health, $science)->create();
        $match = QuestionBankItem::factory()->identification()->reviewed()->for($teacher, 'teacher')->for($health)->create([
            'grade_level' => GradeLevel::Six, 'difficulty' => Difficulty::Hard, 'competency_code' => 'H6N-IIa-4',
        ]);
        QuestionBankItem::factory()->identification()->for($teacher, 'teacher')->for($health)->create(['grade_level' => GradeLevel::Six, 'difficulty' => Difficulty::Hard, 'competency_code' => 'H6N-IIa-4']);
        QuestionBankItem::factory()->identification()->reviewed()->for($teacher, 'teacher')->for($science)->create(['grade_level' => GradeLevel::Six, 'difficulty' => Difficulty::Hard, 'competency_code' => 'H6N-IIa-4']);
        QuestionBankItem::factory()->identification()->reviewed()->for($teacher, 'teacher')->for($health)->create(['grade_level' => GradeLevel::Five, 'difficulty' => Difficulty::Hard, 'competency_code' => 'H6N-IIa-4']);
        QuestionBankItem::factory()->reviewed()->for($teacher, 'teacher')->for($health)->create(['grade_level' => GradeLevel::Six, 'difficulty' => Difficulty::Hard, 'competency_code' => 'H6N-IIa-4']);
        QuestionBankItem::factory()->identification()->reviewed()->for($teacher, 'teacher')->for($health)->create(['grade_level' => GradeLevel::Six, 'difficulty' => Difficulty::Easy, 'competency_code' => 'H6N-IIa-4']);

        $this->actingAs($teacher)
            ->get(route('teacher.question-bank.index', [
                'subject' => $health->id, 'grade' => 6, 'type' => 'identification', 'difficulty' => 'hard', 'reviewed' => 'yes', 'search' => 'IIa-4',
            ]))
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->all() === [$match->id]);
    });

    it('filters for items that are not reviewed yet', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        QuestionBankItem::factory()->reviewed()->for($teacher, 'teacher')->for($subject)->create();
        $unreviewed = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create();

        $this->actingAs($teacher)
            ->get(route('teacher.question-bank.index', ['reviewed' => 'no']))
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->all() === [$unreviewed->id]);
    });

    it('shows a designed empty state for each tab and for filters', function (array $query, string $text) {
        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.question-bank.index', $query))
            ->assertOk()
            ->assertSeeText($text);
    })->with([
        'mine' => [[], 'Your question bank is empty'],
        'shared' => [['tab' => 'shared'], 'Nothing shared with you yet'],
        'filtered' => [['search' => 'nothing'], 'No questions match your filters'],
    ]);

    it('uses the same number of queries for few and many items', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $module = LearningModule::factory()->for($subject)->create();
        $make = fn () => QuestionBankItem::factory()->shared()->for(User::factory()->teaching($subject), 'teacher')->for($module, 'module')->create();
        $make();
        $countQueries = function () use ($teacher): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($teacher)->get(route('teacher.question-bank.index', ['tab' => 'shared']))->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $countQueries();
        $few = $countQueries();

        foreach (range(1, 5) as $ignored) {
            $make();
        }

        expect($countQueries())->toBe($few);
    });

    it('forbids teachers who may not use the question bank', function () {
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageQuestionBank);

        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.question-bank.index'))
            ->assertForbidden()
            ->assertSeeText(RolePermissions::deniedMessage(Permission::ManageQuestionBank));
    });
});

describe('create', function () {
    it('shows the form for the requested type, prefilled for a subject and grade', function () {
        $subject = Subject::factory()->create(['name' => 'Health']);
        $teacher = User::factory()->teaching($subject)->create();

        $this->actingAs($teacher)
            ->get(route('teacher.question-bank.create', ['type' => 'identification', 'subject' => $subject->id, 'grade' => 6]))
            ->assertOk()
            ->assertViewIs('teacher.question-bank.form')
            ->assertViewHas('type', QuestionType::Identification)
            ->assertViewHas('item', fn (QuestionBankItem $item) => $item->subject_id === $subject->id && $item->grade_level === GradeLevel::Six)
            ->assertViewHas('canShare', true)
            ->assertSee('name="accepted_answers"', false)
            ->assertSee('name="is_shared"', false);
    });

    it('explains that the teacher cannot share without the sharing permission', function () {
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ShareQuestions);

        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.question-bank.create'))
            ->assertOk()
            ->assertViewHas('canShare', false)
            ->assertSeeText('Your account cannot share questions, so this question is saved as private.')
            ->assertDontSee('name="is_shared"', false);
    });

    it('forbids teachers without an assigned subject', function () {
        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('teacher.question-bank.create'))
            ->assertForbidden()
            ->assertSeeText(QuestionBankItemPolicy::NO_SUBJECT_MESSAGE);
    });
});

describe('store', function () {
    it('adds a shared, reviewed item with an image to the teacher’s bank', function () {
        Storage::fake('public');
        $this->freezeSecond();
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $module = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);

        $response = $this->actingAs($teacher)->post(route('teacher.question-bank.store'), questionBankItemPayload($subject, [
            'learning_module_id' => $module->id,
            'image' => UploadedFile::fake()->image('eyes.jpg'),
        ]));

        $item = QuestionBankItem::query()->sole();
        $response->assertRedirectToRoute('teacher.question-bank.show', $item)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Question added to your bank.');
        expect($item)
            ->teacher_id->toBe($teacher->id)
            ->subject_id->toBe($subject->id)
            ->grade_level->toBe(GradeLevel::Five)
            ->learning_module_id->toBe($module->id)
            ->topic->toBe('Vitamins and minerals')
            ->competency_code->toBe('H5N-Ib-3')
            ->difficulty->toBe(Difficulty::Medium)
            ->type->toBe(QuestionType::MultipleChoice)
            ->choices->toBe(['Vitamin A', 'Vitamin D', 'Vitamin K'])
            ->correct_answer->toBe('0')
            ->is_shared->toBeTrue()
            ->times_used->toBe(0)
            ->and($item->reviewed_at->toDateTimeString())->toBe(now()->toDateTimeString());
        Storage::disk('public')->assertExists($item->image_path);
    });

    it('keeps the item private when the teacher may not share questions', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ShareQuestions);

        $this->actingAs($teacher)
            ->post(route('teacher.question-bank.store'), questionBankItemPayload($subject, ['is_shared' => '1', 'reviewed' => '0']))
            ->assertSessionHasNoErrors();

        expect(QuestionBankItem::query()->sole())->is_shared->toBeFalse()->reviewed_at->toBeNull();
    });

    it('rejects invalid items with clear messages', function (Closure $overrides, string $field, string $message) {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();

        $this->actingAs($teacher->refresh())
            ->post(route('teacher.question-bank.store'), questionBankItemPayload($subject, $overrides($teacher, $subject)))
            ->assertSessionHasErrors([$field => $message]);

        expect(QuestionBankItem::query()->count())->toBe(0);
    })->with([
        'missing prompt' => [fn () => ['prompt' => ''], 'prompt', 'The question field is required.'],
        'repeated choice' => [fn () => ['choices' => ['Vitamin A', 'vitamin a']], 'choices.1', 'Choice B repeats another choice.'],
        'unassigned subject' => [fn () => ['subject_id' => Subject::factory()->create()->id], 'subject_id', 'Choose one of your active subjects.'],
        'inactive subject' => [fn (User $teacher) => ['subject_id' => tap(Subject::factory()->inactive()->create(), fn (Subject $subject) => $teacher->assignedSubjects()->attach($subject))->id], 'subject_id', 'Choose one of your active subjects.'],
        'missing grade' => [fn () => ['grade_level' => ''], 'grade_level', 'The grade level field is required.'],
        'module of another grade' => [fn (User $teacher, Subject $subject) => ['learning_module_id' => LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Six])->id], 'learning_module_id', 'Choose a learning module of the selected subject and grade.'],
        'long topic' => [fn () => ['topic' => str_repeat('a', 101)], 'topic', 'The topic field must not be greater than 100 characters.'],
        'long competency code' => [fn () => ['competency_code' => str_repeat('A', 41)], 'competency_code', 'The competency code field must not be greater than 40 characters.'],
        'missing difficulty' => [fn () => ['difficulty' => ''], 'difficulty', 'The difficulty field is required.'],
        'unknown difficulty' => [fn () => ['difficulty' => 'extreme'], 'difficulty', 'The selected difficulty is invalid.'],
    ]);

    it('forbids teachers who may not use the question bank before validating', function () {
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageQuestionBank);

        $this->actingAs(User::factory()->teaching()->create())
            ->post(route('teacher.question-bank.store'), [])
            ->assertForbidden()
            ->assertSessionHasNoErrors();
    });
});

describe('show', function () {
    it('previews the item with its answer and the teacher’s quizzes that use it', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $item = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create([
            'prompt' => 'Which snack is the healthiest choice?',
            'choices' => ['A banana', 'A soda'],
            'correct_answer' => '0',
            'times_used' => 3,
        ]);
        $quiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create(['title' => 'Healthy Snacks Quiz']);
        QuizQuestion::factory()->for($quiz)->create(['question_bank_item_id' => $item->id]);
        $foreignQuiz = Quiz::factory()->for($subject)->create(['title' => 'Someone Else’s Quiz']);
        QuizQuestion::factory()->for($foreignQuiz)->create(['question_bank_item_id' => $item->id]);

        $this->actingAs($teacher)
            ->get(route('teacher.question-bank.show', $item))
            ->assertOk()
            ->assertViewIs('teacher.question-bank.show')
            ->assertViewHas('quizzes', fn ($quizzes) => $quizzes->pluck('id')->all() === [$quiz->id])
            ->assertSeeText('Which snack is the healthiest choice?')
            ->assertSee('data-correct-choice', false)
            ->assertSeeText('Added to quizzes 3 times in total')
            ->assertSeeText('Healthy Snacks Quiz')
            ->assertDontSeeText('Someone Else’s Quiz');
    });

    it('shows shared items of the teacher’s subjects', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $item = QuestionBankItem::factory()->shared()->for($subject)->create();

        $this->actingAs($teacher)
            ->get(route('teacher.question-bank.show', $item))
            ->assertOk()
            ->assertSeeText('Copy to my bank');
    });

    it('hides items the teacher may not see', function (Closure $makeItem) {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();

        $this->actingAs($teacher)
            ->get(route('teacher.question-bank.show', $makeItem($subject)))
            ->assertNotFound();
    })->with([
        'another teacher’s private item' => [fn (Subject $subject) => QuestionBankItem::factory()->for($subject)->create()],
        'a shared item of another subject' => [fn () => QuestionBankItem::factory()->shared()->create()],
    ]);

    it('hides the teacher’s own item once its subject was revoked', function () {
        $item = QuestionBankItem::factory()->revokedSubject()->create();

        $this->actingAs($item->teacher)
            ->get(route('teacher.question-bank.show', $item))
            ->assertNotFound();
    });
});

describe('edit', function () {
    it('shows the owner the form with the item’s values', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $item = QuestionBankItem::factory()->trueFalse()->shared()->reviewed()->for($teacher, 'teacher')->for($subject)->create(['topic' => 'Food safety']);

        $response = $this->actingAs($teacher)->get(route('teacher.question-bank.edit', $item));

        $response->assertOk()
            ->assertViewHas('type', QuestionType::TrueFalse)
            ->assertViewHas('canShare', true)
            ->assertSee('value="Food safety"', false);
        expect($response->getContent())->toMatch('#<input[^>]*name="is_shared"[^>]*\schecked(?=[\s/>])#')
            ->toMatch('#<input[^>]*name="reviewed"[^>]*\schecked(?=[\s/>])#');
    });

    it('tells other teachers to duplicate a shared item instead', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $item = QuestionBankItem::factory()->shared()->for($subject)->create();

        $this->actingAs($teacher)
            ->get(route('teacher.question-bank.edit', $item))
            ->assertForbidden()
            ->assertSeeText(QuestionBankItemPolicy::NOT_OWNER_MESSAGE);
    });

    it('hides other teachers’ private items', function () {
        $subject = Subject::factory()->create();
        $item = QuestionBankItem::factory()->for($subject)->create();

        $this->actingAs(User::factory()->teaching($subject)->create())
            ->get(route('teacher.question-bank.edit', $item))
            ->assertNotFound();
    });
});

describe('update', function () {
    it('saves the changes, replaces the image and keeps the first review date', function () {
        Storage::fake('public');
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        Storage::disk('public')->put('quiz-media/images/old.png', 'image');
        $item = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create([
            'reviewed_at' => '2026-10-01 08:00:00', 'is_shared' => false, 'image_path' => 'quiz-media/images/old.png',
        ]);

        $this->actingAs($teacher)
            ->put(route('teacher.question-bank.update', $item), questionBankItemPayload($subject, [
                'prompt' => 'Which vitamin do carrots give us?',
                'image' => UploadedFile::fake()->image('carrot.png'),
            ]))
            ->assertRedirectToRoute('teacher.question-bank.show', $item)
            ->assertSessionHas('status', 'Question saved.');

        $item->refresh();
        expect($item)
            ->prompt->toBe('Which vitamin do carrots give us?')
            ->is_shared->toBeTrue()
            ->difficulty->toBe(Difficulty::Medium)
            ->and($item->reviewed_at->toDateTimeString())->toBe('2026-10-01 08:00:00')
            ->and($item->image_path)->not->toBe('quiz-media/images/old.png');
        Storage::disk('public')->assertExists($item->image_path);
        Storage::disk('public')->assertMissing('quiz-media/images/old.png');
    });

    it('clears the review and stops sharing when unchecked', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $item = QuestionBankItem::factory()->shared()->reviewed()->for($teacher, 'teacher')->for($subject)->create();

        $this->actingAs($teacher)
            ->put(route('teacher.question-bank.update', $item), questionBankItemPayload($subject, ['is_shared' => '0', 'reviewed' => '0']))
            ->assertSessionHasNoErrors();

        expect($item->refresh())->is_shared->toBeFalse()->reviewed_at->toBeNull();
    });

    it('saves the item as private when the teacher may no longer share questions', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $item = QuestionBankItem::factory()->shared()->for($teacher, 'teacher')->for($subject)->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ShareQuestions);

        $this->actingAs($teacher)
            ->put(route('teacher.question-bank.update', $item), questionBankItemPayload($subject, ['is_shared' => '1']))
            ->assertSessionHasNoErrors();

        expect($item->refresh()->is_shared)->toBeFalse();
    });

    it('keeps the item’s current subject after the subject was deactivated', function () {
        $subject = Subject::factory()->inactive()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $item = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create();

        $this->actingAs($teacher)
            ->put(route('teacher.question-bank.update', $item), questionBankItemPayload($subject))
            ->assertSessionHasNoErrors();
    });

    it('lets only the owner change a shared item', function () {
        $subject = Subject::factory()->create();
        $item = QuestionBankItem::factory()->shared()->for($subject)->create(['prompt' => 'Original']);

        $this->actingAs(User::factory()->teaching($subject)->create())
            ->put(route('teacher.question-bank.update', $item), questionBankItemPayload($subject))
            ->assertForbidden();

        expect($item->refresh()->prompt)->toBe('Original');
    });
});

describe('destroy', function () {
    it('deletes the item and its media while quiz copies keep their own', function () {
        Storage::fake('public');
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        Storage::disk('public')->put('quiz-media/images/item.png', 'image');
        Storage::disk('public')->put('quiz-media/images/copy.png', 'image');
        $item = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create(['image_path' => 'quiz-media/images/item.png']);
        $copy = QuizQuestion::factory()->for(Quiz::factory()->for($teacher, 'teacher')->for($subject))->create(['question_bank_item_id' => $item->id, 'image_path' => 'quiz-media/images/copy.png']);

        $this->actingAs($teacher)
            ->delete(route('teacher.question-bank.destroy', $item))
            ->assertRedirectToRoute('teacher.question-bank.index')
            ->assertSessionHas('status', 'Question deleted from your bank.');

        $this->assertModelMissing($item);
        expect($copy->refresh()->question_bank_item_id)->toBeNull();
        Storage::disk('public')->assertMissing('quiz-media/images/item.png');
        Storage::disk('public')->assertExists('quiz-media/images/copy.png');
    });

    it('lets only the owner delete a shared item', function () {
        $subject = Subject::factory()->create();
        $item = QuestionBankItem::factory()->shared()->for($subject)->create();

        $this->actingAs(User::factory()->teaching($subject)->create())
            ->delete(route('teacher.question-bank.destroy', $item))
            ->assertForbidden()
            ->assertSeeText(QuestionBankItemPolicy::NOT_OWNER_MESSAGE);

        $this->assertModelExists($item);
    });

    it('hides other teachers’ private items', function () {
        $subject = Subject::factory()->create();
        $item = QuestionBankItem::factory()->for($subject)->create();

        $this->actingAs(User::factory()->teaching($subject)->create())
            ->delete(route('teacher.question-bank.destroy', $item))
            ->assertNotFound();

        $this->assertModelExists($item);
    });
});
