<?php

use App\Enums\GradeLevel;
use App\Enums\Sex;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentAccounts;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

describe('create', function () {
    it('creates an active learner account with a learner profile and generated credentials', function () {
        $account = app(StudentAccounts::class)->create(['name' => 'Juan Dela Cruz', 'grade_level' => 5]);

        expect($account->enrolled)->toBeFalse();
        expect($account->plainPassword)->toMatch('/^[a-z]{4,}-\d{4}$/');
        expect($account->user->relationLoaded('studentProfile'))->toBeTrue();
        expect($account->user->studentProfile->grade_level)->toBe(GradeLevel::Five);
        expect(Hash::check($account->plainPassword, $account->user->fresh()->password))->toBeTrue();
        $this->assertDatabaseHas('users', [
            'id' => $account->user->id,
            'name' => 'Juan Dela Cruz',
            'username' => 'jdelacruz',
            'email' => null,
            'role' => UserRole::Student->value,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $account->user->id,
            'grade_level' => 5,
            'lrn' => null,
            'sex' => null,
            'birthdate' => null,
            'total_points' => 0,
            'level' => 1,
        ]);
        $this->assertDatabaseCount('classroom_student', 0);
    });

    it('uses the given username, password and profile details', function () {
        $account = app(StudentAccounts::class)->create([
            'name' => 'Ana Reyes',
            'grade_level' => GradeLevel::Six,
            'lrn' => '123456789012',
            'sex' => Sex::Female,
            'birthdate' => '2015-04-09',
            'username' => ' areyes_6 ',
            'password' => 'my-secret-99',
        ]);

        expect($account->plainPassword)->toBe('my-secret-99');
        expect(Hash::check('my-secret-99', $account->user->fresh()->password))->toBeTrue();
        $this->assertDatabaseHas('users', ['id' => $account->user->id, 'username' => 'areyes_6']);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $account->user->id,
            'grade_level' => 6,
            'lrn' => '123456789012',
            'sex' => 'female',
        ]);
        expect($account->user->studentProfile->fresh()->birthdate->toDateString())->toBe('2015-04-09');
    });

    it('accepts the grade level and sex as raw values', function () {
        $account = app(StudentAccounts::class)->create(['name' => 'Ben Cruz', 'grade_level' => '6', 'sex' => 'male']);

        $this->assertDatabaseHas('student_profiles', ['user_id' => $account->user->id, 'grade_level' => 6, 'sex' => 'male']);
    });

    it('stores blank optional details as empty and generates blank credentials', function () {
        $account = app(StudentAccounts::class)->create([
            'name' => 'Liza Soberano',
            'grade_level' => 5,
            'lrn' => '',
            'sex' => '',
            'birthdate' => '',
            'username' => '   ',
            'password' => '',
        ]);

        expect($account->plainPassword)->toMatch('/^[a-z]{4,}-\d{4}$/');
        $this->assertDatabaseHas('users', ['id' => $account->user->id, 'username' => 'lsoberano']);
        $this->assertDatabaseHas('student_profiles', ['user_id' => $account->user->id, 'lrn' => null, 'sex' => null, 'birthdate' => null]);
    });

    it('collapses extra whitespace in the name', function () {
        $account = app(StudentAccounts::class)->create(['name' => "  Maria \t Clara   Santos ", 'grade_level' => 5]);

        expect($account->user->name)->toBe('Maria Clara Santos');
        $this->assertDatabaseHas('users', ['id' => $account->user->id, 'name' => 'Maria Clara Santos', 'username' => 'msantos']);
    });

    it('ignores attributes that are not part of a learner account', function () {
        $account = app(StudentAccounts::class)->create([
            'name' => 'Pedro Penduko',
            'grade_level' => 5,
            'role' => 'admin',
            'is_active' => false,
            'email' => 'pedro@example.com',
            'total_points' => 9000,
            'level' => 19,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $account->user->id,
            'role' => UserRole::Student->value,
            'is_active' => true,
            'email' => null,
        ]);
        $this->assertDatabaseHas('student_profiles', ['user_id' => $account->user->id, 'total_points' => 0, 'level' => 1]);
    });

    it('enrolls the new learner in the given classroom', function () {
        $this->freezeSecond();
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);

        $account = app(StudentAccounts::class)->create(['name' => 'Juan Dela Cruz', 'grade_level' => 5], $classroom);

        expect($account->enrolled)->toBeTrue();
        $this->assertDatabaseHas('classroom_student', [
            'classroom_id' => $classroom->id,
            'student_id' => $account->user->id,
            'enrolled_at' => now()->toDateTimeString(),
        ]);
    });

    it('rolls the account back when the learner profile cannot be saved', function () {
        StudentProfile::factory()->create(['lrn' => '123456789012']);

        expect(fn () => app(StudentAccounts::class)->create(['name' => 'Ana Reyes', 'grade_level' => 5, 'lrn' => '123456789012']))
            ->toThrow(UniqueConstraintViolationException::class);

        $this->assertDatabaseMissing('users', ['name' => 'Ana Reyes']);
        $this->assertDatabaseCount('student_profiles', 1);
    });

    it('rolls the account and profile back when the enrollment fails', function () {
        $classroom = Classroom::factory()->create();
        $classroom->delete();

        expect(fn () => app(StudentAccounts::class)->create(['name' => 'Ana Reyes', 'grade_level' => 5], $classroom))
            ->toThrow(QueryException::class);

        $this->assertDatabaseMissing('users', ['name' => 'Ana Reyes']);
        $this->assertDatabaseCount('student_profiles', 0);
        $this->assertDatabaseCount('classroom_student', 0);
    });
});

