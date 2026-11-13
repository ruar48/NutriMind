<?php

use App\Enums\GradeLevel;
use App\Enums\GuardianRelationship;
use App\Enums\Sex;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Services\StudentImport\StudentImporter;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Services\StudentImport\Support\StudentImportCsv;

it('creates the learner accounts with their profiles and enrolls them in the classroom', function () {
    $this->freezeSecond();
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $preview = StudentImportCsv::preview([
        StudentImportCsv::row(['lrn' => '136742210001', 'sex' => 'M', 'birthdate' => '06/15/2015', 'username' => 'juan_dc']),
        StudentImportCsv::row(['first_name' => 'Ana Marie', 'last_name' => 'Santos', 'grade_level' => '', 'sex' => 'female', 'birthdate' => '']),
    ], $classroom);

    $result = app(StudentImporter::class)->import($preview, $classroom);

    $juan = User::query()->where('username', 'juan_dc')->firstOrFail();
    $ana = User::query()->where('username', 'asantos')->firstOrFail();

    expect($result)
        ->created->toBe(2)
        ->enrolled->toBe(0)
        ->parentsCreated->toBe(0)
        ->guardiansLinked->toBe(0)
        ->and($juan)
        ->name->toBe('Juan Dela Cruz')
        ->role->toBe(UserRole::Student)
        ->is_active->toBeTrue()
        ->email->toBeNull()
        ->and($juan->studentProfile)
        ->lrn->toBe('136742210001')
        ->grade_level->toBe(GradeLevel::Five)
        ->sex->toBe(Sex::Male)
        ->and($juan->studentProfile->birthdate->toDateString())->toBe('2015-06-15')
        ->and($ana->studentProfile)
        ->lrn->toBeNull()
        ->grade_level->toBe(GradeLevel::Five)
        ->birthdate->toBeNull()
        ->and($classroom->students()->pluck('users.id')->sort()->values()->all())->toBe(collect([$juan->id, $ana->id])->sort()->values()->all());
    $this->assertDatabaseHas('classroom_student', ['classroom_id' => $classroom->id, 'student_id' => $juan->id, 'enrolled_at' => now()->toDateTimeString()]);
});

it('hands out each generated password once, in row order', function () {
    $preview = StudentImportCsv::preview([
        StudentImportCsv::row(['first_name' => 'Juan']),
        StudentImportCsv::row(['first_name' => 'Ana', 'last_name' => 'Santos', 'username' => 'ana_s']),
    ]);

    $result = app(StudentImporter::class)->import($preview, null);

    expect(array_column($result->credentials, 'username'))->toBe(['jdelacruz', 'ana_s'])
        ->and(array_column($result->credentials, 'role'))->toBe([UserRole::Student, UserRole::Student]);

    foreach ($result->credentials as $credential) {
        expect($credential['password'])->toMatch('/^[a-z]+-\d{4}$/')
            ->and(Hash::check($credential['password'], User::query()->where('username', $credential['username'])->value('password')))->toBeTrue();
    }
});

it('creates rows with a username from the file before generating usernames', function () {
    $preview = StudentImportCsv::preview([
        StudentImportCsv::row(['first_name' => 'Juan']),
        StudentImportCsv::row(['first_name' => 'Jose', 'username' => 'jdelacruz']),
    ]);

    app(StudentImporter::class)->import($preview, null);

    expect(User::query()->where('username', 'jdelacruz')->value('name'))->toBe('Jose Dela Cruz')
        ->and(User::query()->where('username', 'jdelacruz2')->value('name'))->toBe('Juan Dela Cruz');
});

it('builds generated usernames from the surname column', function () {
    $preview = StudentImportCsv::preview([StudentImportCsv::row(['first_name' => 'Ma. Teresa', 'last_name' => 'Santos Reyes'])]);

    app(StudentImporter::class)->import($preview, null);

    expect(User::query()->where('name', 'Ma. Teresa Santos Reyes')->value('username'))->toBe('msantosreyes');
});

it('generates usernames from a single name column', function () {
    $preview = StudentImportCsv::preview([['name' => 'Juan Miguel Dela Cruz', 'grade_level' => '6']], headers: ['name', 'grade_level']);

    app(StudentImporter::class)->import($preview, null);

    expect(User::query()->where('name', 'Juan Miguel Dela Cruz')->value('username'))->toBe('jdelacruz');
});

it('enrolls existing learners and leaves skipped and invalid rows alone', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $existing = StudentImportCsv::learner('136742210001');
    $enrolled = StudentImportCsv::learner('136742210002');
    $classroom->students()->attach($enrolled);
    $preview = StudentImportCsv::preview([
        StudentImportCsv::row(['lrn' => '136742210001']),
        StudentImportCsv::row(['lrn' => '136742210002']),
        StudentImportCsv::row(['first_name' => 'Ana', 'lrn' => 'invalid']),
    ], $classroom);
    $usersBefore = User::query()->count();

    $result = app(StudentImporter::class)->import($preview, $classroom);

    expect($result)
        ->created->toBe(0)
        ->enrolled->toBe(1)
        ->skipped->toBe(1)
        ->failed->toBe(1)
        ->credentials->toBe([])
        ->and(User::query()->count())->toBe($usersBefore)
        ->and($classroom->students()->whereKey($existing->id)->exists())->toBeTrue();
});

