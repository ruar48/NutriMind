<?php

use App\Enums\GradeLevel;
use App\Enums\GuardianRelationship;
use App\Enums\Sex;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\QuizAttempt;
use App\Models\SchoolYear;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use App\Services\StudentAccounts;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Tests\Fixtures\LearnerProgressFixture;

/**
 * Classrooms of an active school year (two Grade 5, one Grade 6, one archived Grade 5) and a non-archived
 * Grade 5 classroom of the previous school year.
 *
 * @return array{year: SchoolYear, sampaguita: Classroom, ilangIlang: Classroom, narra: Classroom, archived: Classroom, lastYear: Classroom}
 */
$classrooms = function (): array {
    $year = SchoolYear::factory()->active()->create(['name' => '2026–2027']);
    $previousYear = SchoolYear::factory()->create(['name' => '2025–2026']);
    $health = Subject::factory()->create(['name' => 'Health']);
    $teacher = User::factory()->teaching($health)->create(['name' => 'Maricel Santos']);
    $make = fn (SchoolYear $schoolYear, string $name, GradeLevel $grade): Classroom => Classroom::factory()
        ->for($teacher, 'teacher')
        ->for($health)
        ->for($schoolYear)
        ->create(['name' => $name, 'grade_level' => $grade, 'section' => str($name)->afterLast(' ')->toString()]);

    $archived = $make($year, 'Health 5 – Gumamela', GradeLevel::Five);
    $archived->archived_at = now();
    $archived->save();

    return [
        'year' => $year,
        'sampaguita' => $make($year, 'Health 5 – Sampaguita', GradeLevel::Five),
        'ilangIlang' => $make($year, 'Health 5 – Ilang-Ilang', GradeLevel::Five),
        'narra' => $make($year, 'Health 6 – Narra', GradeLevel::Six),
        'archived' => $archived,
        'lastYear' => $make($previousYear, 'Health 5 – Rosal', GradeLevel::Five),
    ];
};

$otherRoles = [
    'teacher' => UserRole::Teacher,
    'parent' => UserRole::Parent,
    'learner' => UserRole::Student,
];

