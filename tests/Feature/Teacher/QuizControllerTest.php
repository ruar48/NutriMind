<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\QuizStatus;
use App\Enums\UserRole;
use App\Http\Requests\Teacher\UpdateQuizRequest;
use App\Models\AcademicTerm;
use App\Models\Classroom;
use App\Models\LearningModule;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccess;
use App\Policies\QuizPolicy;
use App\Services\RolePermissions;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Build a valid quiz settings payload for the subject and grade.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function quizSettingsFormPayload(Subject $subject, GradeLevel $grade = GradeLevel::Five, array $overrides = []): array
{
    return [
        'title' => 'Go, Grow and Glow Foods Quiz',
        'subject_id' => $subject->id,
        'grade_level' => $grade->value,
        'learning_module_id' => null,
        'academic_term_id' => null,
        'instructions' => 'Choose the best answer.',
        'time_limit_minutes' => 20,
        'passing_score' => 80,
        'max_attempts' => 3,
        'shuffle_questions' => '1',
        'show_correct_answers' => '0',
        'classroom_ids' => [],
        'available_from' => null,
        'due_at' => null,
        ...$overrides,
    ];
}

describe('index', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.quizzes.index'))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('teacher.quizzes.index'))
            ->assertForbidden();
    })->with(['admin', 'parent', 'student']);

    it('lists only the teacher’s quizzes in subjects still assigned to them, with a locked notice', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $mine = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create(['title' => 'Healthy Snacks Quiz']);
        Quiz::factory()->for($teacher, 'teacher')->revokedSubject()->create(['title' => 'Revoked Subject Quiz']);
        Quiz::factory()->create(['title' => 'Another Teacher Quiz']);

        $response = $this->actingAs($teacher->refresh())->get(route('teacher.quizzes.index'));

        $response->assertOk()
            ->assertViewIs('teacher.quizzes.index')
            ->assertViewHas('quizzes', fn ($quizzes) => $quizzes->pluck('id')->all() === [$mine->id])
            ->assertViewHas('lockedCount', 1)
            ->assertSeeText('Healthy Snacks Quiz')
            ->assertDontSeeText('Revoked Subject Quiz')
            ->assertDontSeeText('Another Teacher Quiz')
            ->assertSeeText('1 quiz is locked because the administrator removed your access to its subject.');
    });

    it('shows the status tabs with counts and filters by status', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        Quiz::factory()->for($teacher, 'teacher')->for($subject)->count(2)->create();
        $published = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create();
        Quiz::factory()->closed()->for($teacher, 'teacher')->for($subject)->create();

        $response = $this->actingAs($teacher)->get(route('teacher.quizzes.index', ['status' => 'published']));

        $response->assertOk()
            ->assertViewHas('counts', ['all' => 4, 'draft' => 2, 'published' => 1, 'closed' => 1])
            ->assertViewHas('status', QuizStatus::Published)
            ->assertViewHas('quizzes', fn ($quizzes) => $quizzes->pluck('id')->all() === [$published->id]);
    });

    it('filters by subject, classroom and title', function () {
        $health = Subject::factory()->create();
        $science = Subject::factory()->create();
        $teacher = User::factory()->teaching($health, $science)->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->for($health)->create();
        $assigned = Quiz::factory()->for($teacher, 'teacher')->for($health)->create(['title' => 'Food Label Challenge']);
        $assigned->classrooms()->attach($classroom);
        Quiz::factory()->for($teacher, 'teacher')->for($health)->create(['title' => 'Food Safety Quiz']);
        Quiz::factory()->for($teacher, 'teacher')->for($science)->create(['title' => 'Food Chains Quiz']);

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.index', ['subject' => $health->id, 'classroom' => $classroom->id, 'search' => 'label']))
            ->assertOk()
            ->assertViewHas('quizzes', fn ($quizzes) => $quizzes->pluck('id')->all() === [$assigned->id])
            ->assertViewHas('counts', fn (array $counts) => $counts['all'] === 1);
    });

    it('ignores filter values outside the teacher’s options', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $quiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create();
        $otherClassroom = Classroom::factory()->create();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.index', ['classroom' => $otherClassroom->id, 'subject' => 'abc', 'status' => 'archived']))
            ->assertOk()
            ->assertViewHas('filters', fn (array $filters) => $filters['classroom'] === null && $filters['subject'] === null)
            ->assertViewHas('status', null)
            ->assertViewHas('quizzes', fn ($quizzes) => $quizzes->pluck('id')->all() === [$quiz->id]);
    });

    it('shows question totals, submitted attempts, the average score and the next due date', function () {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(9, 0));
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        QuizQuestion::factory()->for($quiz)->create(['points' => 2]);
        QuizQuestion::factory()->for($quiz)->create(['points' => 3]);
        [$past, $soon, $later] = Classroom::factory()->for($teacher, 'teacher')->for($subject)->count(3)->create(['grade_level' => GradeLevel::Five]);
        $quiz->classrooms()->attach([
            $past->id => ['due_at' => now()->subDay()],
            $soon->id => ['due_at' => now()->addDays(2)],
            $later->id => ['due_at' => now()->addDays(9)],
        ]);
        QuizAttempt::factory()->for($quiz)->create(['score' => 4, 'max_score' => 5, 'percentage' => 80]);
        QuizAttempt::factory()->for($quiz)->create(['score' => 3, 'max_score' => 5, 'percentage' => 60]);
        QuizAttempt::factory()->inProgress()->for($quiz)->create();

        $response = $this->actingAs($teacher)->get(route('teacher.quizzes.index'));

        $row = $response->viewData('quizzes')->first();
        expect($row->questions_count)->toBe(2)
            ->and((int) $row->questions_sum_points)->toBe(5)
            ->and($row->submitted_attempts_count)->toBe(2)
            ->and($row->average_percentage)->toBe(70.0)
            ->and($row->next_due_at->toDateTimeString())->toBe('2026-11-15 09:00:00');
        $response->assertSeeText('2 questions · 5 points')
            ->assertSeeText('Nov 15, 2026')
            ->assertSee('70%');
    });

    it('uses the same number of queries for one quiz and for many', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create();
        $makeQuiz = function () use ($teacher, $subject, $classroom): void {
            $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->hasQuestions(2)->create();
            $quiz->classrooms()->attach($classroom);
            QuizAttempt::factory()->for($quiz)->create();
        };
        $makeQuiz();
        $countQueries = function () use ($teacher): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($teacher)->get(route('teacher.quizzes.index'))->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $countQueries();
        $single = $countQueries();

        foreach (range(1, 5) as $ignored) {
            $makeQuiz();
        }
        $many = $countQueries();

        expect($many)->toBe($single);
    });

    it('shows a call to action when the teacher has no quizzes', function () {
        $teacher = User::factory()->teaching()->create();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.index'))
            ->assertOk()
            ->assertSeeText('No quizzes yet')
            ->assertSee(route('teacher.quizzes.create'));
    });

    it('explains when no quiz matches the filters', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        Quiz::factory()->for($teacher, 'teacher')->for($subject)->create(['title' => 'Hydration Quick Quiz']);

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.index', ['search' => 'volcano']))
            ->assertOk()
            ->assertSeeText('No quizzes match your filters');
    });
});

