<?php

use App\Enums\GradeLevel;
use App\Enums\LearningMode;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Teacher\ClassroomController;
use App\Http\Requests\Teacher\StoreClassroomRequest;
use App\Models\Classroom;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use App\Policies\ClassroomPolicy;
use App\Services\RolePermissions;
use App\Services\Settings;
use Illuminate\Pagination\LengthAwarePaginator;

describe('index', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.classrooms.index'))->assertRedirectToRoute('login');
    });

    it('forbids accounts of the other portals', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('teacher.classrooms.index'))
            ->assertForbidden();
    })->with([
        'administrator' => UserRole::Admin,
        'parent' => UserRole::Parent,
        'learner' => UserRole::Student,
    ]);

    it('lists the teacher’s active classrooms with learners, published quizzes and the average score', function () {
        $subject = Subject::factory()->create(['name' => 'Health']);
        $teacher = User::factory()->teaching($subject)->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['name' => 'Health 5 – Sampaguita']);
        [$ana, $ben] = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->count(2)->create();
        $published = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create();
        $draft = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create();
        $classroom->quizzes()->attach([$published->id, $draft->id]);
        QuizAttempt::factory()->for($published)->for($ana, 'student')->for($classroom)->create(['score' => 8, 'max_score' => 10, 'percentage' => 80, 'passed' => true]);
        QuizAttempt::factory()->for($published)->for($ben, 'student')->for($classroom)->create(['score' => 6, 'max_score' => 10, 'percentage' => 60, 'passed' => false]);
        QuizAttempt::factory()->inProgress()->for($published)->for($ben, 'student')->for($classroom)->create();

        $response = $this->actingAs($teacher)->get(route('teacher.classrooms.index'));

        $response->assertOk()
            ->assertViewIs('teacher.classrooms.index')
            ->assertViewHas('classrooms', function (LengthAwarePaginator $classrooms) use ($classroom): bool {
                $card = $classrooms->sole();

                return $card->is($classroom)
                    && (int) $card->students_count === 2
                    && (int) $card->published_quizzes_count === 1
                    && round((float) $card->average_score, 1) === 70.0;
            })
            ->assertSee('data-classroom-card="'.$classroom->id.'"', false)
            ->assertSeeText('Health 5 – Sampaguita')
            ->assertSeeText($classroom->class_code)
            ->assertSee('data-classroom-stat="learners">2<', false)
            ->assertSee('data-classroom-stat="published-quizzes">1<', false)
            ->assertSeeText('70%');
    });

    it('hides other teachers’ classrooms and reports the classrooms of revoked subjects as locked', function () {
        $classroom = Classroom::factory()->create();
        $teacher = $classroom->teacher;
        $locked = Classroom::factory()->for($teacher, 'teacher')->revokedSubject()->create();
        $othersClassroom = Classroom::factory()->create();

        $response = $this->actingAs($teacher)->get(route('teacher.classrooms.index'));

        $response->assertOk()
            ->assertViewHas('classrooms', fn (LengthAwarePaginator $classrooms): bool => $classrooms->pluck('id')->all() === [$classroom->id])
            ->assertViewHas('lockedCount', 1)
            ->assertSeeText('1 classroom is locked because the administrator removed your access to its subject.')
            ->assertDontSee('data-classroom-card="'.$locked->id.'"', false)
            ->assertDontSee('data-classroom-card="'.$othersClassroom->id.'"', false);
    });

    it('lists archived classrooms on the archived tab only', function () {
        $active = Classroom::factory()->create();
        $archived = Classroom::factory()->archived()->for($active->teacher, 'teacher')->for($active->subject)->create();

        $this->actingAs($active->teacher)
            ->get(route('teacher.classrooms.index', ['status' => 'archived']))
            ->assertOk()
            ->assertViewHas('status', 'archived')
            ->assertViewHas('counts', ['active' => 1, 'archived' => 1])
            ->assertViewHas('classrooms', fn (LengthAwarePaginator $classrooms): bool => $classrooms->pluck('id')->all() === [$archived->id]);

        $this->get(route('teacher.classrooms.index'))
            ->assertViewHas('status', 'active')
            ->assertViewHas('classrooms', fn (LengthAwarePaginator $classrooms): bool => $classrooms->pluck('id')->all() === [$active->id]);
    });

    it('filters by one of the teacher’s subjects and ignores subjects that are not theirs', function () {
        $health = Subject::factory()->create();
        $science = Subject::factory()->create();
        $teacher = User::factory()->teaching($health, $science)->create();
        $healthClass = Classroom::factory()->for($teacher, 'teacher')->for($health)->create();
        $scienceClass = Classroom::factory()->for($teacher, 'teacher')->for($science)->create();
        $foreign = Classroom::factory()->create();

        $this->actingAs($teacher)
            ->get(route('teacher.classrooms.index', ['subject' => $science->id]))
            ->assertViewHas('classrooms', fn (LengthAwarePaginator $classrooms): bool => $classrooms->pluck('id')->all() === [$scienceClass->id]);

        $this->get(route('teacher.classrooms.index', ['subject' => $foreign->subject_id]))
            ->assertViewHas('classrooms', fn (LengthAwarePaginator $classrooms): bool => $classrooms->pluck('id')->sort()->values()->all() === [$healthClass->id, $scienceClass->id]);
    });

    it('searches by name, section, room and class code', function (string $field) {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $match = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['name' => 'Health 5 – Sampaguita', 'section' => 'Sampaguita', 'room' => 'Room 104']);
        Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['name' => 'Health 6 – Narra', 'section' => 'Narra', 'room' => 'Room 210']);
        $terms = ['name' => 'Health 5', 'section' => 'sampag', 'room' => 'Room 104', 'class code' => $match->class_code];

        $this->actingAs($teacher)
            ->get(route('teacher.classrooms.index', ['search' => $terms[$field]]))
            ->assertViewHas('isFiltered', true)
            ->assertViewHas('classrooms', fn (LengthAwarePaginator $classrooms): bool => $classrooms->pluck('id')->all() === [$match->id]);
    })->with(['name', 'section', 'room', 'class code']);

    it('pages the classrooms twelve at a time in grade and name order', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $schoolYear = SchoolYear::factory()->active()->create();
        foreach (range(1, ClassroomController::CLASSROOMS_PER_PAGE + 1) as $number) {
            Classroom::factory()->for($teacher, 'teacher')->for($subject)->for($schoolYear)->create([
                'grade_level' => GradeLevel::Five,
                'name' => sprintf('Class %02d', $number),
            ]);
        }

        $this->actingAs($teacher)
            ->get(route('teacher.classrooms.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('classrooms', fn (LengthAwarePaginator $classrooms): bool => $classrooms->total() === 13 && $classrooms->pluck('name')->all() === ['Class 13']);
    });

    it('shows a call to action when the teacher has no classrooms yet', function () {
        $teacher = User::factory()->teaching()->create();

        $this->actingAs($teacher)
            ->get(route('teacher.classrooms.index'))
            ->assertOk()
            ->assertSeeText('No classrooms yet')
            ->assertSee('href="'.route('teacher.classrooms.create').'"', false);
    });

    it('explains that subjects are needed before a teacher without subjects can create classrooms', function () {
        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('teacher.classrooms.index'))
            ->assertOk()
            ->assertSeeText('You can create classrooms once your school administrator assigns you an active subject')
            ->assertDontSee('href="'.route('teacher.classrooms.create').'"', false);
    });

    it('shows dedicated empty states for filters without results and an empty archive', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.index', ['search' => 'no such class']))
            ->assertSeeText('No classrooms match your filters');

        $this->get(route('teacher.classrooms.index', ['status' => 'archived']))
            ->assertSeeText('No archived classrooms');
    });
});