describe('index', function () use ($classrooms, $otherRoles) {
    it('lists learners with LRN, grade, active classrooms, guardians and the computed level', function () use ($classrooms) {
        ['sampaguita' => $sampaguita, 'archived' => $archived] = $classrooms();
        $learner = User::factory()->student(GradeLevel::Five)->enrolledIn($sampaguita, $archived)->create(['name' => 'Sofia Navarro']);
        $learner->studentProfile->forceFill(['lrn' => '136742210001', 'total_points' => 1240, 'level' => 1])->save();
        User::factory()->parent()->guardianOf($learner, GuardianRelationship::Mother)->create();
        User::factory()->parent()->guardianOf($learner, GuardianRelationship::Father)->create();
        User::factory()->teacher()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertViewIs('admin.students.index')
            ->assertViewHas('levels', [$learner->id => 3])
            ->assertViewHas('students', function (LengthAwarePaginator $students) use ($learner, $sampaguita): bool {
                $row = $students->first();

                return $students->total() === 1
                    && $row->is($learner)
                    && $row->guardians_count === 2
                    && $row->enrolledClassrooms->modelKeys() === [$sampaguita->id];
            })
            ->assertSeeText(['Sofia Navarro', '136742210001', 'Grade 5', 'Health 5 – Sampaguita', '1,240 XP'])
            ->assertDontSeeText('Health 5 – Gumamela');
    });

    it('shows at most two classroom chips and counts the rest', function () use ($classrooms) {
        ['sampaguita' => $sampaguita, 'ilangIlang' => $ilangIlang, 'lastYear' => $lastYear] = $classrooms();
        User::factory()->student(GradeLevel::Five)->enrolledIn($sampaguita, $ilangIlang, $lastYear)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('data-classroom-chips="3"', false)
            ->assertSee('data-classroom-chips-more', false)
            ->assertSeeText('+1');
    });

    it('renders a page of learners without lazy loading', function () use ($classrooms) {
        ['sampaguita' => $sampaguita, 'narra' => $narra] = $classrooms();
        $learners = User::factory()->student(GradeLevel::Five)->enrolledIn($sampaguita)->count(4)->create();
        User::factory()->student(GradeLevel::Six)->enrolledIn($narra)->count(3)->create();
        User::factory()->student()->inactive()->create();
        User::factory()->parent()->guardianOf($learners[0])->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertSeeText(['Health 6 – Narra', 'Inactive', 'None', 'Not played yet']);
    });

    it('filters the learners', function (Closure $query, array $expectedNames) use ($classrooms) {
        $setup = $classrooms();
        $sofia = User::factory()->student(GradeLevel::Five)->enrolledIn($setup['sampaguita'])->create(['name' => 'Sofia Navarro', 'username' => 'sofia_navarro']);
        $sofia->studentProfile->update(['lrn' => '136742210077']);
        User::factory()->parent()->guardianOf($sofia)->create();
        User::factory()->student(GradeLevel::Six)->enrolledIn($setup['narra'])->create(['name' => 'Miguel Reyes']);
        User::factory()->student(GradeLevel::Five)->inactive()->create(['name' => 'Carlo Manalastas']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.index', $query($setup)))
            ->assertOk()
            ->assertViewHas('students', fn (LengthAwarePaginator $students): bool => $students->pluck('name')->all() === $expectedNames);
    })->with([
        'grade 6' => [fn (): array => ['grade' => '6'], ['Miguel Reyes']],
        'classroom' => [fn (array $setup): array => ['classroom' => (string) $setup['sampaguita']->id], ['Sofia Navarro']],
        'inactive' => [fn (): array => ['status' => 'inactive'], ['Carlo Manalastas']],
        'guardian linked' => [fn (): array => ['guardian' => 'yes'], ['Sofia Navarro']],
        'no guardian' => [fn (): array => ['guardian' => 'no'], ['Carlo Manalastas', 'Miguel Reyes']],
        'search by name' => [fn (): array => ['search' => 'miguel'], ['Miguel Reyes']],
        'search by username' => [fn (): array => ['search' => 'sofia_nav'], ['Sofia Navarro']],
        'search by LRN' => [fn (): array => ['search' => '210077'], ['Sofia Navarro']],
        'tampered filters' => [fn (): array => ['grade' => '7', 'guardian' => 'maybe', 'classroom' => 'x'], ['Carlo Manalastas', 'Miguel Reyes', 'Sofia Navarro']],
    ]);

    it('sorts by points, highest first', function () {
        $low = User::factory()->student()->create(['name' => 'Low']);
        $low->studentProfile->forceFill(['total_points' => 100])->save();
        $high = User::factory()->student()->create(['name' => 'High']);
        $high->studentProfile->forceFill(['total_points' => 2500])->save();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.index', ['sort' => 'points']))
            ->assertViewHas('students', fn (LengthAwarePaginator $students): bool => $students->pluck('name')->all() === ['High', 'Low']);
    });

    it('paginates 20 learners per page', function () {
        User::factory()->student()->count(21)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.index'))
            ->assertViewHas('students', fn (LengthAwarePaginator $students): bool => $students->total() === 21 && $students->count() === 20);
    });

    it('offers classroom filters of the active school year only, grouped by grade', function () use ($classrooms) {
        $setup = $classrooms();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.index'))
            ->assertViewHas('classroomOptions', [
                'Grade 5' => [$setup['ilangIlang']->id => 'Health 5 – Ilang-Ilang', $setup['sampaguita']->id => 'Health 5 – Sampaguita'],
                'Grade 6' => [$setup['narra']->id => 'Health 6 – Narra'],
            ]);
    });

    it('links the export to the current filters and offers the CSV import', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.index', ['grade' => '5', 'search' => 'ana', 'page' => '2']))
            ->assertOk()
            ->assertSee('href="'.e(route('admin.students.export', ['search' => 'ana', 'grade' => '5'])).'"', false)
            ->assertSee('href="'.route('admin.students.import.create').'"', false);
    });

    it('shows an empty state without learners and one for filters without matches', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.students.index'))->assertOk()->assertSeeText('No learners yet');
        $this->actingAs($admin)->get(route('admin.students.index', ['search' => 'nobody']))->assertOk()->assertSeeText('No learners match your filters');
    });

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.students.index'))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.students.index'))
            ->assertForbidden();
    })->with($otherRoles);
});