describe('create', function () {
    it('shows the form with the school’s default passing score and attempts', function () {
        $subject = Subject::factory()->create(['name' => 'Health']);
        $teacher = User::factory()->teaching($subject)->create();
        app(Settings::class)->setMany(['default_passing_score' => 80, 'default_max_attempts' => 3]);

        $response = $this->actingAs($teacher)->get(route('teacher.quizzes.create'));

        $response->assertOk()
            ->assertViewIs('teacher.quizzes.form')
            ->assertViewHas('quiz', fn (Quiz $quiz) => ! $quiz->exists && $quiz->passing_score === 80 && $quiz->max_attempts === 3 && $quiz->show_correct_answers)
            ->assertViewHas('subjectOptions', [$subject->id => 'Health'])
            ->assertSee('name="passing_score"', false)
            ->assertSee('value="80"', false);
    });

    it('treats an empty default attempt setting as unlimited', function () {
        $teacher = User::factory()->teaching()->create();
        app(Settings::class)->set('default_max_attempts', null);

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.create'))
            ->assertOk()
            ->assertViewHas('quiz', fn (Quiz $quiz) => $quiz->max_attempts === null);
    });

    it('prefills the subject, grade and classroom from one of the teacher’s classrooms', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Six]);

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.create', ['classroom' => $classroom->id]))
            ->assertOk()
            ->assertViewHas('quiz', fn (Quiz $quiz) => $quiz->subject_id === $subject->id && $quiz->grade_level === GradeLevel::Six)
            ->assertViewHas('assignedClassroomIds', [$classroom->id]);
    });

    it('does not prefill another teacher’s classroom', function () {
        $teacher = User::factory()->teaching()->create();
        $foreign = Classroom::factory()->create();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.create', ['classroom' => $foreign->id]))
            ->assertOk()
            ->assertViewHas('assignedClassroomIds', [])
            ->assertViewHas('quiz', fn (Quiz $quiz) => $quiz->subject_id === null);
    });

    it('forbids teachers without the quiz permission', function () {
        $teacher = User::factory()->teaching()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes);

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.create'))
            ->assertForbidden()
            ->assertSeeText(RolePermissions::deniedMessage(Permission::CreateQuizzes));
    });

    it('forbids teachers without an assigned subject', function () {
        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('teacher.quizzes.create'))
            ->assertForbidden()
            ->assertSeeText(QuizPolicy::NO_SUBJECT_MESSAGE);
    });
});

