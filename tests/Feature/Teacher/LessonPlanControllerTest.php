<?php

use App\Enums\GradeLevel;
use App\Enums\LessonPlanStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\Quiz;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use App\Policies\LessonPlanPolicy;
use App\Services\RolePermissions;
use Illuminate\Support\Facades\DB;

/**
 * A valid lesson plan form submission.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function lessonPlanPayload(Subject $subject, array $overrides = []): array
{
    return [
        'subject_id' => $subject->id,
        'grade_level' => 5,
        'academic_term_id' => '',
        'learning_module_id' => '',
        'title' => 'Sorting Go, Grow and Glow Foods',
        'competency_code' => 'h5n-ia-1',
        'learning_competency' => 'Classifies foods into Go, Grow and Glow groups.',
        'objectives' => 'Learners name two foods from each group.',
        'week_number' => 3,
        'starts_on' => '2026-11-16',
        'ends_on' => '2026-11-20',
        'status' => LessonPlanStatus::Planned->value,
        'notes' => 'Bring real fruits and vegetables.',
        ...$overrides,
    ];
}

describe('access', function () {
    it('sends guests to the sign-in page', function () {
        $lessonPlan = LessonPlan::factory()->create();

        $this->get(route('teacher.lesson-plans.index'))->assertRedirectToRoute('login');
        $this->get(route('teacher.lesson-plans.create'))->assertRedirectToRoute('login');
        $this->post(route('teacher.lesson-plans.store'), [])->assertRedirectToRoute('login');
        $this->get(route('teacher.lesson-plans.show', $lessonPlan))->assertRedirectToRoute('login');
        $this->get(route('teacher.lesson-plans.edit', $lessonPlan))->assertRedirectToRoute('login');
        $this->put(route('teacher.lesson-plans.update', $lessonPlan), [])->assertRedirectToRoute('login');
        $this->delete(route('teacher.lesson-plans.destroy', $lessonPlan))->assertRedirectToRoute('login');
    });

    it('forbids the other portals', function (UserRole $role) {
        $lessonPlan = LessonPlan::factory()->create();
        $this->actingAs(User::factory()->{$role->value}()->create());

        $this->get(route('teacher.lesson-plans.index'))->assertForbidden();
        $this->get(route('teacher.lesson-plans.show', $lessonPlan))->assertForbidden();
        $this->delete(route('teacher.lesson-plans.destroy', $lessonPlan))->assertForbidden();

        $this->assertModelExists($lessonPlan);
    })->with([UserRole::Admin, UserRole::Parent]);

    it('hides another teacher\'s lesson plan', function (string $method, string $route) {
        $lessonPlan = LessonPlan::factory()->create(['title' => 'Kept']);
        $other = User::factory()->teaching($lessonPlan->subject)->create();

        $this->actingAs($other)
            ->{$method}(route($route, $lessonPlan), $method === 'put' ? lessonPlanPayload($lessonPlan->subject) : [])
            ->assertNotFound();

        expect($lessonPlan->refresh()->title)->toBe('Kept');
    })->with([
        'show' => ['get', 'teacher.lesson-plans.show'],
        'edit' => ['get', 'teacher.lesson-plans.edit'],
        'update' => ['put', 'teacher.lesson-plans.update'],
        'destroy' => ['delete', 'teacher.lesson-plans.destroy'],
    ]);

    it('tells the owner when the administrator revoked the plan\'s subject', function (string $method, string $route) {
        $lessonPlan = LessonPlan::factory()->revokedSubject()->create();

        $this->actingAs($lessonPlan->teacher)
            ->{$method}(route($route, $lessonPlan), $method === 'put' ? ['title' => ''] : [])
            ->assertForbidden()
            ->assertSeeText(LessonPlanPolicy::SUBJECT_REVOKED_MESSAGE)
            ->assertSessionHasNoErrors();

        $this->assertModelExists($lessonPlan);
    })->with([
        'show' => ['get', 'teacher.lesson-plans.show'],
        'update' => ['put', 'teacher.lesson-plans.update'],
        'destroy' => ['delete', 'teacher.lesson-plans.destroy'],
    ]);

    it('refuses teachers whose lesson plan permission was revoked', function (string $method, string $route, bool $withPlan) {
        $lessonPlan = LessonPlan::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageLessonPlans);

        $this->actingAs($lessonPlan->teacher)
            ->{$method}(route($route, $withPlan ? $lessonPlan : []), [])
            ->assertForbidden()
            ->assertSeeText('You do not have permission to manage lesson plans.');
    })->with([
        'index' => ['get', 'teacher.lesson-plans.index', false],
        'create' => ['get', 'teacher.lesson-plans.create', false],
        'store' => ['post', 'teacher.lesson-plans.store', false],
        'show' => ['get', 'teacher.lesson-plans.show', true],
        'destroy' => ['delete', 'teacher.lesson-plans.destroy', true],
    ]);

    it('refuses a teacher without assigned subjects to write a plan', function () {
        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('teacher.lesson-plans.create'))
            ->assertForbidden()
            ->assertSeeText(LessonPlanPolicy::NO_SUBJECT_MESSAGE);
    });
});

describe('index', function () {
    it('lists the teacher\'s plans in assigned subjects with their alignment', function () {
        $subject = Subject::factory()->create(['name' => 'Health']);
        $teacher = User::factory()->teaching($subject)->create();
        $aligned = LessonPlan::factory()->for($teacher, 'teacher')->for($subject)->create(['title' => 'Smart Snacking', 'starts_on' => '2026-11-16', 'status' => LessonPlanStatus::Ongoing]);
        $aligned->activities()->attach(LearningActivity::factory()->count(2)->create());
        $aligned->missions()->attach(Mission::factory()->create());
        LessonPlan::factory()->for($teacher, 'teacher')->for($subject)->create(['title' => 'Keeping Food Safe', 'starts_on' => '2026-11-09']);
        LessonPlan::factory()->for($subject)->create(['title' => 'Another Teacher Plan']);
        $revoked = LessonPlan::factory()->for($teacher, 'teacher')->revokedSubject()->create(['title' => 'Revoked Subject Plan']);

        $response = $this->actingAs($teacher)->get(route('teacher.lesson-plans.index'));

        $response->assertOk()
            ->assertViewIs('teacher.lesson-plans.index')
            ->assertSeeTextInOrder(['Smart Snacking', 'Keeping Food Safe'])
            ->assertDontSeeText('Another Teacher Plan')
            ->assertDontSeeText('Revoked Subject Plan')
            ->assertSee('data-alignment="aligned"', false)
            ->assertSee('data-alignment="unaligned"', false)
            ->assertViewHas('summary', ['total' => 2, 'aligned' => 1, 'unaligned' => 1, 'ongoing' => 1]);
        expect($response->viewData('lessonPlans')->firstWhere('id', $aligned->id))
            ->activities_count->toBe(2)
            ->quizzes_count->toBe(0)
            ->missions_count->toBe(1);
        $this->assertModelExists($revoked);
    });

    it('filters by subject, quarter, status, alignment and search', function (array $query, string $expected) {
        $health = Subject::factory()->create();
        $science = Subject::factory()->create();
        $teacher = User::factory()->teaching($health, $science)->create();
        $term = AcademicTerm::factory()->create();
        $aligned = LessonPlan::factory()->for($teacher, 'teacher')->for($health)->for($term, 'academicTerm')->create(['title' => 'Smart Snacking', 'competency_code' => 'H5N-IC-2', 'status' => LessonPlanStatus::Completed]);
        $aligned->quizzes()->attach(Quiz::factory()->create());
        LessonPlan::factory()->for($teacher, 'teacher')->for($science)->create(['title' => 'Plant Food Chains', 'competency_code' => 'S5LT-IIA-1', 'status' => LessonPlanStatus::Planned]);

        $query = array_map(fn (mixed $value): mixed => match ($value) {
            'health' => $health->id,
            'term' => $term->id,
            default => $value,
        }, $query);
        $titles = $this->actingAs($teacher)
            ->get(route('teacher.lesson-plans.index', $query))
            ->assertOk()
            ->viewData('lessonPlans')
            ->pluck('title')
            ->all();

        expect($titles)->toBe([$expected]);
    })->with([
        'subject' => [['subject' => 'health'], 'Smart Snacking'],
        'quarter' => [['term' => 'term'], 'Smart Snacking'],
        'status' => [['status' => 'planned'], 'Plant Food Chains'],
        'aligned' => [['alignment' => 'aligned'], 'Smart Snacking'],
        'not aligned' => [['alignment' => 'unaligned'], 'Plant Food Chains'],
        'search code' => [['search' => 's5lt'], 'Plant Food Chains'],
    ]);

    it('ignores a subject filter outside the teacher\'s subjects', function () {
        $lessonPlan = LessonPlan::factory()->create();

        $this->actingAs($lessonPlan->teacher)
            ->get(route('teacher.lesson-plans.index', ['subject' => Subject::factory()->create()->id]))
            ->assertOk()
            ->assertViewHas('lessonPlans', fn ($lessonPlans): bool => $lessonPlans->total() === 1);
    });

    it('renders more lesson plans without more queries', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $queries = function () use ($teacher): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($teacher)->get(route('teacher.lesson-plans.index'))->assertOk();

            return count(DB::getQueryLog());
        };
        LessonPlan::factory()->for($teacher, 'teacher')->for($subject)->for(AcademicTerm::factory(), 'academicTerm')->create();
        $queries();
        $single = $queries();

        foreach (LessonPlan::factory()->for($teacher, 'teacher')->for($subject)->for(AcademicTerm::factory(), 'academicTerm')->count(4)->create() as $lessonPlan) {
            $lessonPlan->activities()->attach(LearningActivity::factory()->create());
        }

        expect($queries())->toBe($single);
    });

    it('paginates fifteen plans per page', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        LessonPlan::factory()->for($teacher, 'teacher')->for($subject)->count(16)->create();

        $this->actingAs($teacher)
            ->get(route('teacher.lesson-plans.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('lessonPlans', fn ($lessonPlans): bool => $lessonPlans->count() === 1 && $lessonPlans->total() === 16);
    });

    it('shows the empty states', function () {
        $lessonPlan = LessonPlan::factory()->create();
        $newTeacher = User::factory()->teaching()->create();

        $this->actingAs($newTeacher)->get(route('teacher.lesson-plans.index'))
            ->assertOk()
            ->assertSeeText('No lesson plans yet');

        $this->actingAs($lessonPlan->teacher)->get(route('teacher.lesson-plans.index', ['search' => 'zzz']))
            ->assertOk()
            ->assertSeeText('No lesson plans match your filters');
    });
});

describe('create', function () {
    it('shows the form with active subjects, this year\'s quarters and published modules', function () {
        $health = Subject::factory()->create(['name' => 'Health']);
        $inactive = Subject::factory()->inactive()->create(['name' => 'Retired Subject']);
        $teacher = User::factory()->teaching($health, $inactive)->create();
        $year = SchoolYear::factory()->active()->create();
        $current = AcademicTerm::factory()->for($year)->active()->create(['name' => '1st Quarter']);
        AcademicTerm::factory()->create(['name' => 'Old Year Quarter']);
        LearningModule::factory()->for($health)->create(['title' => 'Healthy Snacks']);
        LearningModule::factory()->for($health)->unpublished()->create(['title' => 'Draft Module']);
        LearningModule::factory()->create(['title' => 'Other Subject Module']);

        $response = $this->actingAs($teacher)->get(route('teacher.lesson-plans.create'));

        $response->assertOk()
            ->assertViewIs('teacher.lesson-plans.form')
            ->assertSeeText('New lesson plan')
            ->assertSeeText('Health')
            ->assertDontSeeText('Retired Subject')
            ->assertSeeText('1st Quarter')
            ->assertDontSeeText('Old Year Quarter')
            ->assertSeeText('Healthy Snacks')
            ->assertDontSeeText('Draft Module')
            ->assertDontSeeText('Other Subject Module');
        expect($response->viewData('lessonPlan'))
            ->academic_term_id->toBe($current->id)
            ->status->toBe(LessonPlanStatus::Planned);
    });

    it('warns a teacher whose subjects are all inactive', function () {
        $teacher = User::factory()->teaching(Subject::factory()->inactive()->create())->create();

        $this->actingAs($teacher)
            ->get(route('teacher.lesson-plans.create'))
            ->assertOk()
            ->assertSeeText('No active subject to plan for');
    });
});

describe('store', function () {
    it('creates a lesson plan for the teacher', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $year = SchoolYear::factory()->active()->create();
        $term = AcademicTerm::factory()->for($year)->create();
        $module = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);

        $response = $this->actingAs($teacher)->post(route('teacher.lesson-plans.store'), lessonPlanPayload($subject, [
            'academic_term_id' => $term->id,
            'learning_module_id' => $module->id,
        ]));

        $lessonPlan = LessonPlan::query()->sole();
        $response->assertRedirectToRoute('teacher.lesson-plans.show', $lessonPlan)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Lesson plan created. Align game activities, quizzes and missions to it below.');
        expect($lessonPlan)
            ->teacher_id->toBe($teacher->id)
            ->subject_id->toBe($subject->id)
            ->academic_term_id->toBe($term->id)
            ->learning_module_id->toBe($module->id)
            ->grade_level->toBe(GradeLevel::Five)
            ->competency_code->toBe('H5N-IA-1')
            ->week_number->toBe(3)
            ->status->toBe(LessonPlanStatus::Planned)
            ->and($lessonPlan->starts_on->toDateString())->toBe('2026-11-16');
    });

    it('requires the subject, grade, title and status', function () {
        $this->actingAs(User::factory()->teaching()->create())
            ->post(route('teacher.lesson-plans.store'), [])
            ->assertSessionHasErrors([
                'subject_id' => 'The subject field is required.',
                'grade_level' => 'The grade level field is required.',
                'title' => 'The title field is required.',
                'status' => 'The status field is required.',
            ]);

        expect(LessonPlan::query()->count())->toBe(0);
    });

    it('rejects invalid lesson plan details', function (array $overrides, string $field, string $message) {
        $subject = Subject::factory()->create();

        $this->actingAs(User::factory()->teaching($subject)->create())
            ->post(route('teacher.lesson-plans.store'), lessonPlanPayload($subject, $overrides))
            ->assertSessionHasErrors([$field => $message]);

        expect(LessonPlan::query()->count())->toBe(0);
    })->with([
        'unknown grade' => [['grade_level' => 3], 'grade_level', 'The selected grade level is invalid.'],
        'long title' => [['title' => str_repeat('a', 151)], 'title', 'The title field must not be greater than 150 characters.'],
        'long code' => [['competency_code' => str_repeat('a', 41)], 'competency_code', 'The competency code field must not be greater than 40 characters.'],
        'long competency' => [['learning_competency' => str_repeat('a', 1001)], 'learning_competency', 'The learning competency field must not be greater than 1000 characters.'],
        'long objectives' => [['objectives' => str_repeat('a', 2001)], 'objectives', 'The objectives field must not be greater than 2000 characters.'],
        'week zero' => [['week_number' => 0], 'week_number', 'The week number field must be between 1 and 12.'],
        'week thirteen' => [['week_number' => 13], 'week_number', 'The week number field must be between 1 and 12.'],
        'bad start date' => [['starts_on' => 'soon'], 'starts_on', 'The start date field must be a valid date.'],
        'ends before it starts' => [['ends_on' => '2026-11-13'], 'ends_on', 'The end date must be on or after the start date.'],
        'unknown status' => [['status' => 'paused'], 'status', 'The selected status is invalid.'],
        'long notes' => [['notes' => str_repeat('a', 2001)], 'notes', 'The notes field must not be greater than 2000 characters.'],
    ]);

    it('only accepts the teacher\'s active subjects', function (string $case) {
        $teacher = User::factory()->teaching(Subject::factory()->create())->create();
        $subject = match ($case) {
            'not assigned' => Subject::factory()->create(),
            'inactive' => tap(Subject::factory()->inactive()->create(), fn (Subject $subject) => $teacher->assignedSubjects()->attach($subject)),
        };

        $this->actingAs($teacher)
            ->post(route('teacher.lesson-plans.store'), lessonPlanPayload($subject))
            ->assertSessionHasErrors(['subject_id' => 'Choose one of your active subjects.']);
    })->with(['not assigned', 'inactive']);

    it('only accepts a quarter of the active school year', function () {
        $subject = Subject::factory()->create();
        SchoolYear::factory()->active()->create();
        $oldTerm = AcademicTerm::factory()->create();

        $this->actingAs(User::factory()->teaching($subject)->create())
            ->post(route('teacher.lesson-plans.store'), lessonPlanPayload($subject, ['academic_term_id' => $oldTerm->id]))
            ->assertSessionHasErrors(['academic_term_id' => 'Choose a quarter of the active school year.']);
    });

    it('only accepts a published module of the plan\'s subject and grade', function (string $case) {
        $subject = Subject::factory()->create();
        $module = match ($case) {
            'other subject' => LearningModule::factory()->create(['grade_level' => GradeLevel::Five]),
            'other grade' => LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Six]),
            'unpublished' => LearningModule::factory()->for($subject)->unpublished()->create(['grade_level' => GradeLevel::Five]),
        };

        $this->actingAs(User::factory()->teaching($subject)->create())
            ->post(route('teacher.lesson-plans.store'), lessonPlanPayload($subject, ['learning_module_id' => $module->id]))
            ->assertSessionHasErrors(['learning_module_id' => 'Choose a published module of the same subject and grade level.']);
    })->with(['other subject', 'other grade', 'unpublished']);
});

describe('show', function () {
    it('shows the alignment editor with suggested modules, quizzes and missions', function () {
        $subject = Subject::factory()->create(['name' => 'Health']);
        $teacher = User::factory()->teaching($subject)->create();
        $suggested = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IA-1', 'title' => 'Go, Grow and Glow Foods']);
        $other = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IC-9', 'title' => 'Food Safety at Home']);
        $alignedActivity = LearningActivity::factory()->for($suggested, 'module')->create(['title' => 'Sort the Food Groups']);
        LearningActivity::factory()->for($suggested, 'module')->create(['title' => 'Market Day Challenge']);
        LearningActivity::factory()->for($other, 'module')->count(2)->create();
        $quiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->hasQuestions(4)->create(['grade_level' => GradeLevel::Five, 'title' => 'Food Groups Quiz']);
        $mission = Mission::factory()->for($suggested, 'module')->create(['title' => 'Rainbow Plate']);
        Mission::factory()->for($subject)->create(['grade_level' => null, 'title' => 'Water Warrior']);
        $lessonPlan = LessonPlan::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IA-1', 'title' => 'Sorting Foods']);
        $lessonPlan->activities()->attach($alignedActivity);
        $lessonPlan->quizzes()->attach($quiz);

        $response = $this->actingAs($teacher)->get(route('teacher.lesson-plans.show', $lessonPlan));

        $response->assertOk()
            ->assertViewIs('teacher.lesson-plans.show')
            ->assertSeeTextInOrder(['Go, Grow and Glow Foods', 'Food Safety at Home'])
            ->assertSeeText('Suggested')
            ->assertSeeText('Food Groups Quiz')
            ->assertSeeText('4 questions')
            ->assertSeeText('Rainbow Plate')
            ->assertSeeText('Water Warrior')
            ->assertSee('data-alignment="aligned"', false)
            ->assertSee('data-coverage-status="covered"', false)
            ->assertSee('action="'.route('teacher.lesson-plans.alignment.update', $lessonPlan).'"', false)
            ->assertViewHas('aligned', ['activities' => [$alignedActivity->id], 'quizzes' => [$quiz->id], 'missions' => []])
            ->assertViewHas('suggestedMissionIds', [$mission->id]);
        expect($response->viewData('activityGroups')->first()['suggested'])->toBeTrue();
        expect(preg_match('#<input[^>]*name="activities\[\]"[^>]*value="'.$alignedActivity->id.'"[^>]*\schecked(?=[\s/>])#', $response->getContent()))->toBe(1);
    });

    it('renders more content options without more queries', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $lessonPlan = LessonPlan::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IA-1']);
        $addContent = function () use ($subject, $teacher): void {
            $module = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IA-1']);
            LearningActivity::factory()->for($module, 'module')->count(2)->create();
            Mission::factory()->for($module, 'module')->create();
            Quiz::factory()->for($teacher, 'teacher')->for($subject)->hasQuestions(2)->create(['grade_level' => GradeLevel::Five]);
        };
        $queries = function () use ($teacher, $lessonPlan): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($teacher)->get(route('teacher.lesson-plans.show', $lessonPlan))->assertOk();

            return count(DB::getQueryLog());
        };
        $addContent();
        $queries();
        $single = $queries();

        $addContent();
        $addContent();

        expect($queries())->toBe($single);
    });

    it('shows the empty states when no content fits the plan', function () {
        $lessonPlan = LessonPlan::factory()->create(['competency_code' => null]);

        $this->actingAs($lessonPlan->teacher)
            ->get(route('teacher.lesson-plans.show', $lessonPlan))
            ->assertOk()
            ->assertSeeText('Not aligned yet')
            ->assertSeeText('No game activities for')
            ->assertSeeText('No quizzes for')
            ->assertSeeText('No missions for')
            ->assertSee('data-coverage-status="none"', false);
    });
});

describe('edit', function () {
    it('shows the plan in the form, keeping its inactive subject and old quarter', function () {
        $subject = Subject::factory()->inactive()->create(['name' => 'Old Health']);
        $teacher = User::factory()->teaching($subject)->create();
        $oldTerm = AcademicTerm::factory()->create(['name' => '4th Quarter']);
        $lessonPlan = LessonPlan::factory()->for($teacher, 'teacher')->for($subject)->for($oldTerm, 'academicTerm')->create(['title' => 'Keeping Food Safe']);

        $this->actingAs($teacher)
            ->get(route('teacher.lesson-plans.edit', $lessonPlan))
            ->assertOk()
            ->assertViewIs('teacher.lesson-plans.form')
            ->assertSee('value="Keeping Food Safe"', false)
            ->assertSeeText('Old Health (inactive)')
            ->assertSeeText('4th Quarter (SY '.$oldTerm->schoolYear->name.')');
    });
});

describe('update', function () {
    it('updates the plan', function () {
        $lessonPlan = LessonPlan::factory()->create(['title' => 'Old title']);

        $this->actingAs($lessonPlan->teacher)
            ->put(route('teacher.lesson-plans.update', $lessonPlan), lessonPlanPayload($lessonPlan->subject, [
                'grade_level' => $lessonPlan->grade_level->value,
                'title' => 'Smart Snacking',
                'status' => LessonPlanStatus::Completed->value,
            ]))
            ->assertRedirectToRoute('teacher.lesson-plans.show', $lessonPlan)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Lesson plan updated.');

        expect($lessonPlan->refresh())
            ->title->toBe('Smart Snacking')
            ->status->toBe(LessonPlanStatus::Completed)
            ->competency_code->toBe('H5N-IA-1');
    });

    it('removes the alignments that no longer match a changed grade', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $lessonPlan = LessonPlan::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $lessonPlan->activities()->attach(LearningActivity::factory()->for(LearningModule::factory()->for($subject)->state(['grade_level' => GradeLevel::Five]), 'module')->create());
        $lessonPlan->missions()->attach(Mission::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]));
        $lessonPlan->missions()->attach($bothGrades = Mission::factory()->for($subject)->create(['grade_level' => null]));

        $this->actingAs($teacher)
            ->put(route('teacher.lesson-plans.update', $lessonPlan), lessonPlanPayload($subject, ['grade_level' => 6]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Lesson plan updated. 2 aligned items no longer matched its subject or grade and were removed.');

        expect($lessonPlan->activities()->count())->toBe(0)
            ->and($lessonPlan->missions()->pluck('missions.id')->all())->toBe([$bothGrades->id]);
    });

    it('keeps the plan\'s inactive subject, old quarter and unpublished module', function () {
        $subject = Subject::factory()->inactive()->create();
        $teacher = User::factory()->teaching($subject)->create();
        SchoolYear::factory()->active()->create();
        $oldTerm = AcademicTerm::factory()->create();
        $module = LearningModule::factory()->for($subject)->unpublished()->create(['grade_level' => GradeLevel::Five]);
        $lessonPlan = LessonPlan::factory()->for($teacher, 'teacher')->for($subject)->for($oldTerm, 'academicTerm')->for($module, 'module')->create(['grade_level' => GradeLevel::Five]);

        $this->actingAs($teacher)
            ->put(route('teacher.lesson-plans.update', $lessonPlan), lessonPlanPayload($subject, [
                'academic_term_id' => $oldTerm->id,
                'learning_module_id' => $module->id,
            ]))
            ->assertSessionHasNoErrors();

        expect($lessonPlan->refresh())
            ->academic_term_id->toBe($oldTerm->id)
            ->learning_module_id->toBe($module->id);
    });

    it('validates the update', function () {
        $lessonPlan = LessonPlan::factory()->create(['title' => 'Kept']);

        $this->actingAs($lessonPlan->teacher)
            ->put(route('teacher.lesson-plans.update', $lessonPlan), lessonPlanPayload($lessonPlan->subject, ['title' => '']))
            ->assertSessionHasErrors(['title' => 'The title field is required.']);

        expect($lessonPlan->refresh()->title)->toBe('Kept');
    });
});

describe('destroy', function () {
    it('deletes the plan and its alignments', function () {
        $lessonPlan = LessonPlan::factory()->create(['title' => 'Smart Snacking']);
        $quiz = Quiz::factory()->create();
        $lessonPlan->quizzes()->attach($quiz);

        $this->actingAs($lessonPlan->teacher)
            ->delete(route('teacher.lesson-plans.destroy', $lessonPlan))
            ->assertRedirectToRoute('teacher.lesson-plans.index')
            ->assertSessionHas('status', 'Lesson plan “Smart Snacking” deleted.');

        $this->assertModelMissing($lessonPlan);
        $this->assertModelExists($quiz);
        $this->assertDatabaseMissing('lesson_plan_quiz', ['lesson_plan_id' => $lessonPlan->id]);
    });
});