describe('create', function () use ($classrooms, $otherRoles) {
    it('shows the learner form with the active school year’s open classrooms', function () use ($classrooms) {
        $setup = $classrooms();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.create'))
            ->assertOk()
            ->assertViewIs('admin.students.create')
            ->assertViewHas('classrooms', fn ($classrooms): bool => $classrooms->modelKeys() === [$setup['ilangIlang']->id, $setup['sampaguita']->id, $setup['narra']->id])
            ->assertSee('action="'.route('admin.students.store').'"', false)
            ->assertSee('data-show-when="grade_level:5"', false)
            ->assertSeeText(['Health 5 – Sampaguita', 'Health 6 – Narra', 'Maricel Santos'])
            ->assertDontSeeText(['Health 5 – Gumamela', 'Health 5 – Rosal']);
    });

    it('explains when no classroom is open', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.create'))
            ->assertOk()
            ->assertSeeText('No classrooms open this school year');
    });

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.students.create'))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.students.create'))
            ->assertForbidden();
    })->with($otherRoles);
});

describe('store', function () use ($classrooms, $otherRoles) {
    it('creates the learner with generated sign-in details and enrolls them', function () use ($classrooms) {
        ['sampaguita' => $sampaguita, 'ilangIlang' => $ilangIlang] = $classrooms();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.students.store'), [
                'name' => ' Juan   Dela Cruz ',
                'lrn' => '1367 4221-0001',
                'grade_level' => '5',
                'sex' => 'male',
                'birthdate' => '2016-02-14',
                'classroom_ids' => [(string) $sampaguita->id, (string) $ilangIlang->id],
            ]);

        $learner = User::query()->where('name', 'Juan Dela Cruz')->firstOrFail();
        $response->assertRedirect(route('admin.students.show', $learner))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Juan Dela Cruz was added as a Grade 5 learner.');
        $credentials = session('credentials');
        expect($learner)
            ->role->toBe(UserRole::Student)
            ->is_active->toBeTrue()
            ->username->toBe('jdelacruz')
            ->and($learner->studentProfile)
            ->lrn->toBe('136742210001')
            ->grade_level->toBe(GradeLevel::Five)
            ->sex->toBe(Sex::Male)
            ->and($learner->studentProfile->birthdate->toDateString())->toBe('2016-02-14')
            ->and($learner->enrolledClassrooms()->pluck('classrooms.id')->sort()->values()->all())->toBe([$sampaguita->id, $ilangIlang->id])
            ->and($credentials)->toBe([['name' => 'Juan Dela Cruz', 'username' => 'jdelacruz', 'password' => $credentials[0]['password']]])
            ->and($credentials[0]['password'])->toMatch('/^('.implode('|', StudentAccounts::PASSWORD_WORDS).')-\d{4}$/')
            ->and(Hash::check($credentials[0]['password'], $learner->password))->toBeTrue();
    });

    it('keeps typed sign-in details and shows no credentials', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.students.store'), [
                'name' => 'Ana Reyes',
                'grade_level' => '6',
                'username' => 'ana_reyes',
                'password' => 'mango2026',
                'password_confirmation' => 'mango2026',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('credentials');

        $learner = User::query()->where('username', 'ana_reyes')->firstOrFail();
        expect(Hash::check('mango2026', $learner->password))->toBeTrue()
            ->and($learner->studentProfile->grade_level)->toBe(GradeLevel::Six)
            ->and($learner->enrolledClassrooms()->exists())->toBeFalse();
    });

    it('shows the credentials when only the username was generated', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.students.store'), ['name' => 'Ana Reyes', 'grade_level' => '6', 'password' => 'mango2026', 'password_confirmation' => 'mango2026'])
            ->assertSessionHas('credentials', [['name' => 'Ana Reyes', 'username' => 'areyes', 'password' => 'mango2026']]);
    });

    it('never mass assigns the role, status or game progress', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.students.store'), [
                'name' => 'Ana Reyes',
                'grade_level' => '6',
                'role' => 'admin',
                'is_active' => '0',
                'total_points' => '9000',
                'email' => 'ana@example.com',
            ])
            ->assertSessionHasNoErrors();

        $learner = User::query()->where('name', 'Ana Reyes')->firstOrFail();
        expect($learner)->role->toBe(UserRole::Student)->is_active->toBeTrue()->email->toBeNull()
            ->and($learner->studentProfile->total_points)->toBe(0);
    });

    it('requires a name and a grade level', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.students.store'), [])
            ->assertSessionHasErrors([
                'name' => 'The full name field is required.',
                'grade_level' => 'The grade level field is required.',
            ]);

        expect(User::query()->ofRole(UserRole::Student)->exists())->toBeFalse();
    });

    it('rejects invalid learner details', function (Closure $overrides, string $field, string $message) use ($classrooms) {
        $setup = $classrooms();
        $taken = User::factory()->student()->create(['username' => 'TakenName']);
        $taken->studentProfile->update(['lrn' => '136742219999']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.students.store'), [...['name' => 'Ana Reyes', 'grade_level' => '5'], ...$overrides($setup)])
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseMissing('users', ['name' => 'Ana Reyes']);
    })->with([
        'long name' => [fn (): array => ['name' => str_repeat('a', 256)], 'name', 'The full name field must not be greater than 255 characters.'],
        'short LRN' => [fn (): array => ['lrn' => '12345'], 'lrn', 'The LRN field must be 12 digits.'],
        'LRN with letters' => [fn (): array => ['lrn' => '13674221000A'], 'lrn', 'The LRN field must be 12 digits.'],
        'LRN taken' => [fn (): array => ['lrn' => '136742219999'], 'lrn', 'The LRN has already been taken.'],
        'unknown grade' => [fn (): array => ['grade_level' => '7'], 'grade_level', 'The selected grade level is invalid.'],
        'unknown sex' => [fn (): array => ['sex' => 'x'], 'sex', 'The selected sex is invalid.'],
        'birthdate today' => [fn (): array => ['birthdate' => now()->toDateString()], 'birthdate', 'The birthdate field must be a date before today.'],
        'not a date' => [fn (): array => ['birthdate' => 'soon'], 'birthdate', 'The birthdate field must be a valid date.'],
        'username taken in another case' => [fn (): array => ['username' => 'takenname'], 'username', 'The username has already been taken.'],
        'username with spaces' => [fn (): array => ['username' => 'ana reyes'], 'username', 'The username field must only contain letters, numbers, dashes, and underscores.'],
        'long username' => [fn (): array => ['username' => str_repeat('a', 31)], 'username', 'The username field must not be greater than 30 characters.'],
        'weak password' => [fn (): array => ['password' => 'mangoes', 'password_confirmation' => 'mangoes'], 'password', 'The password field must be at least 8 characters.'],
        'unconfirmed password' => [fn (): array => ['password' => 'mango2026', 'password_confirmation' => 'mango2027'], 'password', 'The password field confirmation does not match.'],
        'archived classroom' => [fn (array $setup): array => ['classroom_ids' => [$setup['archived']->id]], 'classroom_ids.0', 'Choose classrooms of the current school year that are not archived.'],
        'past school year' => [fn (array $setup): array => ['classroom_ids' => [$setup['lastYear']->id]], 'classroom_ids.0', 'Choose classrooms of the current school year that are not archived.'],
        'unknown classroom' => [fn (): array => ['classroom_ids' => [999999]], 'classroom_ids.0', 'Choose classrooms of the current school year that are not archived.'],
        'repeated classroom' => [fn (array $setup): array => ['classroom_ids' => [$setup['sampaguita']->id, $setup['sampaguita']->id]], 'classroom_ids.0', 'Each classroom can be chosen only once.'],
        'not a list' => [fn (): array => ['classroom_ids' => 'all'], 'classroom_ids', 'The classrooms field must be an array.'],
        'other grade' => [fn (array $setup): array => ['classroom_ids' => [$setup['narra']->id]], 'classroom_ids', 'Health 6 – Narra is not a Grade 5 classroom. Choose Grade 5 classrooms only.'],
    ]);

    it('sends guests to the sign-in page', function () {
        $this->post(route('admin.students.store'), ['name' => 'Ana Reyes', 'grade_level' => '5'])->assertRedirectToRoute('login');

        $this->assertDatabaseMissing('users', ['name' => 'Ana Reyes']);
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->post(route('admin.students.store'), ['name' => 'Ana Reyes', 'grade_level' => '5'])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['name' => 'Ana Reyes']);
    })->with($otherRoles);
});