describe('store', function () {
    it('creates a draft quiz assigned to the chosen classrooms with one availability window', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $year = SchoolYear::factory()->active()->create();
        $term = AcademicTerm::factory()->for($year)->active()->create();
        $module = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);
        [$rizal, $mabini] = Classroom::factory()->for($teacher, 'teacher')->for($subject)->count(2)->create(['grade_level' => GradeLevel::Five]);

        $response = $this->actingAs($teacher)->post(route('teacher.quizzes.store'), quizSettingsFormPayload($subject, GradeLevel::Five, [
            'learning_module_id' => $module->id,
            'academic_term_id' => $term->id,
            'classroom_ids' => [$rizal->id, $mabini->id],
            'available_from' => '2026-11-16T08:00',
            'due_at' => '2026-11-20T17:00',
        ]));

        $quiz = Quiz::query()->sole();
        $response->assertRedirectToRoute('teacher.quizzes.show', $quiz)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Quiz created. Add your first question.');
        expect($quiz)
            ->teacher_id->toBe($teacher->id)
            ->status->toBe(QuizStatus::Draft)
            ->title->toBe('Go, Grow and Glow Foods Quiz')
            ->grade_level->toBe(GradeLevel::Five)
            ->learning_module_id->toBe($module->id)
            ->academic_term_id->toBe($term->id)
            ->time_limit_minutes->toBe(20)
            ->passing_score->toBe(80)
            ->max_attempts->toBe(3)
            ->shuffle_questions->toBeTrue()
            ->show_correct_answers->toBeFalse()
            ->reviewed_at->toBeNull()
            ->published_at->toBeNull();
        expect($quiz->classrooms()->orderBy('classrooms.id')->get()->map(fn (Classroom $classroom) => [
            $classroom->id,
            $classroom->pivot->available_from->toDateTimeString(),
            $classroom->pivot->due_at->toDateTimeString(),
            $classroom->pivot->assigned_at !== null,
        ])->all())->toBe([
            [$rizal->id, '2026-11-16 08:00:00', '2026-11-20 17:00:00', true],
            [$mabini->id, '2026-11-16 08:00:00', '2026-11-20 17:00:00', true],
        ]);
    });

    it('creates a quiz without classrooms, limits or dates', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();

        $this->actingAs($teacher)->post(route('teacher.quizzes.store'), quizSettingsFormPayload($subject, GradeLevel::Six, [
            'time_limit_minutes' => '',
            'max_attempts' => '',
            'shuffle_questions' => '0',
            'show_correct_answers' => '1',
        ]))->assertSessionHasNoErrors();

        expect(Quiz::query()->sole())
            ->time_limit_minutes->toBeNull()
            ->max_attempts->toBeNull()
            ->shuffle_questions->toBeFalse()
            ->show_correct_answers->toBeTrue()
            ->classrooms->toBeEmpty();
    });

    it('rejects invalid settings with clear messages', function (array $overrides, string $field, string $message) {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.store'), quizSettingsFormPayload($subject, GradeLevel::Five, $overrides))
            ->assertSessionHasErrors([$field => $message]);

        expect(Quiz::query()->count())->toBe(0);
    })->with([
        'missing title' => [['title' => ''], 'title', 'The title field is required.'],
        'long title' => [['title' => str_repeat('a', 151)], 'title', 'The title field must not be greater than 150 characters.'],
        'missing grade' => [['grade_level' => ''], 'grade_level', 'The grade level field is required.'],
        'unknown grade' => [['grade_level' => 4], 'grade_level', 'The selected grade level is invalid.'],
        'long instructions' => [['instructions' => str_repeat('a', 2001)], 'instructions', 'The instructions field must not be greater than 2000 characters.'],
        'time limit too long' => [['time_limit_minutes' => 181], 'time_limit_minutes', 'The time limit field must be between 1 and 180.'],
        'zero time limit' => [['time_limit_minutes' => 0], 'time_limit_minutes', 'The time limit field must be between 1 and 180.'],
        'missing passing score' => [['passing_score' => ''], 'passing_score', 'The passing score field is required.'],
        'passing score too high' => [['passing_score' => 101], 'passing_score', 'The passing score field must be between 1 and 100.'],
        'too many attempts' => [['max_attempts' => 11], 'max_attempts', 'The attempts allowed field must be between 1 and 10.'],
        'invalid opening date' => [['available_from' => 'soon'], 'available_from', 'The opening date field must be a valid date.'],
        'due before it opens' => [['available_from' => '2026-11-20T08:00', 'due_at' => '2026-11-19T08:00'], 'due_at', 'The due date must be on or after the date the quiz opens.'],
    ]);

    it('only accepts the teacher’s active subjects', function (Closure $makeSubject) {
        $teacher = User::factory()->teaching()->create();
        $subject = $makeSubject($teacher);

        $this->actingAs($teacher->refresh())
            ->post(route('teacher.quizzes.store'), quizSettingsFormPayload($subject))
            ->assertSessionHasErrors(['subject_id' => 'Choose one of your active subjects.']);
    })->with([
        'not assigned' => [fn (User $teacher) => Subject::factory()->create()],
        'inactive' => [fn (User $teacher) => tap(Subject::factory()->inactive()->create(), fn (Subject $subject) => $teacher->assignedSubjects()->attach($subject))],
    ]);

    it('only accepts a learning module of the chosen subject and grade', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $otherGradeModule = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Six]);

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.store'), quizSettingsFormPayload($subject, GradeLevel::Five, ['learning_module_id' => $otherGradeModule->id]))
            ->assertSessionHasErrors(['learning_module_id' => 'Choose a learning module of the selected subject and grade.']);
    });

    it('only accepts a quarter of the active school year', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        SchoolYear::factory()->active()->create();
        $oldTerm = AcademicTerm::factory()->for(SchoolYear::factory())->create();

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.store'), quizSettingsFormPayload($subject, GradeLevel::Five, ['academic_term_id' => $oldTerm->id]))
            ->assertSessionHasErrors(['academic_term_id' => 'Choose a quarter of the current school year.']);
    });

    it('only accepts the teacher’s active classrooms of the same subject and grade', function (Closure $makeClassroom) {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $classroom = $makeClassroom($teacher, $subject);

        $this->actingAs($teacher->refresh())
            ->post(route('teacher.quizzes.store'), quizSettingsFormPayload($subject, GradeLevel::Five, ['classroom_ids' => [$classroom->id]]))
            ->assertSessionHasErrors(['classroom_ids.0' => 'Choose only your active classrooms of the selected subject and grade.']);
    })->with([
        'another teacher’s' => [fn (User $teacher, Subject $subject) => Classroom::factory()->for($subject)->create(['grade_level' => GradeLevel::Five])],
        'archived' => [fn (User $teacher, Subject $subject) => Classroom::factory()->archived()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five])],
        'another grade' => [fn (User $teacher, Subject $subject) => Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Six])],
        'another subject' => [fn (User $teacher, Subject $subject) => Classroom::factory()->for($teacher, 'teacher')->create(['grade_level' => GradeLevel::Five])],
    ]);

    it('forbids teachers without the quiz permission before validating', function () {
        $teacher = User::factory()->teaching()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes);

        $this->actingAs($teacher)
            ->post(route('teacher.quizzes.store'), [])
            ->assertForbidden();

        expect(Quiz::query()->count())->toBe(0);
    });
});