describe('create', function () {
    it('shows the form with the teacher’s active subjects and the default learning mode from the settings', function () {
        $health = Subject::factory()->create(['name' => 'Health']);
        $paused = Subject::factory()->inactive()->create(['name' => 'Mathematics']);
        $teacher = User::factory()->teaching($health, $paused)->create();
        SchoolYear::factory()->active()->create(['name' => '2026–2027']);
        app(Settings::class)->set('default_learning_mode', LearningMode::Manual->value);

        $response = $this->actingAs($teacher)->get(route('teacher.classrooms.create'));

        $response->assertOk()
            ->assertViewIs('teacher.classrooms.create')
            ->assertViewHas('subjectOptions', [$health->id => 'Health'])
            ->assertViewHas('subjectShortNames', [$health->id => 'Health'])
            ->assertSeeText('School year 2026–2027')
            ->assertSee('data-classrooms-subject-names="{&quot;'.$health->id.'&quot;:&quot;Health&quot;}"', false)
            ->assertSee('action="'.route('teacher.classrooms.store').'"', false);
        expect($response->getContent())->toMatch('#<input[^>]*value="manual"[^>]*\schecked(?=[\s/>])#');
    });

    it('uses the subject code in name suggestions for long subject names', function () {
        $subject = Subject::factory()->create(['name' => 'EPP – Home Economics', 'code' => 'EPP-HE']);
        SchoolYear::factory()->active()->create();

        $this->actingAs(User::factory()->teaching($subject)->create())
            ->get(route('teacher.classrooms.create'))
            ->assertViewHas('subjectShortNames', [$subject->id => 'EPP-HE']);
    });

    it('preselects a subject from the query string only when it is one of the teacher’s active subjects', function () {
        $health = Subject::factory()->create();
        $science = Subject::factory()->create();
        $teacher = User::factory()->teaching($health, $science)->create();
        SchoolYear::factory()->active()->create();

        $this->actingAs($teacher)
            ->get(route('teacher.classrooms.create', ['subject_id' => $science->id]))
            ->assertViewHas('classroom', fn (Classroom $classroom): bool => $classroom->subject_id === $science->id);

        $this->get(route('teacher.classrooms.create', ['subject_id' => Subject::factory()->create()->id]))
            ->assertViewHas('classroom', fn (Classroom $classroom): bool => $classroom->subject_id === null);
    });

    it('shows an empty state instead of the form while no school year is active', function () {
        SchoolYear::factory()->create();

        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.classrooms.create'))
            ->assertOk()
            ->assertSeeText('No active school year yet')
            ->assertDontSee('action="'.route('teacher.classrooms.store').'"', false);
    });

    it('forbids teachers without an assigned subject', function () {
        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('teacher.classrooms.create'))
            ->assertForbidden()
            ->assertSeeText(ClassroomPolicy::NO_SUBJECT_MESSAGE);
    });

    it('forbids teachers without the manage classrooms permission', function () {
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageClassrooms);

        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.classrooms.create'))
            ->assertForbidden()
            ->assertSeeText(RolePermissions::deniedMessage(Permission::ManageClassrooms));
    });
});