describe('show', function () use ($otherRoles) {
    it('shows the learner’s progress, classrooms, guardians and account actions', function () {
        $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
        $fixture = LearnerProgressFixture::create();
        $mother = User::factory()->parent()->guardianOf($fixture->student, GuardianRelationship::Mother)->create(['name' => 'Ana Navarro']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.show', $fixture->student))
            ->assertOk()
            ->assertViewIs('admin.students.show')
            ->assertViewHas('summary', fn ($summary): bool => $summary->quizzesTaken === 4 && $summary->averageScore === 73.8 && $summary->level === 3)
            ->assertSeeText([$fixture->student->name, 'Weekly quiz scores', 'Progress by subject', 'Recent quiz attempts', 'Missions', 'Rewards'])
            ->assertSeeText([$fixture->healthClassroom->name, 'Math 5 – Mabini', 'Archived'])
            ->assertSeeText(['Ana Navarro', 'Mother'])
            ->assertSee('action="'.route('admin.parents.children.destroy', [$mother, $fixture->student]).'"', false)
            ->assertSee('name="return_to" value="student"', false)
            ->assertSee('action="'.route('admin.users.password.update', $fixture->student).'"', false)
            ->assertSee('action="'.route('admin.users.destroy', $fixture->student).'"', false);
    });

    it('shows the empty states of a new learner', function () {
        $learner = User::factory()->student()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.show', $learner))
            ->assertOk()
            ->assertSeeText(['Not enrolled yet', 'No parent or guardian linked']);
    });

    it('reports accounts that are not learners as missing', function (Closure $account) {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.show', $account()))
            ->assertNotFound();
    })->with([
        'teacher' => fn () => User::factory()->teacher()->create(),
        'parent' => fn () => User::factory()->parent()->create(),
        'unknown id' => fn () => 999999,
    ]);

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.students.show', User::factory()->student()->create()))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.students.show', User::factory()->student()->create()))
            ->assertForbidden();
    })->with($otherRoles);
});

