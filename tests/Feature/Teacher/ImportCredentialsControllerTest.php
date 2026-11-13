<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Services\RolePermissions;
use App\Services\StudentImport\PendingImports;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Services\StudentImport\Support\StudentImportCsv;

it('downloads the sign-in details of the teacher\'s last import once', function () {
    Storage::fake('local');
    $this->travelTo('2026-11-13 10:00:00');
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $pending = StudentImportCsv::pending($classroom->teacher, [StudentImportCsv::row()], $classroom);
    $this->actingAs($classroom->teacher)->post(route('teacher.classrooms.import.commit', [$classroom, $pending->id]))->assertRedirect();
    $password = session('credentials')[0]['password'];

    $response = $this->get(route('teacher.imports.credentials'));

    $response->assertOk()->assertDownload('learner-sign-in-details-2026-11-13.csv');
    expect($response->streamedContent())->toContain("\"Juan Dela Cruz\",Learner,jdelacruz,{$password},\"NutriMind Game\"\r\n");

    $this->from(route('teacher.classrooms.import.create', $classroom))
        ->get(route('teacher.imports.credentials'))
        ->assertRedirectToRoute('teacher.classrooms.import.create', $classroom)
        ->assertSessionHas('error', PendingImports::NO_CREDENTIALS_MESSAGE);
});

it('falls back to the classroom list when there is nothing to download', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('teacher.imports.credentials'))
        ->assertRedirectToRoute('teacher.classrooms.index')
        ->assertSessionHas('error', PendingImports::NO_CREDENTIALS_MESSAGE);
});

it('never hands another teacher\'s sign-in details out', function () {
    $owner = User::factory()->teacher()->create();
    app(PendingImports::class)->putCredentials($owner, [
        ['name' => 'Juan Dela Cruz', 'username' => 'jdelacruz', 'password' => 'mango-4821', 'role' => UserRole::Student],
    ]);

    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('teacher.imports.credentials'))
        ->assertRedirectToRoute('teacher.classrooms.index')
        ->assertSessionHas('error', PendingImports::NO_CREDENTIALS_MESSAGE);
});

it('forbids teachers whose permission was revoked', function () {
    $teacher = User::factory()->teacher()->create();
    app(PendingImports::class)->putCredentials($teacher, [
        ['name' => 'Juan Dela Cruz', 'username' => 'jdelacruz', 'password' => 'mango-4821', 'role' => UserRole::Student],
    ]);
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ImportStudents);

    $this->actingAs($teacher)->get(route('teacher.imports.credentials'))->assertForbidden();

    expect(session()->has(PendingImports::CREDENTIALS_SESSION_KEY))->toBeTrue();
});

it('sends guests to the sign-in page', function () {
    $this->get(route('teacher.imports.credentials'))->assertRedirectToRoute('login');
});

it('forbids other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('teacher.imports.credentials'))
        ->assertForbidden();
})->with([UserRole::Admin, UserRole::Parent]);