describe('show', function () {
    it('shows the builder with the questions, answer key and publishing checklist', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $quiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create(['title' => 'Balanced Plate Check']);
        QuizQuestion::factory()->for($quiz)->create(['prompt' => 'Which food group gives energy?', 'choices' => ['Go foods', 'Grow foods'], 'correct_answer' => '0', 'explanation' => 'Rice is a Go food.']);
        QuizQuestion::factory()->identification()->for($quiz)->create(['prompt' => 'Name a Glow food.', 'correct_answer' => 'Malunggay|Carrot']);

        $response = $this->actingAs($teacher)->get(route('teacher.quizzes.show', $quiz));

        $response->assertOk()
            ->assertViewIs('teacher.quizzes.show')
            ->assertViewHas('checklist', ['hasQuestions' => true, 'hasClassroom' => false, 'isReviewed' => false, 'isReady' => false])
            ->assertSeeTextInOrder(['Which food group gives energy?', 'Name a Glow food.'])
            ->assertSeeText('Rice is a Go food.')
            ->assertSeeText('Malunggay')
            ->assertSee('data-correct-choice', false)
            ->assertSee('data-publish-checklist="incomplete"', false)
            ->assertSee(route('teacher.quizzes.questions.edit', [$quiz, $quiz->questions->first()]));
    });

    it('shows an empty state with the question types when there are no questions', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $quiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.show', $quiz))
            ->assertOk()
            ->assertSeeText('No questions yet')
            ->assertSee(route('teacher.quizzes.questions.create', [$quiz, 'type' => 'true_false']));
    });

    it('locks the questions of a published quiz', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->hasQuestions(1)->create();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.show', $quiz))
            ->assertOk()
            ->assertSeeText('Unpublish the quiz to edit its questions.')
            ->assertDontSee(route('teacher.quizzes.questions.edit', [$quiz, $quiz->questions->first()]))
            ->assertSee(route('teacher.quizzes.results.show', $quiz));
    });

    it('uses the same number of queries for few and many questions and classrooms', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $quiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $add = function () use ($quiz, $teacher, $subject): void {
            QuizQuestion::factory()->for($quiz)->create();
            $quiz->classrooms()->attach(Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]));
        };
        $add();
        $countQueries = function () use ($teacher, $quiz): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($teacher)->get(route('teacher.quizzes.show', $quiz))->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $countQueries();
        $few = $countQueries();

        foreach (range(1, 4) as $ignored) {
            $add();
        }

        expect($countQueries())->toBe($few);
    });

    it('hides other teachers’ quizzes', function () {
        $quiz = Quiz::factory()->create();

        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.quizzes.show', $quiz))
            ->assertNotFound();
    });

    it('forbids the owner once the subject was revoked', function () {
        $quiz = Quiz::factory()->revokedSubject()->create();

        $this->actingAs($quiz->teacher)
            ->get(route('teacher.quizzes.show', $quiz))
            ->assertForbidden()
            ->assertSeeText(QuizPolicy::SUBJECT_REVOKED_MESSAGE);
    });
});