describe('edit', function () use ($classrooms, $otherRoles) {
    it('shows the learner form with current enrollments ticked', function () use ($classrooms) {
        ['sampaguita' => $sampaguita, 'ilangIlang' => $ilangIlang] = $classrooms();
        $learner = User::factory()->student(GradeLevel::Five)->enrolledIn($sampaguita)->create(['username' => 'sofia_navarro']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.edit', $learner))
            ->assertOk()
            ->assertViewIs('admin.students.edit')
            ->assertViewHas('enrolledClassroomIds', [$sampaguita->id])
            ->assertSee('value="sofia_navarro"', false)
            ->assertSee('action="'.route('admin.students.update', $learner).'"', false)
            ->assertSee('action="'.route('admin.users.password.update', $learner).'"', false)
            ->assertMatchesRegularExpression('#<input[^>]*value="'.$sampaguita->id.'"[^>]*\schecked(?=[\s/>])#')
            ->assertDoesntMatchRegularExpression('#<input[^>]*value="'.$ilangIlang->id.'"[^>]*\schecked(?=[\s/>])#');
    });

    it('reports accounts that are not learners as missing', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.edit', User::factory()->parent()->create()))
            ->assertNotFound();
    });

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.students.edit', User::factory()->student()->create()))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.students.edit', User::factory()->student()->create()))
            ->assertForbidden();
    })->with($otherRoles);
});

