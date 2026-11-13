<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\Sex;
use App\Enums\UserRole;
use App\Http\Controllers\Teacher\ClassroomStudentController;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Policies\ClassroomPolicy;
use App\Services\RolePermissions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

describe('create', function () {
    it('shows the new learner form with the grade fixed to the classroom', function () {
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);

        $response = $this->actingAs($classroom->teacher)->get(route('teacher.classrooms.students.create', $classroom));

        $response->assertOk()
            ->assertViewIs('teacher.classrooms.students.create')
            ->assertViewHas('matches', null)
            ->assertSee('action="'.route('teacher.classrooms.students.store', $classroom).'"', false)
            ->assertDontSee('name="grade_level"', false)
            ->assertSeeText('Search for a learner')
            ->assertSee('href="'.route('teacher.classrooms.import.create', $classroom).'"', false);
        expect($response->getContent())->toMatch('#<input[^>]*name="grade_display"[^>]*value="Grade 5"[^>]*disabled#');
    });

    it('finds active learners of the classroom’s grade who are not enrolled yet by name, LRN or username', function (string $term) {
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
        $elsewhere = Classroom::factory()->create(['grade_level' => GradeLevel::Five, 'name' => 'Science 5 – Rosal']);
        $match = User::factory()->student(GradeLevel::Five)->enrolledIn($elsewhere)->create(['name' => 'Sofia Navarro', 'username' => 'sofia_navarro']);
        $match->studentProfile->update(['lrn' => '136742210123']);
        User::factory()->student(GradeLevel::Five)->enrolledIn($classroom)->create(['name' => 'Sofia Enrolled', 'username' => 'sofia_enrolled']);
        User::factory()->student(GradeLevel::Six)->create(['name' => 'Sofia Older', 'username' => 'sofia_older']);
        User::factory()->student(GradeLevel::Five)->inactive()->create(['name' => 'Sofia Inactive', 'username' => 'sofia_inactive']);
        User::factory()->parent()->create(['name' => 'Sofia Parent', 'username' => 'sofia_parent']);

        $response = $this->actingAs($classroom->teacher)->get(route('teacher.classrooms.students.create', [$classroom, 'search' => $term]));

        $response->assertOk()
            ->assertViewHas('matches', fn (Collection $matches): bool => $matches->modelKeys() === [$match->id])
            ->assertViewHas('hasMoreMatches', false)
            ->assertSee('data-enroll-match="'.$match->id.'"', false)
            ->assertSee('action="'.route('teacher.classrooms.enrollments.store', $classroom).'"', false)
            ->assertSeeText('Science 5 – Rosal');
    })->with([
        'name' => 'Sofia',
        'LRN' => '210123',
        'username' => 'sofia_nav',
    ]);

    it('lists at most ten matches and says when there are more', function () {
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Six]);
        User::factory()->student(GradeLevel::Six)->count(ClassroomStudentController::SEARCH_LIMIT + 1)->sequence(fn ($sequence) => ['name' => sprintf('Learner %02d', $sequence->index + 1)])->create();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.students.create', [$classroom, 'search' => 'Learner']))
            ->assertViewHas('matches', fn (Collection $matches): bool => $matches->count() === 10 && $matches->first()->name === 'Learner 01')
            ->assertViewHas('hasMoreMatches', true)
            ->assertSee('data-enroll-more', false);
    });

    it('shows an empty state when no learner matches', function () {
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.students.create', [$classroom, 'search' => 'Nobody here']))
            ->assertOk()
            ->assertSeeText('No learners found');
    });

    it('shows a refused enrollment above the search results it came from', function () {
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
        $older = User::factory()->student(GradeLevel::Six)->create(['name' => 'Miguel Navarro']);
        User::factory()->student(GradeLevel::Five)->create(['name' => 'Sofia Navarro']);
        $searchUrl = route('teacher.classrooms.students.create', [$classroom, 'search' => 'Navarro']);

        $this->actingAs($classroom->teacher)
            ->from($searchUrl)
            ->followingRedirects()
            ->post(route('teacher.classrooms.enrollments.store', $classroom), ['student_ids' => [$older->id]])
            ->assertOk()
            ->assertSee('data-enroll-error', false)
            ->assertSeeText('Only active Grade 5 learners who are not in this class yet can be enrolled.')
            ->assertSeeText('Sofia Navarro');
    });

    it('keeps the typed learner details and shows the errors after a refused account', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs($classroom->teacher)
            ->from(route('teacher.classrooms.students.create', $classroom))
            ->followingRedirects()
            ->post(route('teacher.classrooms.students.store', $classroom), ['name' => 'Ana Reyes', 'lrn' => '123'])
            ->assertOk()
            ->assertSee('value="Ana Reyes"', false)
            ->assertSeeText('The LRN must be exactly 12 digits.');
    });

    it('refuses archived classrooms', function () {
        $classroom = Classroom::factory()->archived()->create();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.students.create', $classroom))
            ->assertForbidden()
            ->assertSeeText(ClassroomPolicy::ARCHIVED_MESSAGE);
    });

    it('forbids teachers without the manage learners permission', function () {
        $classroom = Classroom::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageStudents);

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.students.create', $classroom))
            ->assertForbidden()
            ->assertSeeText(RolePermissions::deniedMessage(Permission::ManageStudents));
    });

    it('reports another teacher’s classroom as missing', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs(User::factory()->teaching($classroom->subject)->create())
            ->get(route('teacher.classrooms.students.create', $classroom))
            ->assertNotFound();
    });

    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.classrooms.students.create', Classroom::factory()->create()))->assertRedirectToRoute('login');
    });
});