describe('edit', function () {
    it('shows the settings form with the assigned classrooms and availability window', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $quiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'title' => 'Hydration Quick Quiz']);
        $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $quiz->classrooms()->attach($classroom, ['available_from' => '2026-11-16 08:00:00', 'due_at' => '2026-11-20 17:00:00']);

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.edit', $quiz))
            ->assertOk()
            ->assertViewIs('teacher.quizzes.form')
            ->assertViewHas('assignedClassroomIds', [$classroom->id])
            ->assertViewHas('hasAttempts', false)
            ->assertSee('value="Hydration Quick Quiz"', false)
            ->assertSee('value="2026-11-16T08:00"', false)
            ->assertSee('value="2026-11-20T17:00"', false)
            ->assertSee('name="subject_id"', false);
    });

    it('shows the locked settings of an attempted quiz as read-only', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['passing_score' => 85]);
        QuizAttempt::factory()->for($quiz)->create();

        $this->actingAs($teacher)
            ->get(route('teacher.quizzes.edit', $quiz))
            ->assertOk()
            ->assertSeeText('Some settings are locked')
            ->assertSeeText('85%')
            ->assertDontSee('name="subject_id"', false)
            ->assertDontSee('name="passing_score"', false)
            ->assertDontSee('name="shuffle_questions"', false);
    });

    it('hides other teachers’ quizzes', function () {
        $quiz = Quiz::factory()->create();

        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.quizzes.edit', $quiz))
            ->assertNotFound();
    });

    it('forbids teachers without the quiz permission', function () {
        $quiz = Quiz::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes);

        $this->actingAs($quiz->teacher)
            ->get(route('teacher.quizzes.edit', $quiz))
            ->assertForbidden();
    });
});

