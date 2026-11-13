<?php

use App\Http\Requests\Admin\UpdateSubjectAssignmentRequest;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use App\Policies\ClassroomPolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

describe('index', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.subject-assignments.index'))->assertRedirectToRoute('login');
    });

    it('forbids every other role', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('admin.subject-assignments.index'))
            ->assertForbidden();
    })->with(['teacher', 'parent', 'student']);

    it('lists active teachers with their subjects and what revoking would lock', function () {
        $health = Subject::factory()->create(['name' => 'Health', 'code' => 'HEALTH']);
        $science = Subject::factory()->create(['name' => 'Science', 'code' => 'SCI']);
        $teacher = User::factory()->teaching($health)->create(['name' => 'Ana Reyes']);
        Classroom::factory()->for($teacher, 'teacher')->for($health)->count(2)->create();
        Quiz::factory()->for($teacher, 'teacher')->for($health)->create();
        User::factory()->teacher()->inactive()->create(['name' => 'Inactive Teacher']);
        User::factory()->parent()->create(['name' => 'A Parent']);

        $response = $this->actingAs($this->admin)->get(route('admin.subject-assignments.index'));

        $response->assertOk()
            ->assertViewIs('admin.subject-assignments.index')
            ->assertSeeText('Ana Reyes')
            ->assertDontSeeText('Inactive Teacher')
            ->assertDontSeeText('A Parent')
            ->assertSee('id="teacher-'.$teacher->id.'"', false)
            ->assertSee('action="'.route('admin.subject-assignments.update', $teacher).'"', false)
            ->assertSeeText('Revoking removes access to 2 classrooms and 1 quiz.')
            ->assertSee('data-modal="revoke-subjects-'.$teacher->id.'"', false)
            ->assertSee('form="assignment-form-'.$teacher->id.'"', false)
            ->assertSee('id="teacher-'.$teacher->id.'-subject-'.$science->id.'"', false);
        expect($response->getContent())
            ->toMatch('#<input[^>]*id="teacher-'.$teacher->id.'-subject-'.$health->id.'"[^>]*\schecked(?=[\s/>])#')
            ->not->toMatch('#<input[^>]*id="teacher-'.$teacher->id.'-subject-'.$science->id.'"[^>]*\schecked(?=[\s/>])#');
    });

    it('summarizes teachers without subjects and teachers per subject', function () {
        $health = Subject::factory()->create(['name' => 'Health']);
        $science = Subject::factory()->create(['name' => 'Science']);
        User::factory()->teaching($health, $science)->create();
        User::factory()->teaching($health)->create();
        User::factory()->teaching($health)->inactive()->create();
        User::factory()->teacher()->count(2)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.subject-assignments.index'));

        $response->assertOk()->assertSeeText('They can’t open classrooms yet');
        expect($response->viewData('activeTeachersCount'))->toBe(4)
            ->and($response->viewData('unassignedTeachersCount'))->toBe(2)
            ->and($response->viewData('subjects')->pluck('teachers_count', 'name')->all())->toBe(['Health' => 2, 'Science' => 1]);
    });

    it('lets an inactive subject the teacher already has be revoked', function () {
        $retired = Subject::factory()->inactive()->create(['name' => 'Old Health']);
        Subject::factory()->inactive()->create(['name' => 'Unused Inactive']);
        $teacher = User::factory()->teaching($retired)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.subject-assignments.index'));

        $response->assertOk()
            ->assertSee('id="teacher-'.$teacher->id.'-subject-'.$retired->id.'"', false)
            ->assertSeeText('Inactive subject. Uncheck to revoke it.')
            ->assertDontSee('-subject-'.Subject::query()->where('name', 'Unused Inactive')->value('id').'"', false);
    });

    it('filters the teachers by name, subject and coverage', function (Closure $query, array $visible) {
        $health = Subject::factory()->create(['name' => 'Health']);
        $science = Subject::factory()->create(['name' => 'Science']);
        User::factory()->teaching($health)->create(['name' => 'Ana Reyes', 'email' => 'ana@school.test']);
        User::factory()->teaching($science)->create(['name' => 'Ben Cruz', 'email' => 'ben@school.test']);
        User::factory()->teacher()->create(['name' => 'Carla Diaz', 'email' => 'carla@school.test']);

        $response = $this->actingAs($this->admin)->get(route('admin.subject-assignments.index', $query($health)));

        expect($response->viewData('teachers')->getCollection()->pluck('name')->all())->toBe($visible);
    })->with([
        'name' => [fn () => ['search' => 'ben'], ['Ben Cruz']],
        'email' => [fn () => ['search' => 'carla@'], ['Carla Diaz']],
        'subject' => [fn (Subject $health) => ['subject' => $health->id], ['Ana Reyes']],
        'without subjects' => [fn () => ['coverage' => 'unassigned'], ['Carla Diaz']],
        'with subjects' => [fn () => ['coverage' => 'assigned'], ['Ana Reyes', 'Ben Cruz']],
        'unknown coverage is ignored' => [fn () => ['coverage' => 'everyone'], ['Ana Reyes', 'Ben Cruz', 'Carla Diaz']],
    ]);

    it('ignores filters that are not text', function () {
        User::factory()->teacher()->count(2)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.subject-assignments.index', [
            'search' => ['ana'],
            'coverage' => ['unassigned'],
            'subject' => ['1'],
        ]));

        $response->assertOk();
        expect($response->viewData('teachers'))->toHaveCount(2);
    });

    it('invites the administrator to add teachers when there are none', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.subject-assignments.index'))
            ->assertOk()
            ->assertSeeText('No active teachers yet')
            ->assertSee(route('admin.users.create', ['role' => 'teacher']), false)
            ->assertSeeText('No active subjects');
    });

    it('offers to clear the filters when nothing matches', function () {
        User::factory()->teacher()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.subject-assignments.index', ['search' => 'nobody']))
            ->assertOk()
            ->assertSeeText('No teachers match your filters');
    });

    it('paginates ten teachers per page', function () {
        User::factory()->teacher()->count(11)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.subject-assignments.index', ['page' => 2]));

        expect($response->viewData('teachers'))->toHaveCount(1);
    });

    it('runs the same number of queries for more teachers', function () {
        $subjects = Subject::factory()->count(3)->create();
        $makeTeachers = fn (int $count) => User::factory()->teaching(...$subjects->all())->count($count)->create()
            ->each(fn (User $teacher) => Classroom::factory()->for($teacher, 'teacher')->for($subjects->first())->create());
        $makeTeachers(2);
        $this->actingAs($this->admin)->get(route('admin.subject-assignments.index'))->assertOk();

        DB::enableQueryLog();
        $this->get(route('admin.subject-assignments.index'))->assertOk();
        $fewer = count(DB::getQueryLog());
        $makeTeachers(5);
        DB::flushQueryLog();
        $this->get(route('admin.subject-assignments.index'))->assertOk();

        expect(count(DB::getQueryLog()))->toBe($fewer);
    });
});