describe('store', function () {
    it('creates the classroom for the teacher in the active school year', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $schoolYear = SchoolYear::factory()->active()->create();
        SchoolYear::factory()->create();

        $response = $this->actingAs($teacher)->post(route('teacher.classrooms.store'), [
            'subject_id' => $subject->id,
            'grade_level' => '5',
            'section' => 'Sampaguita',
            'name' => 'Health 5 – Sampaguita',
            'room' => 'Room 104',
            'description' => 'Mondays and Wednesdays, 9:00 AM.',
            'learning_mode' => 'learner_choice',
        ]);

        $classroom = Classroom::query()->sole();
        $response->assertRedirectToRoute('teacher.classrooms.show', $classroom)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Health 5 – Sampaguita is ready. Share the class code '.$classroom->class_code.' with your learners.');
        expect($classroom)
            ->teacher_id->toBe($teacher->id)
            ->school_year_id->toBe($schoolYear->id)
            ->subject_id->toBe($subject->id)
            ->grade_level->toBe(GradeLevel::Five)
            ->section->toBe('Sampaguita')
            ->room->toBe('Room 104')
            ->description->toBe('Mondays and Wednesdays, 9:00 AM.')
            ->learning_mode->toBe(LearningMode::LearnerChoice)
            ->archived_at->toBeNull()
            ->class_code->toMatch('/^['.Classroom::CLASS_CODE_ALPHABET.']{6}$/');
    });

    it('never takes the owner, school year, class code or archive date from the payload', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $schoolYear = SchoolYear::factory()->active()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $pastYear = SchoolYear::factory()->create();

        $this->actingAs($teacher)->post(route('teacher.classrooms.store'), [
            'subject_id' => $subject->id,
            'grade_level' => '6',
            'section' => 'Narra',
            'name' => 'Health 6 – Narra',
            'learning_mode' => 'automatic',
            'teacher_id' => $otherTeacher->id,
            'school_year_id' => $pastYear->id,
            'class_code' => 'AAAAAA',
            'archived_at' => now()->toDateTimeString(),
        ])->assertSessionHasNoErrors();

        expect(Classroom::query()->sole())
            ->teacher_id->toBe($teacher->id)
            ->school_year_id->toBe($schoolYear->id)
            ->class_code->not->toBe('AAAAAA')
            ->archived_at->toBeNull();
    });

    it('requires the subject, grade, section, name and learning mode', function () {
        SchoolYear::factory()->active()->create();

        $this->actingAs(User::factory()->teaching()->create())
            ->post(route('teacher.classrooms.store'), [])
            ->assertSessionHasErrors([
                'subject_id' => 'The subject field is required.',
                'grade_level' => 'The grade level field is required.',
                'section' => 'The section field is required.',
                'name' => 'The name field is required.',
                'learning_mode' => 'The learning mode field is required.',
            ]);

        expect(Classroom::query()->count())->toBe(0);
    });

    it('rejects invalid classroom details', function (array $payload, string $field, string $message) {
        $subject = Subject::factory()->create();
        SchoolYear::factory()->active()->create();
        $valid = ['subject_id' => $subject->id, 'grade_level' => '5', 'section' => 'Sampaguita', 'name' => 'Health 5 – Sampaguita', 'learning_mode' => 'automatic'];

        $this->actingAs(User::factory()->teaching($subject)->create())
            ->post(route('teacher.classrooms.store'), [...$valid, ...$payload])
            ->assertSessionHasErrors([$field => $message]);

        expect(Classroom::query()->count())->toBe(0);
    })->with([
        'long section' => [['section' => str_repeat('a', 51)], 'section', 'The section field must not be greater than 50 characters.'],
        'long name' => [['name' => str_repeat('a', 101)], 'name', 'The name field must not be greater than 100 characters.'],
        'long room' => [['room' => str_repeat('a', 51)], 'room', 'The room field must not be greater than 50 characters.'],
        'long description' => [['description' => str_repeat('a', 501)], 'description', 'The description field must not be greater than 500 characters.'],
        'unknown grade' => [['grade_level' => '4'], 'grade_level', 'The selected grade level is invalid.'],
        'unknown learning mode' => [['learning_mode' => 'random'], 'learning_mode', 'The selected learning mode is invalid.'],
    ]);

    it('rejects subjects that are not assigned to the teacher or are inactive', function (Closure $makeSubject) {
        SchoolYear::factory()->active()->create();
        $teacher = User::factory()->teaching()->create();
        $subject = $makeSubject($teacher);

        $this->actingAs($teacher)
            ->post(route('teacher.classrooms.store'), ['subject_id' => $subject->id, 'grade_level' => '5', 'section' => 'Sampaguita', 'name' => 'Class', 'learning_mode' => 'automatic'])
            ->assertSessionHasErrors(['subject_id' => 'Choose one of the active subjects assigned to you.']);

        expect(Classroom::query()->count())->toBe(0);
    })->with([
        'not assigned' => fn (User $teacher): Subject => Subject::factory()->create(),
        'assigned but inactive' => function (User $teacher): Subject {
            $subject = Subject::factory()->inactive()->create();
            $teacher->assignedSubjects()->attach($subject);

            return $subject;
        },
    ]);

    it('rejects a section that already has a classroom of the subject and grade this school year', function () {
        $subject = Subject::factory()->create(['name' => 'Health']);
        $schoolYear = SchoolYear::factory()->active()->create();
        Classroom::factory()->for($subject)->for($schoolYear)->create(['grade_level' => GradeLevel::Five, 'section' => 'Sampaguita']);

        $this->actingAs(User::factory()->teaching($subject)->create())
            ->post(route('teacher.classrooms.store'), ['subject_id' => $subject->id, 'grade_level' => '5', 'section' => '  sampaguita ', 'name' => 'Health 5 – Sampaguita', 'learning_mode' => 'automatic'])
            ->assertSessionHasErrors(['section' => 'A Health Grade 5 – Sampaguita classroom already exists this school year.']);

        expect(Classroom::query()->count())->toBe(1);
    });

    it('allows the same section in another school year, grade or subject', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        SchoolYear::factory()->active()->create();
        Classroom::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'section' => 'Sampaguita']);
        Classroom::factory()->for($subject)->for(SchoolYear::current())->create(['grade_level' => GradeLevel::Six, 'section' => 'Sampaguita']);
        Classroom::factory()->for(SchoolYear::current())->create(['grade_level' => GradeLevel::Five, 'section' => 'Sampaguita']);

        $this->actingAs($teacher)
            ->post(route('teacher.classrooms.store'), ['subject_id' => $subject->id, 'grade_level' => '5', 'section' => 'Sampaguita', 'name' => 'Health 5 – Sampaguita', 'learning_mode' => 'automatic'])
            ->assertSessionHasNoErrors();

        expect($teacher->classrooms()->count())->toBe(1);
    });

    it('refuses to create a classroom while no school year is active', function () {
        $subject = Subject::factory()->create();
        SchoolYear::factory()->create();

        $this->actingAs(User::factory()->teaching($subject)->create())
            ->post(route('teacher.classrooms.store'), ['subject_id' => $subject->id, 'grade_level' => '5', 'section' => 'Sampaguita', 'name' => 'Health 5 – Sampaguita', 'learning_mode' => 'automatic'])
            ->assertSessionHasErrors(['school_year_id' => StoreClassroomRequest::NO_ACTIVE_SCHOOL_YEAR_MESSAGE]);

        expect(Classroom::query()->count())->toBe(0);
    });

    it('forbids teachers without the manage classrooms permission', function () {
        $subject = Subject::factory()->create();
        SchoolYear::factory()->active()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageClassrooms);

        $this->actingAs(User::factory()->teaching($subject)->create())
            ->post(route('teacher.classrooms.store'), ['subject_id' => $subject->id, 'grade_level' => '5', 'section' => 'Sampaguita', 'name' => 'Health 5 – Sampaguita', 'learning_mode' => 'automatic'])
            ->assertForbidden();

        expect(Classroom::query()->count())->toBe(0);
    });

    it('sends guests to the sign-in page', function () {
        $this->post(route('teacher.classrooms.store'), [])->assertRedirectToRoute('login');
    });
});