describe('enroll', function () {
    it('enrolls a learner who is not in the classroom yet', function () {
        $this->freezeSecond();
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->create();

        $enrolled = app(StudentAccounts::class)->enroll($student, $classroom);

        expect($enrolled)->toBeTrue();
        $this->assertDatabaseHas('classroom_student', [
            'classroom_id' => $classroom->id,
            'student_id' => $student->id,
            'enrolled_at' => now()->toDateTimeString(),
        ]);
    });

    it('does not enroll a learner twice or move the original enrollment date', function () {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->create();
        $classroom->students()->attach($student, ['enrolled_at' => '2026-06-16 08:00:00']);

        $enrolled = app(StudentAccounts::class)->enroll($student, $classroom);

        expect($enrolled)->toBeFalse();
        $this->assertDatabaseCount('classroom_student', 1);
        $this->assertDatabaseHas('classroom_student', ['student_id' => $student->id, 'enrolled_at' => '2026-06-16 08:00:00']);
    });
});

describe('generateUsername', function () {
    it('builds a lowercase ASCII username from the initial and the surname', function (string $name, string $username) {
        expect(app(StudentAccounts::class)->generateUsername($name))->toBe($username);
    })->with([
        'a surname with a particle' => ['Juan Dela Cruz', 'jdelacruz'],
        'a middle initial and a suffix' => ['José P. Rizal Jr.', 'jrizal'],
        'accented letters' => ['Ñiño Peña', 'npena'],
        'extra spaces and a middle name' => ['  Maria   Clara  Santos ', 'msantos'],
        'an abbreviation and two particles' => ['Ma. Teresa de los Reyes', 'mdelosreyes'],
        'an apostrophe and a hyphen' => ["Zoë O'Brien-Santos", 'zobriensantos'],
        'a particle right after the given name' => ['Ana San Pedro', 'asanpedro'],
        'surname first with a comma' => ['Dela Cruz, Juan Miguel', 'jdelacruz'],
        'surname first with a suffix' => ['Santos III, Pedro', 'psantos'],
        'a single name' => ['Cher', 'cher'],
        'a very long surname' => ['Juan Maximilianoferdinandobartholomew', 'jmaximilianoferdinandobartholo'],
        'no transliterable letters' => ['李小龍', 'learner'],
        'only punctuation' => ['!!! ...', 'learner'],
    ]);

    it('adds the smallest free numeric suffix to a taken username', function () {
        User::factory()->studentWithoutProfile()->create(['username' => 'jdelacruz']);
        User::factory()->studentWithoutProfile()->create(['username' => 'jdelacruz3']);

        expect(app(StudentAccounts::class)->generateUsername('Juan Dela Cruz'))->toBe('jdelacruz2');
    });

    it('treats a username that differs only in letter case as taken', function () {
        User::factory()->studentWithoutProfile()->create(['username' => 'JDelaCruz']);

        expect(app(StudentAccounts::class)->generateUsername('Juan Dela Cruz'))->toBe('jdelacruz2');
    });

    it('does not treat a longer username with the same beginning as taken', function () {
        User::factory()->studentWithoutProfile()->create(['username' => 'jdelacruzz']);

        expect(app(StudentAccounts::class)->generateUsername('Juan Dela Cruz'))->toBe('jdelacruz');
    });

    it('shortens the base so a suffixed username stays within 30 characters', function () {
        User::factory()->studentWithoutProfile()->create(['username' => 'jmaximilianoferdinandobartholo']);
        User::factory()->studentWithoutProfile()->create(['username' => 'jmaximilianoferdinandobarthol2']);

        expect(app(StudentAccounts::class)->generateUsername('Juan Maximilianoferdinandobartholomew'))
            ->toBe('jmaximilianoferdinandobarthol3');
    });

    it('numbers the fallback username too', function () {
        User::factory()->studentWithoutProfile()->create(['username' => 'learner']);

        expect(app(StudentAccounts::class)->generateUsername('李小龍'))->toBe('learner2');
    });
});

