<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\PerformanceBand;
use App\Enums\UserRole;
use App\Models\ActivityProgress;
use App\Models\Classroom;
use App\Models\LearnerFeedback;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use App\Services\Monitoring\LearnerRosterPage;
use App\Services\Monitoring\LearnerRosterRow;
use App\Services\RolePermissions;
use Carbon\CarbonImmutable;

/**
 * Create a Health teacher with one active Grade 5 classroom.
 *
 * @return array{0: User, 1: Subject, 2: Classroom}
 */
function studentPagesSetup(): array
{
    $subject = Subject::factory()->create(['name' => 'Health', 'color' => 'berry', 'icon' => 'heart-pulse']);
    $teacher = User::factory()->teaching($subject)->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create([
        'grade_level' => GradeLevel::Five,
        'name' => 'Health 5 – Sampaguita',
    ]);

    return [$teacher, $subject, $classroom];
}

/**
 * Create a learner enrolled in the classroom with known game figures.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $profile
 */
function studentPagesLearner(Classroom $classroom, array $attributes = [], array $profile = []): User
{
    $student = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create($attributes);
    $student->studentProfile->forceFill($profile + [
        'total_points' => 0,
        'last_active_at' => now()->subDay(),
        'last_synced_at' => now()->subDay(),
    ])->save();

    return $student;
}

/**
 * Record a submitted attempt with an exact percentage (score out of 100).
 *
 * @param  array<string, mixed>  $attributes
 */
function studentPagesAttempt(Quiz $quiz, User $student, ?Classroom $classroom, float $percentage, array $attributes = []): QuizAttempt
{
    return QuizAttempt::factory()
        ->for($quiz)
        ->for($student, 'student')
        ->state(['classroom_id' => $classroom?->getKey()])
        ->create(array_merge([
            'score' => (int) round($percentage),
            'max_score' => 100,
            'percentage' => $percentage,
            'passed' => $percentage >= $quiz->passing_score,
        ], $attributes));
}

/**
 * Get the rows of the learners page keyed by learner name.
 *
 * @return array<string, LearnerRosterRow>
 */
function studentPagesRows(LearnerRosterPage $roster): array
{
    return collect($roster->rows->items())->keyBy(fn (LearnerRosterRow $row): string => $row->student->name)->all();
}