describe('show', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.classrooms.show', Classroom::factory()->create()))->assertRedirectToRoute('login');
    });

    it('forbids accounts of the other portals', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('teacher.classrooms.show', Classroom::factory()->create()))
            ->assertForbidden();
    })->with([
        'administrator' => UserRole::Admin,
        'parent' => UserRole::Parent,
    ]);

    it('reports another teacher’s classroom as missing', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs(User::factory()->teaching($classroom->subject)->create())
            ->get(route('teacher.classrooms.show', $classroom))
            ->assertNotFound();
    });

    it('refuses the owner once the administrator revoked the subject', function () {
        $classroom = Classroom::factory()->revokedSubject()->create();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.show', $classroom))
            ->assertForbidden()
            ->assertSeeText(ClassroomPolicy::SUBJECT_REVOKED_MESSAGE);
    });

    it('shows the roster with each learner’s level, average and completed quizzes in this classroom', function () {
        $classroom = Classroom::factory()->create(['class_code' => 'HLT5SA']);
        $teacher = $classroom->teacher;
        $otherClassroom = Classroom::factory()->for($teacher, 'teacher')->for($classroom->subject)->create();
        $ana = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create(['name' => 'Ana Reyes', 'username' => 'ana_reyes']);
        $ben = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create(['name' => 'Ben Cruz']);
        $ana->studentProfile->forceFill(['total_points' => 1240, 'last_active_at' => now()->subDays(2)])->save();
        [$firstQuiz, $secondQuiz] = Quiz::factory()->published()->for($teacher, 'teacher')->for($classroom->subject)->count(2)->create();
        QuizAttempt::factory()->for($firstQuiz)->for($ana, 'student')->for($classroom)->create(['score' => 8, 'max_score' => 10, 'percentage' => 80, 'passed' => true]);
        QuizAttempt::factory()->for($firstQuiz)->for($ana, 'student')->for($classroom)->create(['attempt_number' => 2, 'score' => 9, 'max_score' => 10, 'percentage' => 90, 'passed' => true]);
        QuizAttempt::factory()->for($secondQuiz)->for($ana, 'student')->for($classroom)->create(['score' => 7, 'max_score' => 10, 'percentage' => 70, 'passed' => false]);
        QuizAttempt::factory()->for($secondQuiz)->for($ana, 'student')->for($otherClassroom)->create(['score' => 1, 'max_score' => 10, 'percentage' => 10, 'passed' => false]);

        $response = $this->actingAs($teacher)->get(route('teacher.classrooms.show', $classroom));

        $response->assertOk()
            ->assertViewIs('teacher.classrooms.show')
            ->assertViewHas('tab', 'learners')
            ->assertViewHas('students', function (LengthAwarePaginator $students) use ($ana, $ben): bool {
                $rows = $students->keyBy('id');

                return $students->pluck('id')->all() === [$ana->id, $ben->id]
                    && round((float) $rows[$ana->id]->classroom_average, 1) === 80.0
                    && (int) $rows[$ana->id]->quizzes_completed === 2
                    && $rows[$ben->id]->classroom_average === null
                    && (int) $rows[$ben->id]->quizzes_completed === 0;
            })
            ->assertSee('data-roster-row="'.$ana->id.'"', false)
            ->assertSee('aria-label="Level 3"', false)
            ->assertSeeText('1,240 XP')
            ->assertSeeText('ana_reyes')
            ->assertSeeText('HLT5SA')
            ->assertSeeText('Learners enter this code in the NutriMind game to join this class.')
            ->assertSee('href="'.route('teacher.students.show', $ana).'"', false)
            ->assertSee('action="'.route('teacher.classrooms.students.password.update', [$classroom, $ana]).'"', false)
            ->assertSee('action="'.route('teacher.classrooms.students.destroy', [$classroom, $ana]).'"', false);
    });

    it('searches the roster by name, username and LRN', function (string $field) {
        $classroom = Classroom::factory()->create();
        $match = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create(['name' => 'Carlo Manalastas', 'username' => 'carlo_m']);
        $match->studentProfile->update(['lrn' => '136742210001']);
        User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create(['name' => 'Dina Lopez', 'username' => 'dina_l']);
        $terms = ['name' => 'Manalas', 'username' => 'carlo_m', 'LRN' => '210001'];

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.show', [$classroom, 'search' => $terms[$field]]))
            ->assertViewHas('students', fn (LengthAwarePaginator $students): bool => $students->pluck('id')->all() === [$match->id]);
    })->with(['name', 'username', 'LRN']);

    it('pages the roster twenty-five learners at a time', function () {
        $classroom = Classroom::factory()->create();
        User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->count(ClassroomController::LEARNERS_PER_PAGE + 1)->create();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.show', $classroom))
            ->assertViewHas('students', fn (LengthAwarePaginator $students): bool => $students->count() === 25 && $students->total() === 26);
    });

    it('lists the assigned quizzes with completion by enrolled learners and the class average', function () {
        $classroom = Classroom::factory()->create();
        $teacher = $classroom->teacher;
        [$ana, $ben] = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->count(3)->create();
        $leaver = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();
        $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($classroom->subject)->hasQuestions(4)->create(['title' => 'Go, Grow, Glow Quiz']);
        $classroom->quizzes()->attach($quiz, ['due_at' => now()->addDays(3)]);
        QuizAttempt::factory()->for($quiz)->for($ana, 'student')->for($classroom)->create(['score' => 8, 'max_score' => 10, 'percentage' => 80, 'passed' => true]);
        QuizAttempt::factory()->for($quiz)->for($ben, 'student')->for($classroom)->create(['score' => 6, 'max_score' => 10, 'percentage' => 60, 'passed' => false]);
        QuizAttempt::factory()->for($quiz)->for($leaver, 'student')->for($classroom)->create(['score' => 4, 'max_score' => 10, 'percentage' => 40, 'passed' => false]);
        $classroom->students()->detach($leaver);

        $response = $this->actingAs($teacher)->get(route('teacher.classrooms.show', [$classroom, 'tab' => 'quizzes']));

        $response->assertOk()
            ->assertViewHas('tab', 'quizzes')
            ->assertViewHas('quizzes', function (LengthAwarePaginator $quizzes) use ($quiz): bool {
                $row = $quizzes->sole();

                return $row->is($quiz)
                    && (int) $row->attempted_count === 2
                    && round((float) $row->classroom_average, 1) === 60.0
                    && (int) $row->questions_count === 4;
            })
            ->assertSee('data-assigned-quiz-completion="2/3"', false)
            ->assertSeeText('Go, Grow, Glow Quiz')
            ->assertSeeText('2 of 3')
            ->assertSee('href="'.route('teacher.quizzes.show', $quiz).'"', false)
            ->assertSee('href="'.route('teacher.quizzes.results.show', $quiz).'"', false);
    });

    it('computes the overview figures from this classroom’s attempts, missions and enrolled learners', function () {
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
        $teacher = $classroom->teacher;
        [$ana, $ben, $carlo] = User::factory()->student(GradeLevel::Five)->enrolledIn($classroom)->count(3)->create();
        $ana->studentProfile->forceFill(['last_active_at' => now()->subDays(2)])->save();
        $ben->studentProfile->forceFill(['last_active_at' => now()->subDays(10)])->save();
        $carlo->studentProfile->forceFill(['last_active_at' => null])->save();
        $published = Quiz::factory()->published()->for($teacher, 'teacher')->for($classroom->subject)->create();
        $classroom->quizzes()->attach(Quiz::factory()->for($teacher, 'teacher')->for($classroom->subject)->create());
        QuizAttempt::factory()->for($published)->for($ana, 'student')->for($classroom)->create(['score' => 8, 'max_score' => 10, 'percentage' => 80, 'passed' => true]);
        QuizAttempt::factory()->for($published)->for($ben, 'student')->for($classroom)->create(['score' => 6, 'max_score' => 10, 'percentage' => 60, 'passed' => false]);
        $mission = Mission::factory()->for($classroom->subject)->create(['grade_level' => null]);
        Mission::factory()->for($classroom->subject)->create(['grade_level' => GradeLevel::Six]);
        Mission::factory()->inactive()->for($classroom->subject)->create(['grade_level' => GradeLevel::Five]);
        $otherSubjectMission = Mission::factory()->create(['grade_level' => GradeLevel::Five]);
        MissionProgress::factory()->completed()->for($mission)->for($ana, 'student')->create();
        MissionProgress::factory()->inProgress()->for($mission)->for($ben, 'student')->create();
        MissionProgress::factory()->completed()->for($otherSubjectMission)->for($carlo, 'student')->create();

        $response = $this->actingAs($teacher)->get(route('teacher.classrooms.show', [$classroom, 'tab' => 'overview']));

        $response->assertOk()->assertViewHas('stats', [
            'learners' => 3,
            'activeThisWeek' => 1,
            'attempts' => 2,
            'averageScore' => 70.0,
            'passRate' => 50.0,
            'openQuizzes' => 1,
            'completedPairs' => 2,
            'expectedPairs' => 3,
            'completionRate' => 66.7,
            'missionsAvailable' => 1,
            'missionsCompleted' => 1,
            'missionsTotal' => 3,
            'bandCounts' => ['satisfactory' => 1, 'did_not_meet_expectations' => 1, 'no_data' => 1],
        ])
            ->assertSee('data-chart="stacked"', false)
            ->assertSeeText('66.7%');
        expect($response->getContent())->toMatch('#data-learning-mode="'.$classroom->learning_mode->value.'"\s+aria-current="true"#');
    });

    it('falls back to the learners tab for an unknown tab', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.show', [$classroom, 'tab' => 'secrets']))
            ->assertOk()
            ->assertViewHas('tab', 'learners');
    });

    it('offers deleting only while the classroom is empty', function () {
        $empty = Classroom::factory()->create();
        $withLearner = Classroom::factory()->for($empty->teacher, 'teacher')->for($empty->subject)->create();
        User::factory()->student($withLearner->grade_level)->enrolledIn($withLearner)->create();

        $this->actingAs($empty->teacher)
            ->get(route('teacher.classrooms.show', $empty))
            ->assertViewHas('canBeDeleted', true)
            ->assertSeeText('Delete classroom')
            ->assertSeeText('Delete '.$empty->name.'?');

        $this->get(route('teacher.classrooms.show', $withLearner))
            ->assertViewHas('canBeDeleted', false)
            ->assertDontSeeText('Delete classroom');
    });

    it('keeps an archived classroom read-only for learner management', function () {
        $classroom = Classroom::factory()->archived()->create();
        $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.show', $classroom))
            ->assertOk()
            ->assertSeeText('This classroom is archived')
            ->assertSee('action="'.route('teacher.classrooms.archive.destroy', $classroom).'"', false)
            ->assertDontSee('href="'.route('teacher.classrooms.students.create', $classroom).'"', false)
            ->assertDontSee('href="'.route('teacher.classrooms.import.create', $classroom).'"', false)
            ->assertDontSee('action="'.route('teacher.classrooms.students.destroy', [$classroom, $learner]).'"', false);
    });

    it('shows the CSV import only to teachers who may import learners', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.show', $classroom))
            ->assertSee('href="'.route('teacher.classrooms.import.create', $classroom).'"', false);

        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ImportStudents);

        $this->get(route('teacher.classrooms.show', $classroom))
            ->assertDontSee('href="'.route('teacher.classrooms.import.create', $classroom).'"', false);
    });

    it('hides the learner actions from teachers without the manage learners permission', function () {
        $classroom = Classroom::factory()->create();
        $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageStudents);

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.show', $classroom))
            ->assertOk()
            ->assertDontSee('href="'.route('teacher.classrooms.students.create', $classroom).'"', false)
            ->assertDontSee('action="'.route('teacher.classrooms.students.password.update', [$classroom, $learner]).'"', false)
            ->assertSee('href="'.route('teacher.students.show', $learner).'"', false);
    });

    it('shows sign-in details flashed once after adding a learner or resetting a password', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs($classroom->teacher)
            ->withSession(['credentials' => [['name' => 'Ana Reyes', 'username' => 'areyes', 'password' => 'mango-4821']]])
            ->get(route('teacher.classrooms.show', $classroom))
            ->assertSee('data-credentials-alert="1"', false)
            ->assertSeeText('mango-4821');
    });

    it('renders the empty states of every tab', function (string $tab, string $text) {
        $classroom = Classroom::factory()->create();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.show', [$classroom, 'tab' => $tab]))
            ->assertOk()
            ->assertSeeText($text);
    })->with([
        'learners' => ['learners', 'No learners yet'],
        'quizzes' => ['quizzes', 'No quizzes assigned yet'],
        'overview' => ['overview', 'No submitted quiz attempts yet'],
    ]);
});

