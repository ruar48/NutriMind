<?php

use App\Enums\Difficulty;
use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Http\Requests\Teacher\StoreQuizBankQuestionRequest;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\User;
use App\Services\RolePermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Create a teacher with a reviewed Grade 5 draft quiz in their subject.
 *
 * @return array{0: User, 1: Quiz, 2: Subject}
 */
function quizBankPickerScenario(): array
{
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $quiz = Quiz::factory()->reviewed()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);

    return [$teacher, $quiz, $subject];
}

describe('create', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.quizzes.bank-questions.create', Quiz::factory()->create()))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('teacher.quizzes.bank-questions.create', Quiz::factory()->create()))
            ->assertForbidden();
    })->with(['admin', 'parent', 'student']);

    it('lists the teacher’s own and shared items of the quiz’s subject and grade only', function () {
        [$teacher, $quiz, $subject] = quizBankPickerScenario();
        $otherSubject = Subject::factory()->create();
        $teacher->assignedSubjects()->attach($otherSubject);
        $colleague = User::factory()->teaching($subject)->create();
        $own = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'prompt' => 'Own private question']);
        $shared = QuestionBankItem::factory()->shared()->for($colleague, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'prompt' => 'Shared colleague question']);
        QuestionBankItem::factory()->for($colleague, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'prompt' => 'Private colleague question']);
        QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Six, 'prompt' => 'Grade six question']);
        QuestionBankItem::factory()->for($teacher, 'teacher')->for($otherSubject)->create(['grade_level' => GradeLevel::Five, 'prompt' => 'Other subject question']);

        $response = $this->actingAs($teacher->refresh())->get(route('teacher.quizzes.bank-questions.create', $quiz));

        $response->assertOk()
            ->assertViewIs('teacher.quizzes.bank-questions.create')
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->sort()->values()->all() === collect([$own->id, $shared->id])->sort()->values()->all())
            ->assertSeeText('Own private question')
            ->assertSeeText('Shared colleague question')
            ->assertSeeText($colleague->name)
            ->assertDontSeeText('Private colleague question')
            ->assertDontSeeText('Grade six question')
            ->assertDontSeeText('Other subject question');
    });

    it('disables items that are already in the quiz', function () {
        [$teacher, $quiz, $subject] = quizBankPickerScenario();
        $item = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        QuizQuestion::factory()->for($quiz)->create(['question_bank_item_id' => $item->id]);

        $response = $this->actingAs($teacher)->get(route('teacher.quizzes.bank-questions.create', $quiz));

        $response->assertOk()
            ->assertViewHas('addedItemIds', [$item->id])
            ->assertSeeText('Already in this quiz');
        expect($response->getContent())->toMatch('#<input[^>]*value="'.$item->id.'"[^>]*\sdisabled(?=[\s/>])#');
    });

    it('filters by difficulty, type, review and words', function () {
        [$teacher, $quiz, $subject] = quizBankPickerScenario();
        $match = QuestionBankItem::factory()->trueFalse()->reviewed()->for($teacher, 'teacher')->for($subject)->create([
            'grade_level' => GradeLevel::Five, 'difficulty' => Difficulty::Hard, 'topic' => 'Hydration',
        ]);
        QuestionBankItem::factory()->trueFalse()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'difficulty' => Difficulty::Hard, 'topic' => 'Hydration']);
        QuestionBankItem::factory()->reviewed()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'difficulty' => Difficulty::Hard, 'topic' => 'Hydration']);

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.bank-questions.create', [$quiz, 'difficulty' => 'hard', 'type' => 'true_false', 'reviewed' => '1', 'search' => 'hydra']))
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->pluck('id')->all() === [$match->id]);
    });

    it('explains when the bank has no questions for the quiz', function () {
        [$teacher, $quiz] = quizBankPickerScenario();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.bank-questions.create', $quiz))
            ->assertOk()
            ->assertSeeText('questions in the bank yet')
            ->assertSee(route('teacher.question-bank.create', ['subject' => $quiz->subject_id, 'grade' => 5]));
    });

    it('explains when no question matches the filters', function () {
        [$teacher, $quiz, $subject] = quizBankPickerScenario();
        QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.bank-questions.create', [$quiz, 'search' => 'nothing like this']))
            ->assertOk()
            ->assertSeeText('No questions match your filters');
    });

    it('uses the same number of queries for few and many items', function () {
        [$teacher, $quiz, $subject] = quizBankPickerScenario();
        $colleague = User::factory()->teaching($subject)->create();
        $make = fn () => QuestionBankItem::factory()->shared()->for($colleague, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $make();
        $countQueries = function () use ($teacher, $quiz): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($teacher)->get(route('teacher.quizzes.bank-questions.create', $quiz))->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $countQueries();
        $few = $countQueries();

        foreach (range(1, 6) as $ignored) {
            $make();
        }

        expect($countQueries())->toBe($few);
    });

    it('sends the teacher back to the builder while the quiz is published', function () {
        [$teacher, $quiz] = quizBankPickerScenario();
        $quiz->forceFill(['status' => 'published'])->save();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.bank-questions.create', $quiz))
            ->assertRedirectToRoute('teacher.quizzes.show', $quiz)
            ->assertSessionHas('error', 'Unpublish the quiz to edit its questions.');
    });

    it('forbids teachers without the permission it needs', function (Permission $permission) {
        [$teacher, $quiz] = quizBankPickerScenario();
        app(RolePermissions::class)->revoke(UserRole::Teacher, $permission);

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.bank-questions.create', $quiz))
            ->assertForbidden()
            ->assertSeeText(RolePermissions::deniedMessage($permission));
    })->with([Permission::CreateQuizzes, Permission::ManageQuestionBank]);

    it('hides other teachers’ quizzes', function () {
        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.quizzes.bank-questions.create', Quiz::factory()->create()))
            ->assertNotFound();
    });
});