describe('update', function () use ($classrooms, $otherRoles) {
    $payload = fn (User $learner, array $overrides = []): array => [
        'name' => $learner->name,
        'username' => $learner->username,
        'lrn' => $learner->studentProfile?->lrn,
        'grade_level' => (string) $learner->studentProfile?->grade_level?->value,
        ...$overrides,
    ];

    it('updates the account and the learner profile', function () use ($payload) {
        $learner = User::factory()->student(GradeLevel::Five)->create(['name' => 'Sofia Navarro']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.students.update', $learner), $payload($learner, [
                'name' => 'Sofia  Navarro-Cruz',
                'username' => 'snavarro',
                'lrn' => '136742210123',
                'sex' => 'female',
                'birthdate' => '2016-05-01',
            ]))
            ->assertRedirect(route('admin.students.show', $learner))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', "Changes to Sofia Navarro-Cruz's profile were saved.");

        $learner->refresh();
        expect($learner)->name->toBe('Sofia Navarro-Cruz')->username->toBe('snavarro')
            ->and($learner->studentProfile)->lrn->toBe('136742210123')->sex->toBe(Sex::Female)
            ->and($learner->studentProfile->birthdate->toDateString())->toBe('2016-05-01');
    });

    it('syncs the active school year’s classrooms and keeps archived and past enrollments', function () use ($classrooms, $payload) {
        $setup = $classrooms();
        $learner = User::factory()->student(GradeLevel::Five)->enrolledIn($setup['sampaguita'], $setup['archived'], $setup['lastYear'])->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.students.update', $learner), $payload($learner, ['classroom_ids' => [$setup['ilangIlang']->id]]))
            ->assertSessionHasNoErrors();

        expect($learner->enrolledClassrooms()->pluck('classrooms.id')->sort()->values()->all())
            ->toBe(collect([$setup['ilangIlang']->id, $setup['archived']->id, $setup['lastYear']->id])->sort()->values()->all());
    });

    it('keeps the original enrollment date of a classroom that stays ticked', function () use ($classrooms, $payload) {
        ['sampaguita' => $sampaguita, 'ilangIlang' => $ilangIlang] = $classrooms();
        $this->freezeSecond();
        $learner = User::factory()->student(GradeLevel::Five)->create();
        $learner->enrolledClassrooms()->attach($sampaguita->id, ['enrolled_at' => now()->subMonth()]);
        $this->travel(1)->days();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.students.update', $learner), $payload($learner, ['classroom_ids' => [$sampaguita->id, $ilangIlang->id]]))
            ->assertSessionHasNoErrors();

        $enrolledAt = $learner->enrolledClassrooms()->get()->mapWithKeys(fn (Classroom $classroom): array => [$classroom->id => $classroom->pivot->enrolled_at->toDateTimeString()]);
        expect($enrolledAt[$sampaguita->id])->toBe(now()->subDay()->subMonth()->toDateTimeString())
            ->and($enrolledAt[$ilangIlang->id])->toBe(now()->toDateTimeString());
    });

    it('removes every active-year enrollment when nothing is ticked', function () use ($classrooms, $payload) {
        $setup = $classrooms();
        $learner = User::factory()->student(GradeLevel::Five)->enrolledIn($setup['sampaguita'], $setup['ilangIlang'])->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.students.update', $learner), $payload($learner))
            ->assertSessionHasNoErrors();

        expect($learner->enrolledClassrooms()->exists())->toBeFalse();
    });

    it('refuses a grade change while classrooms of the old grade stay ticked', function () use ($classrooms, $payload) {
        $setup = $classrooms();
        $learner = User::factory()->student(GradeLevel::Five)->enrolledIn($setup['sampaguita'])->create(['name' => 'Sofia Navarro']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.students.update', $learner), $payload($learner, ['grade_level' => '6', 'classroom_ids' => [$setup['sampaguita']->id]]))
            ->assertSessionHasErrors(['grade_level' => "Grade 6 doesn't match Health 5 – Sampaguita, where Sofia Navarro is enrolled. Untick that classroom or keep Grade 5."]);

        expect($learner->studentProfile->refresh()->grade_level)->toBe(GradeLevel::Five);
    });

    it('refuses a grade change while a past classroom of the old grade is not archived', function () use ($classrooms, $payload) {
        $setup = $classrooms();
        $learner = User::factory()->student(GradeLevel::Five)->enrolledIn($setup['lastYear'])->create(['name' => 'Sofia Navarro']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.students.update', $learner), $payload($learner, ['grade_level' => '6']))
            ->assertSessionHasErrors(['grade_level' => 'Sofia Navarro is still enrolled in Health 5 – Rosal (2025–2026), which is not archived. Archive it or remove the learner from it before changing the grade.']);
    });

    it('changes the grade together with the classrooms of the new grade', function () use ($classrooms, $payload) {
        $setup = $classrooms();
        $learner = User::factory()->student(GradeLevel::Five)->enrolledIn($setup['sampaguita'], $setup['archived'])->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.students.update', $learner), $payload($learner, ['grade_level' => '6', 'classroom_ids' => [$setup['narra']->id]]))
            ->assertSessionHasNoErrors();

        expect($learner->studentProfile->refresh()->grade_level)->toBe(GradeLevel::Six)
            ->and($learner->enrolledClassrooms()->pluck('classrooms.id')->sort()->values()->all())
            ->toBe(collect([$setup['narra']->id, $setup['archived']->id])->sort()->values()->all());
    });

    it('rejects a classroom of another grade', function () use ($classrooms, $payload) {
        $setup = $classrooms();
        $learner = User::factory()->student(GradeLevel::Five)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.students.update', $learner), $payload($learner, ['classroom_ids' => [$setup['sampaguita']->id, $setup['narra']->id]]))
            ->assertSessionHasErrors(['classroom_ids' => 'Health 6 – Narra is not a Grade 5 classroom. Choose Grade 5 classrooms only.']);

        expect($learner->enrolledClassrooms()->exists())->toBeFalse();
    });

    it('keeps the learner’s own LRN and username, even in another case', function () use ($payload) {
        $learner = User::factory()->student()->create(['username' => 'sofia_navarro']);
        $learner->studentProfile->update(['lrn' => '136742210001']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.students.update', $learner), $payload($learner, ['username' => 'Sofia_Navarro']))
            ->assertSessionHasNoErrors();

        expect($learner->refresh()->username)->toBe('Sofia_Navarro');
    });

    it('rejects details another learner uses and a blank username', function (array $overrides, string $field, string $message) use ($payload) {
        $other = User::factory()->student()->create(['username' => 'taken']);
        $other->studentProfile->update(['lrn' => '136742219999']);
        $learner = User::factory()->student()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.students.update', $learner), $payload($learner, $overrides))
            ->assertSessionHasErrors([$field => $message]);
    })->with([
        'LRN taken' => [['lrn' => '136742219999'], 'lrn', 'The LRN has already been taken.'],
        'username taken' => [['username' => 'TAKEN'], 'username', 'The username has already been taken.'],
        'blank username' => [['username' => ''], 'username', 'The username field is required.'],
    ]);

    it('creates the learner profile when it is missing', function () {
        $learner = User::factory()->studentWithoutProfile()->create(['username' => 'no_profile']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.students.update', $learner), ['name' => $learner->name, 'username' => 'no_profile', 'grade_level' => '6'])
            ->assertSessionHasNoErrors();

        expect(StudentProfile::query()->where('user_id', $learner->id)->value('grade_level'))->toBe(6);
    });

    it('reports accounts that are not learners as missing', function () {
        $parent = User::factory()->parent()->create(['name' => 'Ana Navarro']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.students.update', $parent), ['name' => 'Changed', 'username' => 'changed', 'grade_level' => '5'])
            ->assertNotFound();

        expect($parent->refresh()->name)->toBe('Ana Navarro');
    });

    it('sends guests to the sign-in page', function () use ($payload) {
        $learner = User::factory()->student()->create();

        $this->put(route('admin.students.update', $learner), $payload($learner, ['name' => 'Changed']))->assertRedirectToRoute('login');

        expect($learner->refresh()->name)->not->toBe('Changed');
    });

    it('forbids other roles', function (UserRole $role) use ($payload) {
        $learner = User::factory()->student()->create();

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->put(route('admin.students.update', $learner), $payload($learner, ['name' => 'Changed']))
            ->assertForbidden();

        expect($learner->refresh()->name)->not->toBe('Changed');
    })->with($otherRoles);
});

describe('destroy', function () use ($classrooms, $otherRoles) {
    it('deletes the learner with enrollments, guardian links and quiz attempts', function () use ($classrooms) {
        ['sampaguita' => $sampaguita] = $classrooms();
        $learner = User::factory()->student(GradeLevel::Five)->enrolledIn($sampaguita)->create(['name' => 'Sofia Navarro']);
        $parent = User::factory()->parent()->guardianOf($learner)->create();
        $attempt = QuizAttempt::factory()->for($learner, 'student')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.students.destroy', $learner))
            ->assertRedirect(route('admin.students.index'))
            ->assertSessionHas('status', 'Sofia Navarro was deleted.');

        $this->assertModelMissing($learner);
        $this->assertModelMissing($attempt);
        $this->assertModelExists($parent);
        $this->assertDatabaseMissing('classroom_student', ['student_id' => $learner->id]);
        $this->assertDatabaseMissing('guardian_student', ['student_id' => $learner->id]);
    });

    it('reports accounts that are not learners as missing', function () {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.students.destroy', $teacher))
            ->assertNotFound();

        $this->assertModelExists($teacher);
    });

    it('sends guests to the sign-in page', function () {
        $learner = User::factory()->student()->create();

        $this->delete(route('admin.students.destroy', $learner))->assertRedirectToRoute('login');

        $this->assertModelExists($learner);
    });

    it('forbids other roles', function (UserRole $role) {
        $learner = User::factory()->student()->create();

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->delete(route('admin.students.destroy', $learner))
            ->assertForbidden();

        $this->assertModelExists($learner);
    })->with($otherRoles);
});