describe('edit', function () {
    it('shows the settings form with the subject and grade locked', function () {
        $subject = Subject::factory()->create(['name' => 'Health']);
        $classroom = Classroom::factory()->for($subject)->create(['section' => 'Sampaguita', 'grade_level' => GradeLevel::Five]);

        $response = $this->actingAs($classroom->teacher)->get(route('teacher.classrooms.edit', $classroom));

        $response->assertOk()
            ->assertViewIs('teacher.classrooms.edit')
            ->assertSee('action="'.route('teacher.classrooms.update', $classroom).'"', false)
            ->assertSee('value="Sampaguita"', false)
            ->assertDontSee('name="subject_id"', false)
            ->assertDontSee('name="grade_level"', false)
            ->assertSee('data-classrooms-fixed-subject="Health"', false)
            ->assertSee('data-classrooms-fixed-grade="5"', false);
        expect($response->getContent())
            ->toMatch('#<input[^>]*name="subject_display"[^>]*value="Health"[^>]*disabled#')
            ->toMatch('#<input[^>]*name="grade_display"[^>]*value="Grade 5"[^>]*disabled#');
    });

    it('reports another teacher’s classroom as missing', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs(User::factory()->teaching($classroom->subject)->create())
            ->get(route('teacher.classrooms.edit', $classroom))
            ->assertNotFound();
    });

    it('refuses the owner once the subject was revoked', function () {
        $classroom = Classroom::factory()->revokedSubject()->create();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.edit', $classroom))
            ->assertForbidden()
            ->assertSeeText(ClassroomPolicy::SUBJECT_REVOKED_MESSAGE);
    });

    it('forbids teachers without the manage classrooms permission', function () {
        $classroom = Classroom::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageClassrooms);

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.edit', $classroom))
            ->assertForbidden();
    });
});