describe('update', function () {
    it('saves the settings, syncs the classrooms and keeps archived assignments', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $quiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        [$kept, $removed, $added] = Classroom::factory()->for($teacher, 'teacher')->for($subject)->count(3)->create(['grade_level' => GradeLevel::Five]);
        $archived = Classroom::factory()->archived()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $quiz->classrooms()->attach([$kept->id => [], $removed->id => [], $archived->id => ['due_at' => '2026-03-01 17:00:00']]);

        $response = $this->actingAs($teacher)->put(route('teacher.quizzes.update', $quiz), quizSettingsFormPayload($subject, GradeLevel::Five, [
            'title' => 'Balanced Plate Check',
            'classroom_ids' => [$kept->id, $added->id],
            'due_at' => '2026-11-27T17:00',
        ]));

        $response->assertRedirectToRoute('teacher.quizzes.show', $quiz)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Quiz settings saved.');
        expect($quiz->refresh())->title->toBe('Balanced Plate Check')->passing_score->toBe(80);
        $assignments = $quiz->classrooms()->get()->mapWithKeys(fn (Classroom $classroom) => [$classroom->id => $classroom->pivot->due_at?->toDateTimeString()]);
        expect($assignments->keys()->sort()->values()->all())->toBe(collect([$kept->id, $added->id, $archived->id])->sort()->values()->all())
            ->and($assignments[$kept->id])->toBe('2026-11-27 17:00:00')
            ->and($assignments[$added->id])->toBe('2026-11-27 17:00:00')
            ->and($assignments[$archived->id])->toBe('2026-03-01 17:00:00');
    });

    it('moves a quiz without attempts to another subject and drops classrooms of the old one', function () {
        $health = Subject::factory()->create();
        $science = Subject::factory()->create();
        $teacher = User::factory()->teaching($health, $science)->create();
        $quiz = Quiz::factory()->for($teacher, 'teacher')->for($health)->create(['grade_level' => GradeLevel::Five]);
        $healthClass = Classroom::factory()->for($teacher, 'teacher')->for($health)->create(['grade_level' => GradeLevel::Five]);
        $quiz->classrooms()->attach($healthClass);

        $this->actingAs($teacher)
            ->put(route('teacher.quizzes.update', $quiz), quizSettingsFormPayload($health, GradeLevel::Five, ['subject_id' => $science->id, 'classroom_ids' => [$healthClass->id]]))
            ->assertSessionHasErrors(['classroom_ids.0' => 'Choose only your active classrooms of the selected subject and grade.']);

        $this->actingAs($teacher)
            ->put(route('teacher.quizzes.update', $quiz), quizSettingsFormPayload($health, GradeLevel::Five, ['subject_id' => $science->id, 'classroom_ids' => []]))
            ->assertSessionHasNoErrors();

        expect($quiz->refresh()->subject_id)->toBe($science->id)
            ->and($quiz->classrooms()->count())->toBe(0);
    });

    it('rejects changes to settings that learner attempts lock', function (string $field, mixed $value, string $label) {
        $subject = Subject::factory()->create();
        $other = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject, $other)->create();
        $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create([
            'grade_level' => GradeLevel::Five, 'passing_score' => 75, 'time_limit_minutes' => 20, 'max_attempts' => 2, 'shuffle_questions' => false,
        ]);
        $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $quiz->classrooms()->attach($classroom);
        QuizAttempt::factory()->for($quiz)->for($classroom)->create();
        $value = $value === 'other-subject' ? $other->id : $value;

        $this->actingAs($teacher)
            ->put(route('teacher.quizzes.update', $quiz), [
                'title' => 'Renamed',
                'passing_score' => 75,
                'classroom_ids' => [$classroom->id],
                $field => $value,
            ])
            ->assertSessionHasErrors([$field => 'Learners have already taken this quiz, so the '.$label.' cannot change.']);

        expect($quiz->refresh()->title)->not->toBe('Renamed');
    })->with([
        'subject' => ['subject_id', 'other-subject', 'subject'],
        'grade' => ['grade_level', 6, 'grade level'],
        'passing score' => ['passing_score', 90, 'passing score'],
        'time limit' => ['time_limit_minutes', 30, 'time limit'],
        'attempts' => ['max_attempts', '', 'attempts allowed'],
        'shuffle' => ['shuffle_questions', '1', 'shuffle questions'],
    ]);

    it('saves the unlocked settings of an attempted quiz and keeps the locked ones', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'passing_score' => 75, 'show_correct_answers' => true]);
        $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $quiz->classrooms()->attach($classroom);
        QuizAttempt::factory()->for($quiz)->for($classroom)->create();

        $this->actingAs($teacher)
            ->put(route('teacher.quizzes.update', $quiz), [
                'title' => 'Healthy Snacks Quiz (Q2)',
                'instructions' => 'Answer every question.',
                'show_correct_answers' => '0',
                'classroom_ids' => [$classroom->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirectToRoute('teacher.quizzes.show', $quiz);

        expect($quiz->refresh())
            ->title->toBe('Healthy Snacks Quiz (Q2)')
            ->show_correct_answers->toBeFalse()
            ->passing_score->toBe(75)
            ->grade_level->toBe(GradeLevel::Five);
    });

    it('keeps classrooms with learner attempts assigned', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $attempted = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'name' => 'Grade 5 – Rizal']);
        $other = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $quiz->classrooms()->attach([$attempted->id, $other->id]);
        QuizAttempt::factory()->for($quiz)->for($attempted)->create();

        $this->actingAs($teacher)
            ->put(route('teacher.quizzes.update', $quiz), ['title' => $quiz->title, 'passing_score' => $quiz->passing_score, 'classroom_ids' => [$other->id]])
            ->assertSessionHasErrors(['classroom_ids' => 'Grade 5 – Rizal already has learner attempts, so it must stay assigned.']);

        expect($quiz->classrooms()->count())->toBe(2);
    });

    it('keeps at least one classroom on a published quiz', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $quiz->classrooms()->attach($classroom);

        $this->actingAs($teacher)
            ->put(route('teacher.quizzes.update', $quiz), quizSettingsFormPayload($subject, GradeLevel::Five, ['classroom_ids' => []]))
            ->assertSessionHasErrors(['classroom_ids' => UpdateQuizRequest::PUBLISHED_NEEDS_CLASSROOM_MESSAGE]);
    });

    it('lets the quiz keep a quarter of a past school year', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $oldTerm = AcademicTerm::factory()->for(SchoolYear::factory())->create();
        $quiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'academic_term_id' => $oldTerm->id]);

        $this->actingAs($teacher)
            ->put(route('teacher.quizzes.update', $quiz), quizSettingsFormPayload($subject, GradeLevel::Five, ['academic_term_id' => $oldTerm->id]))
            ->assertSessionHasNoErrors();
    });

    it('hides other teachers’ quizzes before validating', function () {
        $quiz = Quiz::factory()->create();

        $this->actingAs(User::factory()->teaching()->create())
            ->put(route('teacher.quizzes.update', $quiz), [])
            ->assertNotFound();
    });

    it('forbids the owner once the subject was revoked', function () {
        $quiz = Quiz::factory()->revokedSubject()->create();

        $this->actingAs($quiz->teacher)
            ->put(route('teacher.quizzes.update', $quiz), [])
            ->assertForbidden();
    });
});