describe('update', function () {
    it('assigns new subjects as the current administrator and revokes removed ones', function () {
        $health = Subject::factory()->create(['name' => 'Health']);
        $science = Subject::factory()->create(['name' => 'Science']);
        $pe = Subject::factory()->create(['name' => 'Physical Education']);
        $earlierAdmin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create(['name' => 'Ana Reyes']);
        $teacher->assignedSubjects()->attach([$health->id => ['assigned_by' => $earlierAdmin->id], $pe->id => ['assigned_by' => $earlierAdmin->id]]);
        Classroom::factory()->for($teacher, 'teacher')->for($pe)->count(2)->create();

        $this->actingAs($this->admin)
            ->from(route('admin.subject-assignments.index', ['page' => 1]))
            ->put(route('admin.subject-assignments.update', $teacher), ['subject_ids' => [(string) $health->id, (string) $science->id]])
            ->assertRedirect(route('admin.subject-assignments.index', ['page' => 1]).'#teacher-'.$teacher->id)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', "Ana Reyes's subjects were saved: assigned Science; revoked Physical Education (2 classrooms locked).");

        $this->assertDatabaseHas('subject_teacher', ['teacher_id' => $teacher->id, 'subject_id' => $health->id, 'assigned_by' => $earlierAdmin->id]);
        $this->assertDatabaseHas('subject_teacher', ['teacher_id' => $teacher->id, 'subject_id' => $science->id, 'assigned_by' => $this->admin->id]);
        $this->assertDatabaseMissing('subject_teacher', ['teacher_id' => $teacher->id, 'subject_id' => $pe->id]);
    });

    it('revokes every subject when none is checked', function () {
        $health = Subject::factory()->create(['name' => 'Health']);
        $teacher = User::factory()->teaching($health)->create(['name' => 'Ana Reyes']);

        $this->actingAs($this->admin)
            ->put(route('admin.subject-assignments.update', $teacher), [])
            ->assertSessionHas('status', "Ana Reyes's subjects were saved: revoked Health.");

        expect($teacher->assignedSubjects()->exists())->toBeFalse();
    });

    it('says so when nothing changed', function () {
        $health = Subject::factory()->create();
        $teacher = User::factory()->teaching($health)->create(['name' => 'Ana Reyes']);

        $this->actingAs($this->admin)
            ->put(route('admin.subject-assignments.update', $teacher), ['subject_ids' => [$health->id]])
            ->assertSessionHas('status', "Ana Reyes's subjects are unchanged.");
    });

    it('takes away the teacher\'s access to the classrooms of a revoked subject', function () {
        $classroom = Classroom::factory()->create();
        $teacher = $classroom->teacher;
        expect(Gate::forUser($teacher)->inspect('view', $classroom))->toEqual(Response::allow());

        $this->actingAs($this->admin)
            ->put(route('admin.subject-assignments.update', $teacher), ['subject_ids' => []])
            ->assertSessionHasNoErrors();

        expect(Gate::forUser($teacher->refresh())->inspect('view', $classroom))
            ->toEqual(Response::deny(ClassroomPolicy::SUBJECT_REVOKED_MESSAGE));
    });

    it('keeps an inactive subject the teacher already has, but refuses to assign a new inactive one', function () {
        $retired = Subject::factory()->inactive()->create(['name' => 'Old Health']);
        $other = Subject::factory()->inactive()->create(['name' => 'Values Education']);
        $teacher = User::factory()->teaching($retired)->create();

        $this->actingAs($this->admin)
            ->put(route('admin.subject-assignments.update', $teacher), ['subject_ids' => [$retired->id]])
            ->assertSessionHasNoErrors();

        $this->put(route('admin.subject-assignments.update', $teacher), ['subject_ids' => [$retired->id, $other->id]])
            ->assertSessionHasErrorsIn(UpdateSubjectAssignmentRequest::errorBagFor($teacher), [
                'subject_ids' => "Values Education is inactive and can't be assigned. Activate it first.",
            ]);

        expect($teacher->assignedSubjects()->pluck('subjects.id')->all())->toBe([$retired->id]);
    });

    it('rejects invalid subject lists in the teacher\'s own error bag', function (Closure $payload, string $field, string $message) {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($this->admin)
            ->from(route('admin.subject-assignments.index'))
            ->put(route('admin.subject-assignments.update', $teacher), $payload($subject))
            ->assertRedirect(route('admin.subject-assignments.index').'#teacher-'.$teacher->id)
            ->assertSessionHasErrorsIn(UpdateSubjectAssignmentRequest::errorBagFor($teacher), [$field => $message]);

        expect($teacher->assignedSubjects()->exists())->toBeFalse();
    })->with([
        'not a list' => [fn () => ['subject_ids' => 'all'], 'subject_ids', 'The subjects field must be an array.'],
        'unknown subject' => [fn () => ['subject_ids' => [999999]], 'subject_ids.0', 'One of the selected subjects no longer exists. Reload the page and try again.'],
        'not a number' => [fn () => ['subject_ids' => ['health']], 'subject_ids.0', 'The subject field must be an integer.'],
        'twice' => [fn (Subject $subject) => ['subject_ids' => [$subject->id, $subject->id]], 'subject_ids.0', 'A subject was selected twice.'],
    ]);

    it('shows the failed form with its errors and old input only for that teacher', function () {
        $health = Subject::factory()->create(['name' => 'Health']);
        $failing = User::factory()->teacher()->create(['name' => 'Ana Reyes']);
        $other = User::factory()->teacher()->create(['name' => 'Ben Cruz']);

        $this->actingAs($this->admin)
            ->from(route('admin.subject-assignments.index'))
            ->put(route('admin.subject-assignments.update', $failing), ['subject_ids' => [$health->id, 999999]]);
        $response = $this->get(route('admin.subject-assignments.index'));

        $response->assertOk()->assertSeeText('One of the selected subjects no longer exists. Reload the page and try again.');
        expect($response->getContent())
            ->toMatch('#<input[^>]*id="teacher-'.$failing->id.'-subject-'.$health->id.'"[^>]*\schecked(?=[\s/>])#')
            ->not->toMatch('#<input[^>]*id="teacher-'.$other->id.'-subject-'.$health->id.'"[^>]*\schecked(?=[\s/>])#');
    });

    it('answers a user who is not a teacher with 404', function (string $role) {
        $user = User::factory()->{$role}()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.subject-assignments.update', $user), ['subject_ids' => []])
            ->assertNotFound();
    })->with(['admin', 'parent', 'student']);

    it('forbids every other role before validating', function (string $role) {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->put(route('admin.subject-assignments.update', $teacher), ['subject_ids' => 'invalid'])
            ->assertForbidden()
            ->assertSessionHasNoErrors();
    })->with(['teacher', 'parent']);

    it('sends guests to the sign-in page', function () {
        $teacher = User::factory()->teacher()->create();

        $this->put(route('admin.subject-assignments.update', $teacher), ['subject_ids' => []])->assertRedirectToRoute('login');
    });
});