describe('update', function () {
    it('updates the editable details and keeps the subject and grade', function () {
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five, 'learning_mode' => LearningMode::Automatic]);
        $originalSubjectId = $classroom->subject_id;

        $response = $this->actingAs($classroom->teacher)->put(route('teacher.classrooms.update', $classroom), [
            'section' => 'Ilang-Ilang',
            'name' => 'Health 5 – Ilang-Ilang',
            'room' => 'Room 108',
            'description' => 'Moved to the new building.',
            'learning_mode' => 'manual',
            'subject_id' => Subject::factory()->create()->id,
            'grade_level' => '6',
        ]);

        $response->assertRedirectToRoute('teacher.classrooms.show', $classroom)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Health 5 – Ilang-Ilang was updated.');
        expect($classroom->refresh())
            ->section->toBe('Ilang-Ilang')
            ->name->toBe('Health 5 – Ilang-Ilang')
            ->room->toBe('Room 108')
            ->description->toBe('Moved to the new building.')
            ->learning_mode->toBe(LearningMode::Manual)
            ->subject_id->toBe($originalSubjectId)
            ->grade_level->toBe(GradeLevel::Five);
    });

    it('rejects a section another classroom of the same subject, grade and school year uses', function () {
        $subject = Subject::factory()->create(['name' => 'Science']);
        $classroom = Classroom::factory()->for($subject)->create(['grade_level' => GradeLevel::Six, 'section' => 'Narra']);
        Classroom::factory()->for($subject)->for($classroom->schoolYear)->create(['grade_level' => GradeLevel::Six, 'section' => 'Molave']);

        $this->actingAs($classroom->teacher)
            ->put(route('teacher.classrooms.update', $classroom), ['section' => 'MOLAVE', 'name' => 'Science 6 – Molave', 'learning_mode' => 'automatic'])
            ->assertSessionHasErrors(['section' => 'A Science Grade 6 – Molave classroom already exists this school year.']);

        expect($classroom->refresh()->section)->toBe('Narra');
    });

    it('keeps the classroom’s own section valid', function () {
        $classroom = Classroom::factory()->create(['section' => 'Narra']);

        $this->actingAs($classroom->teacher)
            ->put(route('teacher.classrooms.update', $classroom), ['section' => 'Narra', 'name' => 'Renamed class', 'learning_mode' => 'automatic'])
            ->assertSessionHasNoErrors();

        expect($classroom->refresh()->name)->toBe('Renamed class');
    });

    it('rejects invalid details', function (array $payload, string $field, string $message) {
        $classroom = Classroom::factory()->create();

        $this->actingAs($classroom->teacher)
            ->put(route('teacher.classrooms.update', $classroom), [...['section' => 'Narra', 'name' => 'Health 6 – Narra', 'learning_mode' => 'automatic'], ...$payload])
            ->assertSessionHasErrors([$field => $message]);
    })->with([
        'missing section' => [['section' => ''], 'section', 'The section field is required.'],
        'missing name' => [['name' => ''], 'name', 'The name field is required.'],
        'long name' => [['name' => str_repeat('a', 101)], 'name', 'The name field must not be greater than 100 characters.'],
        'long room' => [['room' => str_repeat('a', 51)], 'room', 'The room field must not be greater than 50 characters.'],
        'long description' => [['description' => str_repeat('a', 501)], 'description', 'The description field must not be greater than 500 characters.'],
        'unknown learning mode' => [['learning_mode' => 'random'], 'learning_mode', 'The selected learning mode is invalid.'],
    ]);

    it('reports another teacher’s classroom as missing', function () {
        $classroom = Classroom::factory()->create(['name' => 'Original']);

        $this->actingAs(User::factory()->teaching($classroom->subject)->create())
            ->put(route('teacher.classrooms.update', $classroom), ['section' => 'Narra', 'name' => 'Taken over', 'learning_mode' => 'automatic'])
            ->assertNotFound();

        expect($classroom->refresh()->name)->toBe('Original');
    });

    it('forbids teachers without the manage classrooms permission', function () {
        $classroom = Classroom::factory()->create(['name' => 'Original']);
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageClassrooms);

        $this->actingAs($classroom->teacher)
            ->put(route('teacher.classrooms.update', $classroom), ['section' => 'Narra', 'name' => 'Renamed', 'learning_mode' => 'automatic'])
            ->assertForbidden();

        expect($classroom->refresh()->name)->toBe('Original');
    });
});