describe('store', function () {
    it('copies the selected items to the end of the quiz in the selected order', function () {
        Storage::fake('public');
        [$teacher, $quiz, $subject] = quizBankPickerScenario();
        QuizQuestion::factory()->for($quiz)->create(['sort_order' => 3]);
        Storage::disk('public')->put('quiz-media/images/bank.png', 'image');
        $withImage = QuestionBankItem::factory()->identification()->for($teacher, 'teacher')->for($subject)->create([
            'grade_level' => GradeLevel::Five, 'correct_answer' => 'Calcium', 'image_path' => 'quiz-media/images/bank.png', 'times_used' => 2,
        ]);
        $shared = QuestionBankItem::factory()->shared()->for(User::factory()->teaching($subject), 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.bank-questions.store', $quiz), ['item_ids' => [$shared->id, $withImage->id]])
            ->assertRedirect(route('teacher.quizzes.show', $quiz))
            ->assertSessionHas('status', '2 questions were added from the question bank.');

        $copies = $quiz->questions()->whereNotNull('question_bank_item_id')->get();
        expect($copies->pluck('question_bank_item_id')->all())->toBe([$shared->id, $withImage->id])
            ->and($copies->pluck('sort_order')->all())->toBe([4, 5])
            ->and($copies->pluck('points')->all())->toBe([1, 1])
            ->and($copies[0]->prompt)->toBe($shared->prompt)
            ->and($copies[0]->choices)->toBe($shared->choices)
            ->and($copies[1]->type)->toBe(QuestionType::Identification)
            ->and($copies[1]->correct_answer)->toBe('Calcium')
            ->and($copies[1]->image_path)->not->toBe('quiz-media/images/bank.png');
        Storage::disk('public')->assertExists([$copies[1]->image_path, 'quiz-media/images/bank.png']);
        expect($withImage->refresh()->times_used)->toBe(3)
            ->and($shared->refresh()->times_used)->toBe(1)
            ->and($quiz->refresh()->reviewed_at)->toBeNull();
    });

    it('reports a single added question', function () {
        [$teacher, $quiz, $subject] = quizBankPickerScenario();
        $item = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.bank-questions.store', $quiz), ['item_ids' => [$item->id]])
            ->assertSessionHas('status', '1 question was added from the question bank.');
    });

    it('rejects a selection that cannot be added', function (Closure $selection, string $message) {
        [$teacher, $quiz, $subject] = quizBankPickerScenario();

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.bank-questions.store', $quiz), ['item_ids' => $selection($teacher, $quiz, $subject)])
            ->assertSessionHasErrors(['item_ids' => $message]);

        expect($quiz->questions()->whereNotNull('question_bank_item_id')->count())->toBeLessThanOrEqual(1)
            ->and($quiz->refresh()->reviewed_at)->not->toBeNull();
    })->with([
        'nothing selected' => [fn () => [], 'Select at least one question to add.'],
        'another teacher’s private item' => [fn (User $teacher, Quiz $quiz, Subject $subject) => [QuestionBankItem::factory()->for($subject)->create(['grade_level' => GradeLevel::Five])->id], StoreQuizBankQuestionRequest::UNAVAILABLE_MESSAGE],
        'an item of another grade' => [fn (User $teacher, Quiz $quiz, Subject $subject) => [QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Six])->id], StoreQuizBankQuestionRequest::UNAVAILABLE_MESSAGE],
        'an unknown item' => [fn () => [999999], StoreQuizBankQuestionRequest::UNAVAILABLE_MESSAGE],
        'an item already in the quiz' => [function (User $teacher, Quiz $quiz, Subject $subject) {
            $item = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
            QuizQuestion::factory()->for($quiz)->create(['question_bank_item_id' => $item->id]);

            return [$item->id];
        }, StoreQuizBankQuestionRequest::ALREADY_ADDED_MESSAGE],
        'too many items' => [fn () => range(1, 51), 'Add at most 50 questions at a time.'],
    ]);

    it('rejects the same item twice', function () {
        [$teacher, $quiz, $subject] = quizBankPickerScenario();
        $item = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.bank-questions.store', $quiz), ['item_ids' => [$item->id, $item->id]])
            ->assertSessionHasErrors(['item_ids.0' => 'Select each question only once.']);
    });

    it('does not add questions once learners attempted the quiz', function () {
        [$teacher, $quiz, $subject] = quizBankPickerScenario();
        $item = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        QuizAttempt::factory()->for($quiz)->create();

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.bank-questions.store', $quiz), ['item_ids' => [$item->id]])
            ->assertRedirectToRoute('teacher.quizzes.show', $quiz)
            ->assertSessionHas('error', 'Learners have already taken this quiz. Duplicate it to make changes.');

        expect($quiz->questions()->count())->toBe(0)
            ->and($item->refresh()->times_used)->toBe(0);
    });

    it('forbids teachers who may not use the question bank', function () {
        [$teacher, $quiz, $subject] = quizBankPickerScenario();
        $item = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageQuestionBank);

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.bank-questions.store', $quiz), ['item_ids' => [$item->id]])
            ->assertForbidden();

        expect($quiz->questions()->count())->toBe(0);
    });

    it('hides other teachers’ quizzes', function () {
        $this->actingAs(User::factory()->teaching()->create())
            ->post(route('teacher.quizzes.bank-questions.store', Quiz::factory()->create()), ['item_ids' => [1]])
            ->assertNotFound();
    });
});