describe('store', function () {
    it('creates a learner in the classroom’s grade, enrolls them and shows generated sign-in details once', function () {
        $this->freezeSecond();
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Six, 'name' => 'Health 6 – Narra']);

        $response = $this->actingAs($classroom->teacher)->post(route('teacher.classrooms.students.store', $classroom), [
            'name' => 'Juan Dela Cruz',
            'lrn' => '136742200001',
            'sex' => 'male',
            'birthdate' => '2015-04-12',
            'username' => '',
            'password' => '',
        ]);

        $learner = User::query()->ofRole(UserRole::Student)->sole();
        $credentials = session('credentials');
        $response->assertRedirectToRoute('teacher.classrooms.show', $classroom)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Juan Dela Cruz was added to Health 6 – Narra. Save the sign-in details now — the password is shown only once.');
        expect($learner)
            ->username->toBe('jdelacruz')
            ->is_active->toBeTrue()
            ->and($learner->studentProfile)
            ->grade_level->toBe(GradeLevel::Six)
            ->lrn->toBe('136742200001')
            ->sex->toBe(Sex::Male)
            ->and($learner->studentProfile->birthdate->toDateString())->toBe('2015-04-12')
            ->and($classroom->students()->sole()->pivot->enrolled_at->toDateTimeString())->toBe(now()->toDateTimeString())
            ->and($credentials)->toHaveCount(1)
            ->and($credentials[0]['name'])->toBe('Juan Dela Cruz')
            ->and($credentials[0]['username'])->toBe('jdelacruz')
            ->and($credentials[0]['password'])->toMatch('/^[a-z]+-\d{4}$/')
            ->and(Hash::check($credentials[0]['password'], $learner->password))->toBeTrue();
    });

    it('uses the username and password the teacher typed', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs($classroom->teacher)->post(route('teacher.classrooms.students.store', $classroom), [
            'name' => 'Ana Reyes',
            'username' => 'ana_reyes',
            'password' => 'healthy2026',
        ])->assertSessionHasNoErrors();

        $learner = User::query()->where('username', 'ana_reyes')->sole();
        expect(Hash::check('healthy2026', $learner->password))->toBeTrue()
            ->and(session('credentials')[0]['password'])->toBe('healthy2026');
    });

    it('never takes the grade, role or account status from the payload', function () {
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);

        $this->actingAs($classroom->teacher)->post(route('teacher.classrooms.students.store', $classroom), [
            'name' => 'Ana Reyes',
            'grade_level' => '6',
            'role' => 'admin',
            'is_active' => '0',
        ])->assertSessionHasNoErrors();

        $learner = User::query()->where('name', 'Ana Reyes')->sole();
        expect($learner->role)->toBe(UserRole::Student)
            ->and($learner->is_active)->toBeTrue()
            ->and($learner->studentProfile->grade_level)->toBe(GradeLevel::Five);
    });

    it('rejects invalid learner details', function (array $payload, string $field, string $message) {
        $classroom = Classroom::factory()->create();
        User::factory()->student()->create(['username' => 'taken_name'])->studentProfile->update(['lrn' => '136742200099']);

        $this->actingAs($classroom->teacher)
            ->post(route('teacher.classrooms.students.store', $classroom), [...['name' => 'Ana Reyes'], ...$payload])
            ->assertSessionHasErrors([$field => $message]);

        expect($classroom->students()->count())->toBe(0);
    })->with([
        'missing name' => [['name' => ''], 'name', 'The full name field is required.'],
        'long name' => [['name' => str_repeat('a', 121)], 'name', 'The full name field must not be greater than 120 characters.'],
        'short LRN' => [['lrn' => '12345'], 'lrn', 'The LRN must be exactly 12 digits.'],
        'LRN with letters' => [['lrn' => '13674220000A'], 'lrn', 'The LRN must be exactly 12 digits.'],
        'taken LRN' => [['lrn' => '136742200099'], 'lrn', 'Another learner already has this LRN.'],
        'unknown sex' => [['sex' => 'other'], 'sex', 'The selected sex is invalid.'],
        'birthdate not a date' => [['birthdate' => 'soon'], 'birthdate', 'The birthdate field must be a valid date.'],
        'birthdate in the future' => [['birthdate' => now()->addDay()->toDateString()], 'birthdate', 'The birthdate must be a date before today.'],
        'username with spaces' => [['username' => 'ana reyes'], 'username', 'The username field must only contain letters, numbers, dashes, and underscores.'],
        'long username' => [['username' => str_repeat('a', 31)], 'username', 'The username field must not be greater than 30 characters.'],
        'taken username in another case' => [['username' => 'Taken_Name'], 'username', 'This username is already taken.'],
        'weak password' => [['password' => 'abc'], 'password', 'The password field must be at least 8 characters.'],
        'password without numbers' => [['password' => 'onlyletters'], 'password', 'The password field must contain at least one number.'],
    ]);

    it('refuses archived classrooms', function () {
        $classroom = Classroom::factory()->archived()->create();

        $this->actingAs($classroom->teacher)
            ->post(route('teacher.classrooms.students.store', $classroom), ['name' => 'Ana Reyes'])
            ->assertForbidden();

        expect(User::query()->ofRole(UserRole::Student)->count())->toBe(0);
    });

    it('forbids teachers without the manage learners permission', function () {
        $classroom = Classroom::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageStudents);

        $this->actingAs($classroom->teacher)
            ->post(route('teacher.classrooms.students.store', $classroom), ['name' => 'Ana Reyes'])
            ->assertForbidden();

        expect(User::query()->ofRole(UserRole::Student)->count())->toBe(0);
    });

    it('reports another teacher’s classroom as missing', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs(User::factory()->teaching($classroom->subject)->create())
            ->post(route('teacher.classrooms.students.store', $classroom), ['name' => 'Ana Reyes'])
            ->assertNotFound();

        expect(User::query()->ofRole(UserRole::Student)->count())->toBe(0);
    });

    it('forbids accounts of the other portals', function (UserRole $role) {
        $classroom = Classroom::factory()->create();

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->post(route('teacher.classrooms.students.store', $classroom), ['name' => 'Ana Reyes'])
            ->assertForbidden();
    })->with([
        'administrator' => UserRole::Admin,
        'parent' => UserRole::Parent,
    ]);
});