describe('generatePassword', function () {
    it('generates a food word, a dash and four digits', function () {
        $accounts = app(StudentAccounts::class);

        $passwords = collect(range(1, 200))->map(fn (): string => $accounts->generatePassword());

        expect($passwords->all())->each->toMatch('/^[a-z]{4,}-\d{4}$/');
        expect($passwords->map(fn (string $password): string => Str::before($password, '-'))->unique()->all())
            ->each->toBeIn(StudentAccounts::PASSWORD_WORDS);
        expect($passwords->unique()->count())->toBeGreaterThan(1);
    });

    it('draws from distinct lowercase words of at least four letters', function () {
        expect(StudentAccounts::PASSWORD_WORDS)->each->toMatch('/^[a-z]{4,}$/');
        expect(StudentAccounts::PASSWORD_WORDS)->toHaveCount(count(array_unique(StudentAccounts::PASSWORD_WORDS)));
    });
});

describe('resetPassword', function () {
    it('replaces the password with a generated one and signs out remembered sessions', function () {
        $student = User::factory()->student()->create(['remember_token' => 'old-token']);

        $password = app(StudentAccounts::class)->resetPassword($student);

        $student->refresh();
        expect($password)->toMatch('/^[a-z]{4,}-\d{4}$/');
        expect(Hash::check($password, $student->password))->toBeTrue();
        expect(Hash::check('password', $student->password))->toBeFalse();
        expect($student->remember_token)->not->toBe('old-token')->toHaveLength(60);
    });

    it('refuses accounts that are not learner accounts', function (UserRole $role) {
        $user = User::factory()->state(['role' => $role])->create();

        expect(fn () => app(StudentAccounts::class)->resetPassword($user))
            ->toThrow(InvalidArgumentException::class, 'Only learner accounts can get a generated learner password.');

        expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    })->with([
        'administrator' => UserRole::Admin,
        'teacher' => UserRole::Teacher,
        'parent' => UserRole::Parent,
    ]);
});
