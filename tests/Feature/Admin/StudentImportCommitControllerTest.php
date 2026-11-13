<?php

use App\Enums\GradeLevel;
use App\Enums\GuardianRelationship;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Services\StudentImport\PendingImports;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Services\StudentImport\Support\StudentImportCsv;

it('imports the valid rows into the chosen classroom and keeps the sign-in details for one download', function () {
    Storage::fake('local');
    $this->freezeSecond();
    $admin = User::factory()->admin()->create();
    $classroom = Classroom::factory()->create(['name' => 'Health 5 – Sampaguita', 'grade_level' => GradeLevel::Five]);
    $existing = StudentImportCsv::learner('136742210001');
    $pending = StudentImportCsv::pending($admin, [
        StudentImportCsv::row(['lrn' => '136742210009', 'guardian_name' => 'Maria Dela Cruz', 'guardian_email' => 'maria@example.com', 'guardian_relationship' => 'mother']),
        StudentImportCsv::row(['first_name' => 'Pedro', 'lrn' => '136742210001']),
        StudentImportCsv::row(['first_name' => 'Ana', 'lrn' => 'bad']),
    ], $classroom);

    $response = $this->actingAs($admin)->post(route('admin.students.import.commit', $pending->id));

    $juan = User::query()->where('name', 'Juan Dela Cruz')->sole();
    $parent = User::query()->where('email', 'maria@example.com')->sole();
    $credentials = session('credentials');
    $response->assertRedirectToRoute('admin.students.import.create')
        ->assertSessionHas('status', 'Imported 2 learners into Health 5 – Sampaguita: 1 new account and 1 existing learner enrolled. Created 1 parent account.')
        ->assertSessionHas('importResult', [
            'created' => 1, 'enrolled' => 1, 'skipped' => 0, 'failed' => 1,
            'parentsCreated' => 1, 'guardiansLinked' => 1, 'accounts' => 2, 'classroom' => 'Health 5 – Sampaguita',
        ]);
    expect($juan->role)->toBe(UserRole::Student)
        ->and($juan->studentProfile->lrn)->toBe('136742210009')
        ->and($classroom->students()->pluck('users.id')->sort()->values()->all())->toBe(collect([$juan->id, $existing->id])->sort()->values()->all())
        ->and($parent->role)->toBe(UserRole::Parent)
        ->and($parent->linkedStudents()->sole()->is($juan))->toBeTrue()
        ->and($parent->linkedStudents()->sole()->pivot->relationship)->toBe(GuardianRelationship::Mother)
        ->and(User::query()->where('name', 'Ana Dela Cruz')->exists())->toBeFalse()
        ->and(array_column($credentials, 'username'))->toBe(['jdelacruz', 'maria@example.com'])
        ->and(Hash::check($credentials[0]['password'], $juan->password))->toBeTrue()
        ->and(Hash::check($credentials[1]['password'], $parent->password))->toBeTrue()
        ->and(session('imports.'.$pending->id))->toBeNull()
        ->and(session(PendingImports::CREDENTIALS_SESSION_KEY))->toBeString();
    Storage::disk('local')->assertMissing($pending->path);
});

it('creates accounts without enrollments when no classroom was chosen', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $pending = StudentImportCsv::pending($admin, [
        StudentImportCsv::row(['grade_level' => '6']),
        StudentImportCsv::row(['first_name' => 'Ana', 'grade_level' => '5']),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.students.import.commit', $pending->id))
        ->assertRedirectToRoute('admin.students.import.create')
        ->assertSessionHas('status', 'Imported 2 learners: 2 new accounts.');

    expect(User::query()->ofRole(UserRole::Student)->count())->toBe(2)
        ->and(DB::table('classroom_student')->count())->toBe(0);
});

it('returns 404 for a second commit of the same import', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $pending = StudentImportCsv::pending($admin, [StudentImportCsv::row()]);
    $this->actingAs($admin)->post(route('admin.students.import.commit', $pending->id))->assertRedirect();

    $this->post(route('admin.students.import.commit', $pending->id))->assertNotFound();

    expect(User::query()->ofRole(UserRole::Student)->count())->toBe(1);
});