it('creates each new parent account once and links every learner of that email', function () {
    $preview = StudentImportCsv::preview([
        StudentImportCsv::row(['guardian_name' => 'Maria Dela Cruz', 'guardian_email' => 'Maria@Example.com', 'guardian_relationship' => 'mother']),
        StudentImportCsv::row(['first_name' => 'Pedro', 'grade_level' => '6', 'guardian_email' => 'maria@example.com', 'guardian_relationship' => 'mother']),
    ]);

    $result = app(StudentImporter::class)->import($preview, null);

    $parent = User::query()->where('email', 'maria@example.com')->firstOrFail();

    expect($result)
        ->parentsCreated->toBe(1)
        ->guardiansLinked->toBe(2)
        ->and($parent)
        ->name->toBe('Maria Dela Cruz')
        ->role->toBe(UserRole::Parent)
        ->is_active->toBeTrue()
        ->and($parent->linkedStudents()->orderBy('users.id')->pluck('name')->all())->toBe(['Juan Dela Cruz', 'Pedro Dela Cruz'])
        ->and($parent->linkedStudents->every(fn (User $child): bool => $child->pivot->relationship === GuardianRelationship::Mother))->toBeTrue()
        ->and(array_column($result->credentials, 'username'))->toBe(['jdelacruz', 'maria@example.com', 'pdelacruz'])
        ->and($result->credentials[1]['role'])->toBe(UserRole::Parent)
        ->and($result->credentials[1]['password'])->toMatch('/^[A-Za-z2-9]{4}-[A-Za-z2-9]{4}-[A-Za-z2-9]{4}$/')
        ->and(Hash::check($result->credentials[1]['password'], $parent->password))->toBeTrue();
});

it('links an existing parent account without changing it', function () {
    $parent = User::factory()->parent()->create(['email' => 'maria@example.com', 'name' => 'Maria Dela Cruz']);
    $passwordBefore = $parent->password;
    $preview = StudentImportCsv::preview([StudentImportCsv::row(['guardian_name' => 'Someone Else', 'guardian_email' => 'maria@example.com', 'guardian_relationship' => 'grandparent'])]);

    $result = app(StudentImporter::class)->import($preview, null);

    $child = $parent->linkedStudents()->sole();

    expect($result)->parentsCreated->toBe(0)->guardiansLinked->toBe(1)
        ->and($child->name)->toBe('Juan Dela Cruz')
        ->and($child->pivot->relationship)->toBe(GuardianRelationship::Grandparent)
        ->and($parent->refresh())->name->toBe('Maria Dela Cruz')->password->toBe($passwordBefore)
        ->and(array_column($result->credentials, 'username'))->toBe(['jdelacruz']);
});

it('links a parent to an enrolled existing learner', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $learner = StudentImportCsv::learner('136742210001');
    $preview = StudentImportCsv::preview([StudentImportCsv::row([
        'lrn' => '136742210001',
        'guardian_name' => 'Maria Dela Cruz',
        'guardian_email' => 'maria@example.com',
        'guardian_relationship' => 'mother',
    ])], $classroom);

    $result = app(StudentImporter::class)->import($preview, $classroom);

    expect($result)->enrolled->toBe(1)->parentsCreated->toBe(1)->guardiansLinked->toBe(1)
        ->and($learner->guardians()->sole()->email)->toBe('maria@example.com');
});

it('does not link an already linked guardian twice', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $learner = StudentImportCsv::learner('136742210001');
    User::factory()->parent()->guardianOf($learner, GuardianRelationship::Mother)->create(['email' => 'maria@example.com']);
    $preview = StudentImportCsv::preview([StudentImportCsv::row(['lrn' => '136742210001', 'guardian_email' => 'maria@example.com', 'guardian_relationship' => 'father'])], $classroom);

    $result = app(StudentImporter::class)->import($preview, $classroom);

    expect($result->guardiansLinked)->toBe(0)
        ->and($learner->guardians()->sole()->pivot->relationship)->toBe(GuardianRelationship::Mother);
});

it('rolls everything back when one account cannot be saved', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $preview = StudentImportCsv::preview([
        StudentImportCsv::row(['first_name' => 'Ana', 'lrn' => '136742210001', 'guardian_name' => 'Maria', 'guardian_email' => 'maria@example.com']),
        StudentImportCsv::row(['first_name' => 'Ben', 'lrn' => '136742210002']),
    ], $classroom);
    StudentImportCsv::learner('136742210002');
    $usersBefore = User::query()->count();

    expect(fn () => app(StudentImporter::class)->import($preview, $classroom))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class)
        ->and(User::query()->count())->toBe($usersBefore)
        ->and($classroom->students()->count())->toBe(0)
        ->and(User::query()->where('email', 'maria@example.com')->exists())->toBeFalse();
});

it('generates strong, readable parent passwords', function () {
    $passwords = collect(range(1, 50))->map(fn (): string => app(StudentImporter::class)->generateParentPassword());

    expect($passwords->unique())->toHaveCount(50);

    foreach ($passwords as $password) {
        expect($password)->toMatch('/^[A-HJ-NP-Za-km-z2-9]{4}-[A-HJ-NP-Za-km-z2-9]{4}-[A-HJ-NP-Za-km-z2-9]{4}$/')
            ->toMatch('/\d/')
            ->toMatch('/[A-Za-z]/');
    }
});