describe('index', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.students.index'))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('teacher.students.index'))
            ->assertForbidden();
    })->with([
        'administrator' => UserRole::Admin,
        'parent' => UserRole::Parent,
        'learner' => UserRole::Student,
    ]);

    it('lists only the learners of the teacher’s active classrooms in assigned subjects', function () {
        [$teacher, $subject, $classroom] = studentPagesSetup();
        studentPagesLearner($classroom, ['name' => 'Ana Cruz']);
        $archived = Classroom::factory()->for($teacher, 'teacher')->for($subject)->archived()->create();
        studentPagesLearner($archived, ['name' => 'Last Year Learner']);
        $revoked = Classroom::factory()->for($teacher, 'teacher')->revokedSubject()->create();
        studentPagesLearner($revoked, ['name' => 'Revoked Subject Learner']);
        studentPagesLearner(Classroom::factory()->create(), ['name' => 'Other Teacher Learner']);

        $response = $this->actingAs($teacher)->get(route('teacher.students.index'));

        $response->assertOk()
            ->assertViewIs('teacher.students.index')
            ->assertSeeText('Ana Cruz')
            ->assertDontSeeText('Last Year Learner')
            ->assertDontSeeText('Revoked Subject Learner')
            ->assertDontSeeText('Other Teacher Learner');
        expect($response->viewData('roster')->learnerCount)->toBe(1);
    });

    it('averages each learner’s best attempt per quiz made in the teacher’s classrooms', function () {
        [$teacher, $subject, $classroom] = studentPagesSetup();
        $student = studentPagesLearner($classroom, ['name' => 'Ana Cruz']);
        $plateQuiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create();
        $snackQuiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create();
        studentPagesAttempt($plateQuiz, $student, $classroom, 60);
        studentPagesAttempt($plateQuiz, $student, $classroom, 82.5, ['attempt_number' => 2]);
        studentPagesAttempt($snackQuiz, $student, $classroom, 70);
        studentPagesAttempt($snackQuiz, $student, $classroom, 99, ['submitted_at' => null]);
        studentPagesAttempt(Quiz::factory()->published()->create(), $student, Classroom::factory()->create(), 10);

        $response = $this->actingAs($teacher)->get(route('teacher.students.index'));

        $row = studentPagesRows($response->viewData('roster'))['Ana Cruz'];
        expect($row->averageScore)->toBe(76.3)
            ->and($row->band)->toBe(PerformanceBand::FairlySatisfactory)
            ->and($row->quizzesTaken)->toBe(2);
        $response->assertSee('76.3%');
    });

    it('counts completed missions in the subjects of the learner’s classrooms only', function () {
        [$teacher, $subject, $classroom] = studentPagesSetup();
        $student = studentPagesLearner($classroom, ['name' => 'Ana Cruz']);
        MissionProgress::factory()->completed()->for(Mission::factory()->for($subject)->create())->for($student, 'student')->create();
        MissionProgress::factory()->completed()->for(Mission::factory()->for($subject)->create())->for($student, 'student')->create();
        MissionProgress::factory()->inProgress()->for(Mission::factory()->for($subject)->create())->for($student, 'student')->create();
        MissionProgress::factory()->completed()->for(Mission::factory()->create())->for($student, 'student')->create();

        $response = $this->actingAs($teacher)->get(route('teacher.students.index'));

        expect(studentPagesRows($response->viewData('roster'))['Ana Cruz']->missionsCompleted)->toBe(2);
    });

    it('shows the level from the learner’s points', function () {
        [$teacher, , $classroom] = studentPagesSetup();
        studentPagesLearner($classroom, ['name' => 'Ana Cruz'], ['total_points' => 1240]);

        $response = $this->actingAs($teacher)->get(route('teacher.students.index'));

        $row = studentPagesRows($response->viewData('roster'))['Ana Cruz'];
        expect($row->level)->toBe(3)->and($row->points)->toBe(1240);
        $response->assertSee('aria-label="Level 3"', false)->assertSeeText('1,240 XP');
    });

    it('summarizes every learner in scope regardless of the band and search filters', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
        [$teacher, $subject, $classroom] = studentPagesSetup();
        $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create();
        studentPagesAttempt($quiz, studentPagesLearner($classroom, ['name' => 'Ana Cruz']), $classroom, 95);
        studentPagesAttempt($quiz, studentPagesLearner($classroom, ['name' => 'Ben Reyes'], ['last_synced_at' => now()->subDays(8)]), $classroom, 70);
        studentPagesLearner($classroom, ['name' => 'Carlo Diaz'], ['last_active_at' => null, 'last_synced_at' => null]);

        $roster = $this->actingAs($teacher)
            ->get(route('teacher.students.index', ['band' => 'outstanding', 'search' => 'Ana']))
            ->viewData('roster');

        expect($roster->learnerCount)->toBe(3)
            ->and($roster->averageScore)->toBe(82.5)
            ->and($roster->needsAttention)->toBe(1)
            ->and($roster->syncNeedsAttention)->toBe(2)
            ->and($roster->bandCounts)->toMatchArray([
                'outstanding' => 1,
                'did_not_meet_expectations' => 1,
                'no_data' => 1,
                'very_satisfactory' => 0,
            ])
            ->and($roster->rows->total())->toBe(1);
    });

    it('filters by performance band', function (string $band, array $expected) {
        [$teacher, $subject, $classroom] = studentPagesSetup();
        $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create();
        studentPagesAttempt($quiz, studentPagesLearner($classroom, ['name' => 'Ana Cruz']), $classroom, 92);
        studentPagesAttempt($quiz, studentPagesLearner($classroom, ['name' => 'Ben Reyes']), $classroom, 74.9);
        studentPagesLearner($classroom, ['name' => 'Carlo Diaz']);

        $response = $this->actingAs($teacher)->get(route('teacher.students.index', ['band' => $band]));

        expect(array_keys(studentPagesRows($response->viewData('roster'))))->toBe($expected);
    })->with([
        'outstanding' => ['outstanding', ['Ana Cruz']],
        'did not meet expectations' => ['did_not_meet_expectations', ['Ben Reyes']],
        'no data' => ['no_data', ['Carlo Diaz']],
        'a band nobody is in' => ['satisfactory', []],
        'an unknown band is ignored' => ['excellent', ['Ana Cruz', 'Ben Reyes', 'Carlo Diaz']],
    ]);

    it('limits learners and scores to the chosen classroom', function () {
        [$teacher, $subject, $classroom] = studentPagesSetup();
        $other = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'name' => 'Health 5 – Ilang-Ilang']);
        $both = studentPagesLearner($classroom, ['name' => 'Ana Cruz']);
        $other->students()->attach($both);
        studentPagesLearner($other, ['name' => 'Ben Reyes']);
        $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create();
        studentPagesAttempt($quiz, $both, $classroom, 80);
        studentPagesAttempt(Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(), $both, $other, 40);

        $response = $this->actingAs($teacher)->get(route('teacher.students.index', ['classroom' => $classroom->id]));

        $rows = studentPagesRows($response->viewData('roster'));
        expect(array_keys($rows))->toBe(['Ana Cruz'])
            ->and($rows['Ana Cruz']->averageScore)->toBe(80.0)
            ->and($rows['Ana Cruz']->classrooms->pluck('name')->all())->toBe(['Health 5 – Sampaguita']);
    });

    it('reports a classroom outside the teacher’s active classrooms as missing', function (Closure $classroom) {
        [$teacher, $subject] = studentPagesSetup();

        $this->actingAs($teacher)
            ->get(route('teacher.students.index', ['classroom' => $classroom($teacher, $subject)]))
            ->assertNotFound();
    })->with([
        'another teacher’s classroom' => fn (User $teacher, Subject $subject): int => Classroom::factory()->for($subject)->create()->id,
        'an archived classroom' => fn (User $teacher, Subject $subject): int => Classroom::factory()->for($teacher, 'teacher')->for($subject)->archived()->create()->id,
        'a classroom of a revoked subject' => fn (User $teacher, Subject $subject): int => Classroom::factory()->for($teacher, 'teacher')->revokedSubject()->create()->id,
        'not an id' => fn (User $teacher, Subject $subject): string => 'abc',
    ]);

    it('searches by name, username and LRN', function (string $search) {
        [$teacher, , $classroom] = studentPagesSetup();
        $ana = studentPagesLearner($classroom, ['name' => 'Ana Cruz', 'username' => 'ana_cruz']);
        $ana->studentProfile->forceFill(['lrn' => '136742210001'])->save();
        $ben = studentPagesLearner($classroom, ['name' => 'Ben Reyes', 'username' => 'ben_reyes']);
        $ben->studentProfile->forceFill(['lrn' => '136742219999'])->save();

        $response = $this->actingAs($teacher)->get(route('teacher.students.index', ['search' => $search]));

        expect(array_keys(studentPagesRows($response->viewData('roster'))))->toBe(['Ana Cruz']);
    })->with([
        'name' => 'Cruz',
        'username' => 'ana_c',
        'LRN' => '210001',
    ]);

    it('sorts by points, highest first', function () {
        [$teacher, , $classroom] = studentPagesSetup();
        studentPagesLearner($classroom, ['name' => 'Ana Cruz'], ['total_points' => 300]);
        studentPagesLearner($classroom, ['name' => 'Ben Reyes'], ['total_points' => 900]);
        studentPagesLearner($classroom, ['name' => 'Carlo Diaz'], ['total_points' => 600]);

        $response = $this->actingAs($teacher)->get(route('teacher.students.index', ['sort' => 'points', 'direction' => 'desc']));

        expect(array_keys(studentPagesRows($response->viewData('roster'))))->toBe(['Ben Reyes', 'Carlo Diaz', 'Ana Cruz']);
    });

    it('sorts by name by default and ignores unknown sort keys', function () {
        [$teacher, , $classroom] = studentPagesSetup();
        studentPagesLearner($classroom, ['name' => 'Carlo Diaz']);
        studentPagesLearner($classroom, ['name' => 'Ana Cruz']);

        $response = $this->actingAs($teacher)->get(route('teacher.students.index', ['sort' => 'password']));

        expect(array_keys(studentPagesRows($response->viewData('roster'))))->toBe(['Ana Cruz', 'Carlo Diaz']);
    });

    it('warns about learners who never synced or synced more than 7 days ago', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
        [$teacher, , $classroom] = studentPagesSetup();
        studentPagesLearner($classroom, ['name' => 'Ana Cruz'], ['last_synced_at' => now()->subDays(7)]);
        studentPagesLearner($classroom, ['name' => 'Ben Reyes'], ['last_synced_at' => now()->subDays(7)->subMinute()]);
        studentPagesLearner($classroom, ['name' => 'Carlo Diaz'], ['last_active_at' => null, 'last_synced_at' => null]);

        $response = $this->actingAs($teacher)->get(route('teacher.students.index'));

        $rows = studentPagesRows($response->viewData('roster'));
        expect($rows['Ana Cruz']->syncNeedsAttention)->toBeFalse()
            ->and($rows['Ben Reyes']->syncNeedsAttention)->toBeTrue()
            ->and($rows['Carlo Diaz']->syncNeedsAttention)->toBeTrue();
        $response->assertSee('data-sync-status="ok"', false)
            ->assertSee('data-sync-status="stale"', false)
            ->assertSee('data-sync-status="never"', false)
            ->assertSeeText('Never synced')
            ->assertSeeText('Not played yet');
    });

    it('paginates 20 learners per page and keeps the filters', function () {
        [$teacher, , $classroom] = studentPagesSetup();
        foreach (range(1, 21) as $number) {
            studentPagesLearner($classroom, ['name' => sprintf('Learner %02d', $number)]);
        }

        $response = $this->actingAs($teacher)->get(route('teacher.students.index', ['page' => 2, 'search' => 'Learner']));

        expect(array_keys(studentPagesRows($response->viewData('roster'))))->toBe(['Learner 21']);
        $response->assertSee('search=Learner', false);
    });

    it('explains an empty list without classrooms', function () {
        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.students.index'))
            ->assertOk()
            ->assertSeeText('No active classrooms yet');
    });

    it('explains an empty list without learners', function () {
        [$teacher] = studentPagesSetup();

        $this->actingAs($teacher)
            ->get(route('teacher.students.index'))
            ->assertOk()
            ->assertSeeText('No learners yet');
    });

    it('explains an empty filtered list', function () {
        [$teacher, , $classroom] = studentPagesSetup();
        studentPagesLearner($classroom, ['name' => 'Ana Cruz']);

        $this->actingAs($teacher)
            ->get(route('teacher.students.index', ['search' => 'Nobody']))
            ->assertOk()
            ->assertSeeText('No learners match your filters')
            ->assertSeeText('Clear filters');
    });

    it('hides the feedback actions without the give feedback permission', function () {
        [$teacher, , $classroom] = studentPagesSetup();
        studentPagesLearner($classroom, ['name' => 'Ana Cruz']);
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::GiveFeedback);

        $this->actingAs($teacher)
            ->get(route('teacher.students.index'))
            ->assertOk()
            ->assertDontSee(route('teacher.feedback.create'));
    });

    it('renders many learners with classrooms, attempts and missions', function () {
        [$teacher, $subject, $classroom] = studentPagesSetup();
        $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create();
        $mission = Mission::factory()->for($subject)->create();
        foreach (range(1, 6) as $number) {
            $student = studentPagesLearner($classroom, ['name' => 'Learner '.$number]);
            studentPagesAttempt($quiz, $student, $classroom, 50 + $number * 7);
            MissionProgress::factory()->completed()->for($mission)->for($student, 'student')->create();
        }

        $this->actingAs($teacher)
            ->get(route('teacher.students.index'))
            ->assertOk()
            ->assertSeeText('Health 5 – Sampaguita')
            ->assertSee(route('teacher.feedback.create', ['student' => User::query()->where('name', 'Learner 1')->value('id')]), false);
    });
});