it('returns 404 while the same import is being committed', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $pending = StudentImportCsv::pending($admin, [StudentImportCsv::row()]);
    $lock = Cache::lock('student-import:'.$pending->id, 60);
    $lock->get();

    $this->actingAs($admin)->post(route('admin.students.import.commit', $pending->id))->assertNotFound();

    $lock->release();
    expect(User::query()->ofRole(UserRole::Student)->exists())->toBeFalse();
    Storage::disk('local')->assertExists($pending->path);
});

it('returns 404 when the uploaded file is already gone', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $pending = StudentImportCsv::pending($admin, [StudentImportCsv::row()]);
    Storage::disk('local')->delete($pending->path);

    $this->actingAs($admin)->post(route('admin.students.import.commit', $pending->id))->assertNotFound();

    expect(session('imports.'.$pending->id))->toBeNull();
});

it('returns 404 for tokens that are unknown, malformed or someone else\'s', function (Closure $token) {
    Storage::fake('local');
    $owner = User::factory()->admin()->create();
    $pending = StudentImportCsv::pending($owner, [StudentImportCsv::row()]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.students.import.commit', $token($pending)))
        ->assertNotFound();

    expect(User::query()->ofRole(UserRole::Student)->exists())->toBeFalse();
})->with([
    'another administrator\'s token' => [fn ($pending): string => $pending->id],
    'an unknown token' => [fn (): string => (string) Str::uuid()],
    'a malformed token' => [fn (): string => 'imports'],
]);

it('keeps the preview when nothing can be imported', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    StudentImportCsv::learner('136742210001');
    $pending = StudentImportCsv::pending($admin, [
        StudentImportCsv::row(['lrn' => '136742210001']),
        StudentImportCsv::row(['first_name' => 'Ana', 'sex' => 'unknown']),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.students.import.commit', $pending->id))
        ->assertRedirectToRoute('admin.students.import.show', $pending->id)
        ->assertSessionHas('error', 'Nothing was imported: every row is skipped or has errors. Fix the file and upload it again.');

    expect(User::query()->ofRole(UserRole::Student)->count())->toBe(1)
        ->and(session('imports.'.$pending->id))->not->toBeNull();
    Storage::disk('local')->assertExists($pending->path);
});

it('re-validates the file against the current data when committing', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $pending = StudentImportCsv::pending($admin, [
        StudentImportCsv::row(['username' => 'juan_dc']),
        StudentImportCsv::row(['first_name' => 'Ana', 'username' => 'ana_s']),
    ]);
    User::factory()->teacher()->create(['username' => 'juan_dc']);

    $this->actingAs($admin)
        ->post(route('admin.students.import.commit', $pending->id))
        ->assertSessionHas('importResult', fn (array $result): bool => $result['created'] === 1 && $result['failed'] === 1);

    expect(User::query()->ofRole(UserRole::Student)->pluck('username')->all())->toBe(['ana_s']);
});

it('starts over when the chosen classroom was archived after the upload', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $pending = StudentImportCsv::pending($admin, [StudentImportCsv::row()], $classroom);
    $classroom->forceFill(['archived_at' => now()])->save();

    $this->actingAs($admin)
        ->post(route('admin.students.import.commit', $pending->id))
        ->assertRedirectToRoute('admin.students.import.create')
        ->assertSessionHas('error', 'The classroom chosen for this import was archived or deleted. Upload the file again.');

    expect(User::query()->ofRole(UserRole::Student)->exists())->toBeFalse();
});

it('sends guests to the sign-in page', function () {
    $this->post(route('admin.students.import.commit', (string) Str::uuid()))->assertRedirectToRoute('login');
});

it('forbids other roles', function (UserRole $role) {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $pending = StudentImportCsv::pending($admin, [StudentImportCsv::row()]);

    $this->actingAs(User::factory()->{$role->value}()->create())
        ->post(route('admin.students.import.commit', $pending->id))
        ->assertForbidden();

    expect(User::query()->ofRole(UserRole::Student)->exists())->toBeFalse();
})->with([UserRole::Teacher, UserRole::Parent]);
