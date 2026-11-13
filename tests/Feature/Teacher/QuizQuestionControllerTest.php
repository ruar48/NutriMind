<?php

use App\Enums\Permission;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Http\Controllers\Teacher\QuizQuestionController;
use App\Http\Requests\Teacher\QuestionContentRequest;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\User;
use App\Policies\QuizPolicy;
use App\Services\QuestionMedia;
use App\Services\RolePermissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Create a teacher with a reviewed draft quiz in a subject they teach.
 *
 * @return array{0: User, 1: Quiz}
 */
function quizQuestionDraft(): array
{
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $quiz = Quiz::factory()->reviewed()->for($teacher, 'teacher')->for($subject)->create();

    return [$teacher, $quiz];
}

/**
 * Build a valid multiple choice question payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function quizQuestionPayload(array $overrides = []): array
{
    return [
        'type' => QuestionType::MultipleChoice->value,
        'prompt' => 'Which food group gives our body energy?',
        'choices' => ['Go foods', 'Grow foods', 'Glow foods'],
        'correct_answer' => '0',
        'points' => 2,
        'explanation' => 'Rice and bread are Go foods.',
        'video_url' => null,
        ...$overrides,
    ];
}

describe('create', function () {
    it('sends guests to the sign-in page', function () {
        $quiz = Quiz::factory()->create();

        $this->get(route('teacher.quizzes.questions.create', $quiz))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (string $role) {
        $quiz = Quiz::factory()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('teacher.quizzes.questions.create', $quiz))
            ->assertForbidden();
    })->with(['admin', 'parent', 'student']);

    it('shows the form of the requested question type', function (?string $type, string $expectedField, string $title) {
        [$teacher, $quiz] = quizQuestionDraft();
        QuizQuestion::factory()->for($quiz)->count(2)->create();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.questions.create', array_filter([$quiz->id, 'type' => $type])))
            ->assertOk()
            ->assertViewIs('teacher.quizzes.questions.form')
            ->assertViewHas('number', 3)
            ->assertSee($expectedField, false)
            ->assertSeeText($title)
            ->assertSeeText('Saving asks for a new review');
    })->with([
        'multiple choice by default' => [null, 'name="choices[3]"', 'Question 3'],
        'true or false' => ['true_false', 'value="1"', 'Question 3'],
        'identification' => ['identification', 'name="accepted_answers"', 'Question 3'],
        'unknown type' => ['essay', 'name="choices[0]"', 'Question 3'],
    ]);

    it('shows the real upload limits in the hints', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        $media = app(QuestionMedia::class);

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.questions.create', $quiz))
            ->assertOk()
            ->assertSeeText('JPG, PNG, WebP or GIF, up to '.$media->imageLimitLabel().'.')
            ->assertSeeText('MP4 or WebM, up to '.$media->videoLimitLabel().'.');
    });

    it('sends the teacher back to the builder while the quiz is published', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        $quiz->forceFill(['status' => 'published'])->save();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.questions.create', $quiz))
            ->assertRedirectToRoute('teacher.quizzes.show', $quiz)
            ->assertSessionHas('error', QuizQuestionController::PUBLISHED_QUESTIONS_LOCKED_MESSAGE);
    });

    it('sends the teacher back to the builder once learners attempted the quiz', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        $quiz->forceFill(['status' => 'closed'])->save();
        QuizAttempt::factory()->for($quiz)->create();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.questions.create', $quiz))
            ->assertRedirectToRoute('teacher.quizzes.show', $quiz)
            ->assertSessionHas('error', 'Learners have already taken this quiz. Duplicate it to make changes.');
    });

    it('hides other teachers’ quizzes', function () {
        $quiz = Quiz::factory()->create();

        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.quizzes.questions.create', $quiz))
            ->assertNotFound();
    });

    it('forbids the owner once the subject was revoked', function () {
        $quiz = Quiz::factory()->revokedSubject()->create();

        $this->actingAs($quiz->teacher)
            ->get(route('teacher.quizzes.questions.create', $quiz))
            ->assertForbidden()
            ->assertSeeText(QuizPolicy::SUBJECT_REVOKED_MESSAGE);
    });

    it('forbids teachers without the quiz permission', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes);

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.questions.create', $quiz))
            ->assertForbidden()
            ->assertSeeText(RolePermissions::deniedMessage(Permission::CreateQuizzes));
    });
});

describe('store', function () {
    it('adds a multiple choice question with an image at the end and asks for a new review', function () {
        Storage::fake('public');
        [$teacher, $quiz] = quizQuestionDraft();
        QuizQuestion::factory()->for($quiz)->create(['sort_order' => 4]);

        $response = $this->actingAs($teacher)->post(route('teacher.quizzes.questions.store', $quiz), quizQuestionPayload([
            'choices' => ['  Go   foods ', 'Grow foods', 'Glow foods'],
            'correct_answer' => '2',
            'image' => UploadedFile::fake()->image('plate.png', 120, 80),
        ]));

        $question = $quiz->questions()->reorder()->latest('id')->first();
        $response->assertRedirect(route('teacher.quizzes.show', $quiz).'#question-'.$question->id)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Question added.');
        expect($question)
            ->type->toBe(QuestionType::MultipleChoice)
            ->prompt->toBe('Which food group gives our body energy?')
            ->choices->toBe(['Go foods', 'Grow foods', 'Glow foods'])
            ->correct_answer->toBe('2')
            ->points->toBe(2)
            ->sort_order->toBe(5)
            ->explanation->toBe('Rice and bread are Go foods.')
            ->video_path->toBeNull();
        expect($question->image_path)->toStartWith('quiz-media/images/')->not->toContain('plate');
        Storage::disk('public')->assertExists($question->image_path);
        expect($quiz->refresh()->reviewed_at)->toBeNull();
    });

    it('adds a true or false question with the fixed choices', function () {
        [$teacher, $quiz] = quizQuestionDraft();

        $this->actingAs($teacher)->post(route('teacher.quizzes.questions.store', $quiz), [
            'type' => 'true_false',
            'prompt' => 'Soft drinks are a healthy replacement for water.',
            'choices' => ['Yes', 'No', 'Maybe'],
            'correct_answer' => '1',
            'points' => 1,
        ])->assertSessionHasNoErrors();

        expect($quiz->questions()->sole())
            ->type->toBe(QuestionType::TrueFalse)
            ->choices->toBe(['True', 'False'])
            ->correct_answer->toBe('1')
            ->sort_order->toBe(1);
    });

    it('adds an identification question with every accepted answer', function () {
        [$teacher, $quiz] = quizQuestionDraft();

        $this->actingAs($teacher)->post(route('teacher.quizzes.questions.store', $quiz), [
            'type' => 'identification',
            'prompt' => 'What vitamin is found in oranges?',
            'accepted_answers' => "Vitamin C\r\n\r\n  vit   C \nvitamin c\nAscorbic acid",
            'points' => 3,
        ])->assertSessionHasNoErrors();

        $question = $quiz->questions()->sole();
        expect($question)
            ->choices->toBeNull()
            ->correct_answer->toBe('Vitamin C|vit C|Ascorbic acid')
            ->and($question->isCorrectAnswer('ASCORBIC ACID'))->toBeTrue();
    });

    it('stores an uploaded video, or a video link', function () {
        Storage::fake('public');
        [$teacher, $quiz] = quizQuestionDraft();

        $this->actingAs($teacher)->post(route('teacher.quizzes.questions.store', $quiz), quizQuestionPayload([
            'video' => UploadedFile::fake()->create('lesson.mp4', 900, 'video/mp4'),
        ]))->assertSessionHasNoErrors();
        $this->actingAs($teacher)->post(route('teacher.quizzes.questions.store', $quiz), quizQuestionPayload([
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]))->assertSessionHasNoErrors();

        [$withFile, $withLink] = $quiz->questions()->get()->all();
        expect($withFile->video_path)->toStartWith('quiz-media/videos/')->toEndWith('.mp4')
            ->and($withFile->video_url)->toBeNull()
            ->and($withLink->video_path)->toBeNull()
            ->and($withLink->videoEmbedUrl())->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
        Storage::disk('public')->assertExists($withFile->video_path);
    });

    it('opens a new form of the same type after “save and add another”', function () {
        [$teacher, $quiz] = quizQuestionDraft();

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.questions.store', $quiz), quizQuestionPayload(['next' => 'another']))
            ->assertRedirectToRoute('teacher.quizzes.questions.create', [$quiz, 'type' => 'multiple_choice'])
            ->assertSessionHas('status', 'Question added. Write the next one.');
    });

    it('rejects invalid questions with clear messages', function (array $overrides, string $field, string $message) {
        [$teacher, $quiz] = quizQuestionDraft();

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.questions.store', $quiz), quizQuestionPayload($overrides))
            ->assertSessionHasErrors([$field => $message]);

        expect($quiz->questions()->count())->toBe(0)
            ->and($quiz->refresh()->reviewed_at)->not->toBeNull();
    })->with([
        'missing type' => [['type' => ''], 'type', 'Choose a question type.'],
        'unknown type' => [['type' => 'essay'], 'type', 'Choose a valid question type.'],
        'missing prompt' => [['prompt' => ''], 'prompt', 'The question field is required.'],
        'long prompt' => [['prompt' => str_repeat('a', 2001)], 'prompt', 'The question field must not be greater than 2000 characters.'],
        'missing choices' => [['choices' => null], 'choices', 'Add the answer choices.'],
        'one choice' => [['choices' => ['Go foods'], 'correct_answer' => '0'], 'choices', 'Add at least 2 choices.'],
        'seven choices' => [['choices' => ['A', 'B', 'C', 'D', 'E', 'F', 'G']], 'choices', 'Use at most 6 choices.'],
        'blank choice' => [['choices' => ['Go foods', '', 'Glow foods']], 'choices.1', 'Fill in choice B or remove it.'],
        'long choice' => [['choices' => ['Go foods', str_repeat('a', 301)]], 'choices.1', 'Choice B may not be longer than 300 characters.'],
        'repeated choice' => [['choices' => ['Go foods', 'GO  FOODS', 'Glow foods']], 'choices.1', 'Choice B repeats another choice.'],
        'no correct choice' => [['correct_answer' => ''], 'correct_answer', 'Mark the correct choice.'],
        'correct choice out of range' => [['correct_answer' => '3'], 'correct_answer', 'Mark one of the choices as correct.'],
        'true or false without answer' => [['type' => 'true_false', 'correct_answer' => ''], 'correct_answer', 'Choose whether the statement is true or false.'],
        'true or false with another answer' => [['type' => 'true_false', 'correct_answer' => '2'], 'correct_answer', 'Choose whether the statement is true or false.'],
        'identification without answers' => [['type' => 'identification', 'accepted_answers' => ''], 'accepted_answers', 'Add at least one accepted answer.'],
        'eleven accepted answers' => [['type' => 'identification', 'accepted_answers' => implode("\n", range(1, 11))], 'accepted_answers', 'List at most 10 accepted answers.'],
        'long accepted answer' => [['type' => 'identification', 'accepted_answers' => str_repeat('a', 101)], 'accepted_answers', 'Each accepted answer may not be longer than 100 characters.'],
        'separator in an answer' => [['type' => 'identification', 'accepted_answers' => 'Go|Grow'], 'accepted_answers', 'Accepted answers cannot contain the | character.'],
        'answers too long together' => [['type' => 'identification', 'accepted_answers' => implode("\n", array_map(fn (int $i) => $i.str_repeat('a', 98), range(1, 6)))], 'accepted_answers', 'The accepted answers are too long together. Keep them under 500 characters in total.'],
        'missing points' => [['points' => ''], 'points', 'The points field is required.'],
        'zero points' => [['points' => 0], 'points', 'The points field must be between 1 and 10.'],
        'eleven points' => [['points' => 11], 'points', 'The points field must be between 1 and 10.'],
        'long explanation' => [['explanation' => str_repeat('a', 1001)], 'explanation', 'The explanation field must not be greater than 1000 characters.'],
        'link without http' => [['video_url' => 'javascript:alert(1)'], 'video_url', 'Enter a full video link that starts with http:// or https://.'],
        'ftp link' => [['video_url' => 'ftp://example.org/clip.mp4'], 'video_url', 'Enter a full video link that starts with http:// or https://.'],
        'long link' => [['video_url' => 'https://example.org/'.str_repeat('a', 490)], 'video_url', 'The video link field must not be greater than 500 characters.'],
    ]);

    it('rejects files that are not accepted images or videos', function (string $field, Closure $file, string $message) {
        Storage::fake('public');
        [$teacher, $quiz] = quizQuestionDraft();

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.questions.store', $quiz), quizQuestionPayload([$field => $file()]))
            ->assertSessionHasErrors([$field => $message]);

        expect(Storage::disk('public')->allFiles())->toBe([]);
    })->with([
        'pdf as image' => ['image', fn () => UploadedFile::fake()->create('worksheet.pdf', 20, 'application/pdf'), 'The image must be a JPG, PNG, WebP or GIF file.'],
        'svg as image' => ['image', fn () => UploadedFile::fake()->create('icon.svg', 5, 'image/svg+xml'), 'The image must be a JPG, PNG, WebP or GIF file.'],
        'image too large' => ['image', fn () => UploadedFile::fake()->image('big.jpg')->size(app(QuestionMedia::class)->imageMaxKilobytes() + 1), fn () => 'The image may not be larger than '.app(QuestionMedia::class)->imageLimitLabel().'.'],
        'mov as video' => ['video', fn () => UploadedFile::fake()->create('clip.mov', 100, 'video/quicktime'), 'The video must be an MP4 or WebM file.'],
        'video too large' => ['video', fn () => UploadedFile::fake()->create('long.mp4', app(QuestionMedia::class)->videoMaxKilobytes() + 1, 'video/mp4'), fn () => 'The video may not be larger than '.app(QuestionMedia::class)->videoLimitLabel().'.'],
    ]);

    it('caps the video limit at the PHP upload limit', function () {
        $phpLimitKilobytes = (int) floor(UploadedFile::getMaxFilesize() / 1024);

        expect(app(QuestionMedia::class)->videoMaxKilobytes())->toBe(min(20480, $phpLimitKilobytes));
    });

    it('shows the form again with the typed values and the error', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        $createUrl = route('teacher.quizzes.questions.create', $quiz);

        $this->actingAs($teacher)
            ->from($createUrl)
            ->followingRedirects()
            ->post(route('teacher.quizzes.questions.store', $quiz), quizQuestionPayload([
                'prompt' => 'Which food helps us grow?',
                'choices' => ['Fish', '', 'Candy'],
                'correct_answer' => '2',
            ]))
            ->assertOk()
            ->assertSeeText('Which food helps us grow?')
            ->assertSee('value="Fish"', false)
            ->assertSee('value="Candy"', false)
            ->assertSeeText('Fill in choice B or remove it.');
    });

    it('refuses an uploaded video together with a video link', function () {
        Storage::fake('public');
        [$teacher, $quiz] = quizQuestionDraft();

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.questions.store', $quiz), quizQuestionPayload([
                'video' => UploadedFile::fake()->create('lesson.webm', 300, 'video/webm'),
                'video_url' => 'https://vimeo.com/76979871',
            ]))
            ->assertSessionHasErrors(['video' => QuestionContentRequest::BOTH_VIDEOS_MESSAGE]);

        expect($quiz->questions()->count())->toBe(0);
    });

    it('does not add questions to a published quiz', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        $quiz->forceFill(['status' => 'published'])->save();

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.questions.store', $quiz), quizQuestionPayload())
            ->assertRedirectToRoute('teacher.quizzes.show', $quiz)
            ->assertSessionHas('error', 'Unpublish the quiz to edit its questions.');

        expect($quiz->questions()->count())->toBe(0);
    });

    it('forbids teachers without the quiz permission before validating', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes);

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.questions.store', $quiz), [])
            ->assertForbidden()
            ->assertSessionHasNoErrors();
    });

    it('hides other teachers’ quizzes', function () {
        $quiz = Quiz::factory()->create();

        $this->actingAs(User::factory()->teaching()->create())
            ->post(route('teacher.quizzes.questions.store', $quiz), quizQuestionPayload())
            ->assertNotFound();
    });
});

describe('edit', function () {
    it('shows the question with its answers', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        QuizQuestion::factory()->for($quiz)->create();
        $question = QuizQuestion::factory()->for($quiz)->create([
            'prompt' => 'Which snack is the healthiest?',
            'choices' => ['A banana', 'A chocolate bar'],
            'correct_answer' => '1',
        ]);

        $response = $this->actingAs($teacher)->get(route('teacher.quizzes.questions.edit', [$quiz, $question]));

        $response->assertOk()
            ->assertViewHas('number', 2)
            ->assertViewHas('type', QuestionType::MultipleChoice)
            ->assertSeeText('Which snack is the healthiest?')
            ->assertSee('value="A chocolate bar"', false)
            ->assertDontSeeText('Its previous answers are replaced');
        expect($response->getContent())->toMatch('#<input type="radio" name="correct_answer" value="1"[^>]*\schecked(?=[\s/>])#');
    });

    it('warns before switching the question to another type', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        $question = QuizQuestion::factory()->for($quiz)->create();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.questions.edit', [$quiz, $question, 'type' => 'identification']))
            ->assertOk()
            ->assertViewHas('type', QuestionType::Identification)
            ->assertSeeText('Saving changes this question from multiple choice to identification.');
    });

    it('returns 404 for a question of another quiz', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        $otherQuiz = Quiz::factory()->for($teacher, 'teacher')->for($quiz->subject)->create();
        $question = QuizQuestion::factory()->for($otherQuiz)->create();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.questions.edit', [$quiz, $question]))
            ->assertNotFound();
    });

    it('sends the teacher back to the builder while the quiz is published', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        $question = QuizQuestion::factory()->for($quiz)->create();
        $quiz->forceFill(['status' => 'published'])->save();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.questions.edit', [$quiz, $question]))
            ->assertRedirectToRoute('teacher.quizzes.show', $quiz)
            ->assertSessionHas('error', 'Unpublish the quiz to edit its questions.');
    });
});

describe('update', function () {
    it('saves the changes, replaces the image, removes the video and asks for a new review', function () {
        Storage::fake('public');
        [$teacher, $quiz] = quizQuestionDraft();
        Storage::disk('public')->put('quiz-media/images/old.png', 'old');
        Storage::disk('public')->put('quiz-media/videos/old.mp4', 'old');
        $question = QuizQuestion::factory()->for($quiz)->create(['image_path' => 'quiz-media/images/old.png', 'video_path' => 'quiz-media/videos/old.mp4']);

        $response = $this->actingAs($teacher)->put(route('teacher.quizzes.questions.update', [$quiz, $question]), quizQuestionPayload([
            'prompt' => 'Which food group helps us grow?',
            'correct_answer' => '1',
            'image' => UploadedFile::fake()->image('new.webp'),
            'remove_video' => '1',
            'video_url' => 'https://vimeo.com/76979871',
        ]));

        $response->assertRedirect(route('teacher.quizzes.show', $quiz).'#question-'.$question->id)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Question saved.');
        $question->refresh();
        expect($question)
            ->prompt->toBe('Which food group helps us grow?')
            ->correct_answer->toBe('1')
            ->video_path->toBeNull()
            ->video_url->toBe('https://vimeo.com/76979871')
            ->and($question->image_path)->not->toBe('quiz-media/images/old.png');
        Storage::disk('public')->assertExists($question->image_path);
        Storage::disk('public')->assertMissing(['quiz-media/images/old.png', 'quiz-media/videos/old.mp4']);
        expect($quiz->refresh()->reviewed_at)->toBeNull();
    });

    it('keeps the review when nothing changed', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        $question = QuizQuestion::factory()->for($quiz)->create(quizQuestionPayload([
            'type' => QuestionType::MultipleChoice,
            'video_url' => null,
        ]));

        $this->actingAs($teacher)
            ->put(route('teacher.quizzes.questions.update', [$quiz, $question]), quizQuestionPayload())
            ->assertSessionHasNoErrors();

        expect($quiz->refresh()->reviewed_at)->not->toBeNull();
    });

    it('switches a multiple choice question to identification', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        $question = QuizQuestion::factory()->for($quiz)->create();

        $this->actingAs($teacher)->put(route('teacher.quizzes.questions.update', [$quiz, $question]), [
            'type' => 'identification',
            'prompt' => 'Name the mineral in milk that builds strong bones.',
            'accepted_answers' => 'Calcium',
            'points' => 1,
        ])->assertSessionHasNoErrors();

        expect($question->refresh())
            ->type->toBe(QuestionType::Identification)
            ->choices->toBeNull()
            ->correct_answer->toBe('Calcium');
    });

    it('refuses a video link while the uploaded video is kept', function () {
        Storage::fake('public');
        [$teacher, $quiz] = quizQuestionDraft();
        Storage::disk('public')->put('quiz-media/videos/clip.mp4', 'video');
        $question = QuizQuestion::factory()->for($quiz)->create(['video_path' => 'quiz-media/videos/clip.mp4']);

        $this->actingAs($teacher)
            ->put(route('teacher.quizzes.questions.update', [$quiz, $question]), quizQuestionPayload(['video_url' => 'https://youtu.be/dQw4w9WgXcQ']))
            ->assertSessionHasErrors(['video' => QuestionContentRequest::BOTH_VIDEOS_MESSAGE]);

        Storage::disk('public')->assertExists('quiz-media/videos/clip.mp4');
        expect($question->refresh()->video_url)->toBeNull();
    });

    it('keeps a media file that another question still uses', function () {
        Storage::fake('public');
        [$teacher, $quiz] = quizQuestionDraft();
        Storage::disk('public')->put('quiz-media/images/shared.png', 'image');
        $question = QuizQuestion::factory()->for($quiz)->create(['image_path' => 'quiz-media/images/shared.png']);
        QuizQuestion::factory()->for($quiz)->create(['image_path' => 'quiz-media/images/shared.png']);

        $this->actingAs($teacher)
            ->put(route('teacher.quizzes.questions.update', [$quiz, $question]), quizQuestionPayload(['remove_image' => '1']))
            ->assertSessionHasNoErrors();

        expect($question->refresh()->image_path)->toBeNull();
        Storage::disk('public')->assertExists('quiz-media/images/shared.png');
    });

    it('does not change questions once learners attempted the quiz', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        $question = QuizQuestion::factory()->for($quiz)->create(['prompt' => 'Original prompt']);
        QuizAttempt::factory()->for($quiz)->create();

        $this->actingAs($teacher)
            ->put(route('teacher.quizzes.questions.update', [$quiz, $question]), quizQuestionPayload())
            ->assertRedirectToRoute('teacher.quizzes.show', $quiz)
            ->assertSessionHas('error', 'Learners have already taken this quiz. Duplicate it to make changes.');

        expect($question->refresh()->prompt)->toBe('Original prompt');
    });

    it('returns 404 for a question of another quiz', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        $question = QuizQuestion::factory()->for(Quiz::factory()->for($teacher, 'teacher')->for($quiz->subject))->create();

        $this->actingAs($teacher)
            ->put(route('teacher.quizzes.questions.update', [$quiz, $question]), quizQuestionPayload())
            ->assertNotFound();
    });
});

describe('destroy', function () {
    it('deletes the question and its media files and asks for a new review', function () {
        Storage::fake('public');
        [$teacher, $quiz] = quizQuestionDraft();
        Storage::disk('public')->put('quiz-media/images/q.png', 'image');
        $question = QuizQuestion::factory()->for($quiz)->create(['image_path' => 'quiz-media/images/q.png']);

        $this->actingAs($teacher)
            ->delete(route('teacher.quizzes.questions.destroy', [$quiz, $question]))
            ->assertRedirect(route('teacher.quizzes.show', $quiz))
            ->assertSessionHas('status', 'Question deleted.');

        $this->assertModelMissing($question);
        Storage::disk('public')->assertMissing('quiz-media/images/q.png');
        expect($quiz->refresh()->reviewed_at)->toBeNull();
    });

    it('does not delete questions of a published quiz', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        $question = QuizQuestion::factory()->for($quiz)->create();
        $quiz->forceFill(['status' => 'published'])->save();

        $this->actingAs($teacher)
            ->delete(route('teacher.quizzes.questions.destroy', [$quiz, $question]))
            ->assertRedirectToRoute('teacher.quizzes.show', $quiz)
            ->assertSessionHas('error', 'Unpublish the quiz to edit its questions.');

        $this->assertModelExists($question);
    });

    it('returns 404 for a question of another quiz', function () {
        [$teacher, $quiz] = quizQuestionDraft();
        $question = QuizQuestion::factory()->for(Quiz::factory()->for($teacher, 'teacher')->for($quiz->subject))->create();

        $this->actingAs($teacher)
            ->delete(route('teacher.quizzes.questions.destroy', [$quiz, $question]))
            ->assertNotFound();

        $this->assertModelExists($question);
    });
});