describe('show', function () {
    it('sends guests to the sign-in page', function () {
        [, , $classroom] = studentPagesSetup();

        $this->get(route('teacher.students.show', studentPagesLearner($classroom)))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        [, , $classroom] = studentPagesSetup();

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('teacher.students.show', studentPagesLearner($classroom)))
            ->assertForbidden();
    })->with([
        'administrator' => UserRole::Admin,
        'parent' => UserRole::Parent,
    ]);

    it('reports learners outside the teacher’s classrooms as missing', function () {
        [$teacher] = studentPagesSetup();

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', studentPagesLearner(Classroom::factory()->create())))
            ->assertNotFound();
    });

    it('reports a learner whose classroom subject was revoked as missing', function () {
        [$teacher] = studentPagesSetup();
        $revoked = Classroom::factory()->for($teacher, 'teacher')->revokedSubject()->create();

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', studentPagesLearner($revoked)))
            ->assertNotFound();
    });

    it('reports accounts that are not learners as missing', function () {
        [$teacher] = studentPagesSetup();

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', User::factory()->parent()->create()))
            ->assertNotFound();
    });

    it('still shows a learner of the teacher’s archived classroom', function () {
        [$teacher, $subject] = studentPagesSetup();
        $archived = Classroom::factory()->for($teacher, 'teacher')->for($subject)->archived()->create();

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', studentPagesLearner($archived, ['name' => 'Last Year Learner'])))
            ->assertOk()
            ->assertSeeText('Last Year Learner');
    });

    it('shows the learner’s attempts in the teacher’s subjects and links the teacher’s own quizzes', function () {
        [$teacher, $subject, $classroom] = studentPagesSetup();
        $student = studentPagesLearner($classroom, ['name' => 'Ana Cruz']);
        $ownQuiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['title' => 'Healthy Plate Quiz']);
        $colleagueQuiz = Quiz::factory()->published()->for($subject)->create(['title' => 'Colleague Health Quiz']);
        $otherSubjectQuiz = Quiz::factory()->published()->create(['title' => 'Fractions Quiz']);
        $ownAttempt = studentPagesAttempt($ownQuiz, $student, $classroom, 85);
        $colleagueAttempt = studentPagesAttempt($colleagueQuiz, $student, null, 60);
        studentPagesAttempt($otherSubjectQuiz, $student, null, 90);

        $response = $this->actingAs($teacher)->get(route('teacher.students.show', $student));

        $response->assertOk()
            ->assertViewIs('teacher.students.show')
            ->assertSeeText('Healthy Plate Quiz')
            ->assertSeeText('Colleague Health Quiz')
            ->assertDontSeeText('Fractions Quiz')
            ->assertSee(route('teacher.attempts.show', $ownAttempt), false)
            ->assertDontSee(route('teacher.attempts.show', $colleagueAttempt), false);
        expect($response->viewData('summary')->quizzesTaken)->toBe(2)
            ->and($response->viewData('summary')->averageScore)->toBe(72.5)
            ->and($response->viewData('attempts')->total())->toBe(2);
    });

    it('paginates the attempts 10 per page', function () {
        [$teacher, $subject, $classroom] = studentPagesSetup();
        $student = studentPagesLearner($classroom);
        $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['max_attempts' => null]);
        foreach (range(1, 11) as $number) {
            studentPagesAttempt($quiz, $student, $classroom, 50, ['attempt_number' => $number, 'submitted_at' => now()->subDays(12 - $number)]);
        }

        $response = $this->actingAs($teacher)->get(route('teacher.students.show', [$student, 'attempts' => 2]));

        expect($response->viewData('attempts')->items())->toHaveCount(1)
            ->and($response->viewData('attempts')->first()->attempt_number)->toBe(1);
    });

    it('lists mission progress and module progress in the teacher’s subjects only', function () {
        [$teacher, $subject, $classroom] = studentPagesSetup();
        $student = studentPagesLearner($classroom);
        MissionProgress::factory()->completed()->for(Mission::factory()->for($subject)->create(['title' => 'Rainbow Plate']))->for($student, 'student')->create();
        MissionProgress::factory()->completed()->for(Mission::factory()->create(['title' => 'Market Explorer']))->for($student, 'student')->create();
        $module = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'title' => 'Go, Grow, Glow World']);
        $activities = LearningActivity::factory()->count(3)->for($module, 'module')->create();
        LearningActivity::factory()->unpublished()->for($module, 'module')->create();
        ActivityProgress::factory()->completed()->for($activities[0], 'activity')->for($student, 'student')->create();
        ActivityProgress::factory()->completed()->for($activities[1], 'activity')->for($student, 'student')->create();
        LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Six, 'title' => 'Grade Six World']);
        LearningModule::factory()->for($subject)->unpublished()->create(['grade_level' => GradeLevel::Five, 'title' => 'Draft World']);

        $response = $this->actingAs($teacher)->get(route('teacher.students.show', $student));

        $response->assertOk()
            ->assertSeeText('Rainbow Plate')
            ->assertDontSeeText('Market Explorer')
            ->assertSeeText('Go, Grow, Glow World')
            ->assertSeeText('2 of 3')
            ->assertDontSeeText('Grade Six World')
            ->assertDontSeeText('Draft World');
    });

    it('shows the teacher’s own feedback about the learner', function () {
        [$teacher, , $classroom] = studentPagesSetup();
        $student = studentPagesLearner($classroom);
        LearnerFeedback::factory()->for($teacher, 'teacher')->for($student, 'student')->create(['message' => 'Great job on the plate quiz!']);
        LearnerFeedback::factory()->for($student, 'student')->create(['message' => 'A colleague wrote this.']);

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', $student))
            ->assertOk()
            ->assertSeeText('Great job on the plate quiz!')
            ->assertDontSeeText('A colleague wrote this.')
            ->assertSee(route('teacher.feedback.create', ['student' => $student->id]), false);
    });

    it('hides the feedback section without the give feedback permission', function () {
        [$teacher, , $classroom] = studentPagesSetup();
        $student = studentPagesLearner($classroom);
        LearnerFeedback::factory()->for($teacher, 'teacher')->for($student, 'student')->create(['message' => 'Great job on the plate quiz!']);
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::GiveFeedback);

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', $student))
            ->assertOk()
            ->assertDontSeeText('Great job on the plate quiz!')
            ->assertDontSee(route('teacher.feedback.create', ['student' => $student->id]), false);
    });

    it('renders a learner without any progress', function () {
        [$teacher, , $classroom] = studentPagesSetup();

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', studentPagesLearner($classroom, ['name' => 'Carlo Diaz'], ['last_active_at' => null, 'last_synced_at' => null])))
            ->assertOk()
            ->assertSeeText('Carlo Diaz')
            ->assertSeeText('No quiz attempts yet')
            ->assertSeeText('No modules yet')
            ->assertSeeText('No feedback yet');
    });
});