describe('destroy', function () {
    it('deletes an empty classroom', function () {
        $classroom = Classroom::factory()->create(['name' => 'Health 5 – Rosal']);

        $this->actingAs($classroom->teacher)
            ->delete(route('teacher.classrooms.destroy', $classroom))
            ->assertRedirectToRoute('teacher.classrooms.index')
            ->assertSessionHas('status', 'Health 5 – Rosal was deleted.');

        $this->assertModelMissing($classroom);
    });

    it('refuses to delete a classroom that has learners, quizzes or attempts', function (Closure $fill) {
        $classroom = Classroom::factory()->create(['name' => 'Health 5 – Rosal']);
        $fill($classroom);

        $this->actingAs($classroom->teacher)
            ->delete(route('teacher.classrooms.destroy', $classroom))
            ->assertRedirectToRoute('teacher.classrooms.show', $classroom)
            ->assertSessionHas('error', 'Health 5 – Rosal still has learners, quizzes or quiz attempts. Archive it instead.');

        $this->assertModelExists($classroom);
    })->with([
        'a learner' => fn (Classroom $classroom) => User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create(),
        'an assigned quiz' => fn (Classroom $classroom) => $classroom->quizzes()->attach(Quiz::factory()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create()),
        'an attempt of a removed learner' => function (Classroom $classroom): void {
            $quiz = Quiz::factory()->published()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create();
            $attempt = QuizAttempt::factory()->for($quiz)->for($classroom)->create();
            $classroom->students()->detach($attempt->student_id);
            $classroom->quizzes()->detach($quiz);
        },
    ]);

    it('reports another teacher’s classroom as missing', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs(User::factory()->teaching($classroom->subject)->create())
            ->delete(route('teacher.classrooms.destroy', $classroom))
            ->assertNotFound();

        $this->assertModelExists($classroom);
    });

    it('forbids teachers without the manage classrooms permission', function () {
        $classroom = Classroom::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageClassrooms);

        $this->actingAs($classroom->teacher)
            ->delete(route('teacher.classrooms.destroy', $classroom))
            ->assertForbidden();

        $this->assertModelExists($classroom);
    });
});