describe('destroy', function () {
    it('removes the learner from the classroom and keeps their attempts', function () {
        $classroom = Classroom::factory()->create(['name' => 'Health 5 – Sampaguita']);
        $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create(['name' => 'Ana Reyes']);
        $quiz = Quiz::factory()->published()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create();
        $attempt = QuizAttempt::factory()->for($quiz)->for($learner, 'student')->for($classroom)->create();

        $this->actingAs($classroom->teacher)
            ->delete(route('teacher.classrooms.students.destroy', [$classroom, $learner]))
            ->assertRedirectToRoute('teacher.classrooms.show', $classroom)
            ->assertSessionHas('status', 'Ana Reyes was removed from Health 5 – Sampaguita. Their quiz results stay in your reports.');

        expect($classroom->students()->count())->toBe(0);
        $this->assertModelExists($learner);
        $this->assertModelExists($attempt);
        expect($attempt->refresh()->classroom_id)->toBe($classroom->id);
    });

    it('reports a learner who is not enrolled in the classroom as missing', function () {
        $classroom = Classroom::factory()->create();
        $elsewhere = Classroom::factory()->create();
        $learner = User::factory()->student()->enrolledIn($elsewhere)->create();

        $this->actingAs($classroom->teacher)
            ->delete(route('teacher.classrooms.students.destroy', [$classroom, $learner]))
            ->assertNotFound();

        expect($elsewhere->students()->whereKey($learner->id)->exists())->toBeTrue();
    });

    it('reports accounts that are not learners as missing', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs($classroom->teacher)
            ->delete(route('teacher.classrooms.students.destroy', [$classroom, User::factory()->parent()->create()]))
            ->assertNotFound();
    });

    it('refuses archived classrooms', function () {
        $classroom = Classroom::factory()->archived()->create();
        $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();

        $this->actingAs($classroom->teacher)
            ->delete(route('teacher.classrooms.students.destroy', [$classroom, $learner]))
            ->assertForbidden()
            ->assertSeeText(ClassroomPolicy::ARCHIVED_MESSAGE);

        expect($classroom->students()->count())->toBe(1);
    });

    it('forbids teachers without the manage learners permission', function () {
        $classroom = Classroom::factory()->create();
        $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageStudents);

        $this->actingAs($classroom->teacher)
            ->delete(route('teacher.classrooms.students.destroy', [$classroom, $learner]))
            ->assertForbidden();

        expect($classroom->students()->count())->toBe(1);
    });

    it('reports another teacher’s classroom as missing', function () {
        $classroom = Classroom::factory()->create();
        $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();

        $this->actingAs(User::factory()->teaching($classroom->subject)->create())
            ->delete(route('teacher.classrooms.students.destroy', [$classroom, $learner]))
            ->assertNotFound();

        expect($classroom->students()->count())->toBe(1);
    });

    it('sends guests to the sign-in page', function () {
        $classroom = Classroom::factory()->create();
        $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();

        $this->delete(route('teacher.classrooms.students.destroy', [$classroom, $learner]))->assertRedirectToRoute('login');
    });
});
