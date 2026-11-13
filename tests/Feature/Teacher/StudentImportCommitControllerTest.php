<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Policies\ClassroomPolicy;
use App\Services\RolePermissions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Services\StudentImport\Support\StudentImportCsv;

it('imports the class list into the classroom and shows the sign-in details once', function () {
    Storage::fake('local');
    $classroom = Classroom::factory()->create(['name' => 'Health 5 – Sampaguita', 'grade_level' => GradeLevel::Five]);
    $existing = StudentImportCsv::learner('136742210001');
    $pending = StudentImportCsv::pending($classroom->teacher, [
        StudentImportCsv::row(['grade_level' => '', 'guardian_name' => 'Maria Dela Cruz', 'guardian_email' => 'maria@example.com', 'guardian_relationship' => 'mother']),
        StudentImportCsv::row(['first_name' => 'Pedro', 'lrn' => '136742210001']),
    ], $classroom);

    $response = $this->actingAs($classroom->teacher)->post(route('teacher.classrooms.import.commit', [$classroom, $pending->id]));

    $juan = User::query()->where('name', 'Juan Dela Cruz')->sole();
    $response->assertRedirectToRoute('teacher.classrooms.import.create', $classroom)
        ->assertSessionHas('status', 'Imported 2 learners into Health 5 – Sampaguita: 1 new account and 1 existing learner enrolled. Created 1 parent account.')
        ->assertSessionHas('credentials', fn (array $credentials): bool => array_column($credentials, 'username') === ['jdelacruz', 'maria@example.com']);
    expect($juan->studentProfile->grade_level)->toBe(GradeLevel::Five)
        ->and($classroom->students()->pluck('users.id')->sort()->values()->all())->toBe(collect([$juan->id, $existing->id])->sort()->values()->all())
        ->and(User::query()->where('email', 'maria@example.com')->sole()->linkedStudents()->sole()->is($juan))->toBeTrue()
        ->and(session('imports.'.$pending->id))->toBeNull();
    Storage::disk('local')->assertMissing($pending->path);

    $this->get(route('teacher.classrooms.import.create', $classroom))
        ->assertOk()
        ->assertSee('data-credentials-alert="2"', false)
        ->assertSeeText('jdelacruz');
});

it('returns 404 for a second commit of the same import', function () {
    Storage::fake('local');
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $pending = StudentImportCsv::pending($classroom->teacher, [StudentImportCsv::row()], $classroom);
    $this->actingAs($classroom->teacher)->post(route('teacher.classrooms.import.commit', [$classroom, $pending->id]))->assertRedirect();

    $this->post(route('teacher.classrooms.import.commit', [$classroom, $pending->id]))->assertNotFound();

    expect($classroom->students()->count())->toBe(1);
});

it('returns 404 for the token of another classroom', function () {
    Storage::fake('local');
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $otherClassroom = Classroom::factory()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create(['grade_level' => GradeLevel::Five]);
    $pending = StudentImportCsv::pending($classroom->teacher, [StudentImportCsv::row()], $classroom);

    $this->actingAs($classroom->teacher)
        ->post(route('teacher.classrooms.import.commit', [$otherClassroom, $pending->id]))
        ->assertNotFound();

    expect(User::query()->ofRole(UserRole::Student)->exists())->toBeFalse();
});

it('returns 404 for another teacher\'s token or classroom', function () {
    Storage::fake('local');
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $pending = StudentImportCsv::pending($classroom->teacher, [StudentImportCsv::row()], $classroom);
    $otherClassroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);

    $this->actingAs($otherClassroom->teacher)
        ->post(route('teacher.classrooms.import.commit', [$otherClassroom, $pending->id]))
        ->assertNotFound();
    $this->post(route('teacher.classrooms.import.commit', [$classroom, $pending->id]))->assertNotFound();

    expect(User::query()->ofRole(UserRole::Student)->exists())->toBeFalse();
});

it('keeps the preview when nothing can be imported', function () {
    Storage::fake('local');
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $pending = StudentImportCsv::pending($classroom->teacher, [StudentImportCsv::row(['grade_level' => '6'])], $classroom);

    $this->actingAs($classroom->teacher)
        ->post(route('teacher.classrooms.import.commit', [$classroom, $pending->id]))
        ->assertRedirectToRoute('teacher.classrooms.import.show', [$classroom, $pending->id])
        ->assertSessionHas('error', 'Nothing was imported: every row is skipped or has errors. Fix the file and upload it again.');

    Storage::disk('local')->assertExists($pending->path);
});

it('starts over when the uploaded file can no longer be read', function () {
    Storage::fake('local');
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $pending = StudentImportCsv::pending($classroom->teacher, [StudentImportCsv::row()], $classroom);
    Storage::disk('local')->put($pending->path, '');

    $this->actingAs($classroom->teacher)
        ->post(route('teacher.classrooms.import.commit', [$classroom, $pending->id]))
        ->assertRedirectToRoute('teacher.classrooms.import.create', $classroom)
        ->assertSessionHas('error', 'The uploaded file can no longer be read. Upload it again.');

    Storage::disk('local')->assertMissing($pending->path);
});

it('forbids committing into a classroom archived after the upload', function () {
    Storage::fake('local');
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $pending = StudentImportCsv::pending($classroom->teacher, [StudentImportCsv::row()], $classroom);
    $classroom->forceFill(['archived_at' => now()])->save();

    $this->actingAs($classroom->teacher)
        ->post(route('teacher.classrooms.import.commit', [$classroom, $pending->id]))
        ->assertForbidden()
        ->assertSeeText(ClassroomPolicy::ARCHIVED_MESSAGE);

    expect(User::query()->ofRole(UserRole::Student)->exists())->toBeFalse();
});

it('forbids committing when the administrator revoked the permission', function () {
    Storage::fake('local');
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $pending = StudentImportCsv::pending($classroom->teacher, [StudentImportCsv::row()], $classroom);
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ImportStudents);

    $this->actingAs($classroom->teacher)
        ->post(route('teacher.classrooms.import.commit', [$classroom, $pending->id]))
        ->assertForbidden();

    expect(User::query()->ofRole(UserRole::Student)->exists())->toBeFalse();
});

it('forbids committing into a classroom whose subject was revoked', function () {
    Storage::fake('local');
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $pending = StudentImportCsv::pending($classroom->teacher, [StudentImportCsv::row()], $classroom);
    $classroom->teacher->assignedSubjects()->detach($classroom->subject_id);

    $this->actingAs($classroom->teacher->refresh())
        ->post(route('teacher.classrooms.import.commit', [$classroom, $pending->id]))
        ->assertForbidden()
        ->assertSeeText(ClassroomPolicy::SUBJECT_REVOKED_MESSAGE);
});

it('sends guests to the sign-in page', function () {
    $this->post(route('teacher.classrooms.import.commit', [Classroom::factory()->create(), (string) Str::uuid()]))
        ->assertRedirectToRoute('login');
});

it('forbids other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->post(route('teacher.classrooms.import.commit', [Classroom::factory()->create(), (string) Str::uuid()]))
        ->assertForbidden();
})->with([UserRole::Admin, UserRole::Parent]);