describe('destroy', function () {
    it('deletes the quiz, its questions and their media files', function () {
        Storage::fake('public');
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $quiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create(['title' => 'Food Label Challenge']);
        Storage::disk('public')->put('quiz-media/images/label.png', 'image');
        Storage::disk('public')->put('quiz-media/videos/label.mp4', 'video');
        $question = QuizQuestion::factory()->for($quiz)->create(['image_path' => 'quiz-media/images/label.png', 'video_path' => 'quiz-media/videos/label.mp4']);

        $this->actingAs($teacher)
            ->delete(route('teacher.quizzes.destroy', $quiz))
            ->assertRedirectToRoute('teacher.quizzes.index')
            ->assertSessionHas('status', '“Food Label Challenge” was deleted.');

        $this->assertModelMissing($quiz);
        $this->assertModelMissing($question);
        Storage::disk('public')->assertMissing(['quiz-media/images/label.png', 'quiz-media/videos/label.mp4']);
    });

    it('refuses to delete a quiz that learners attempted', function () {
        $quiz = Quiz::factory()->published()->create();
        QuizAttempt::factory()->for($quiz)->create();

        $this->actingAs($quiz->teacher)
            ->delete(route('teacher.quizzes.destroy', $quiz))
            ->assertForbidden()
            ->assertSeeText(QuizPolicy::HAS_ATTEMPTS_MESSAGE);

        $this->assertModelExists($quiz);
    });

    it('hides other teachers’ quizzes', function () {
        $quiz = Quiz::factory()->create();

        $this->actingAs(User::factory()->teaching()->create())
            ->delete(route('teacher.quizzes.destroy', $quiz))
            ->assertNotFound();

        $this->assertModelExists($quiz);
    });
});
