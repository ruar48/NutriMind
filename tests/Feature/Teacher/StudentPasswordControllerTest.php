<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Policies\ClassroomPolicy;
use App\Services\RolePermissions;
use Illuminate\Support\Facades\Hash;

it('gives an enrolled learner a new generated password and shows it once', function () {
    $classroom = Classroom::factory()->create();
    $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create(['name' => 'Ana Reyes', 'username' => 'ana_reyes']);
    $learner->forceFill(['remember_token' => 'old-token'])->save();

    $response = $this->actingAs($classroom->teacher)
        ->put(route('teacher.classrooms.students.password.update', [$classroom, $learner]));

    $credentials = session('credentials');
    $response->assertRedirectToRoute('teacher.classrooms.show', $classroom)
        ->assertSessionHas('status', 'Ana Reyes’s password was reset. Share the new sign-in details now — they are shown only once.');
    $learner->refresh();
    expect($credentials)->toHaveCount(1)
        ->and($credentials[0]['name'])->toBe('Ana Reyes')
        ->and($credentials[0]['username'])->toBe('ana_reyes')
        ->and($credentials[0]['password'])->toMatch('/^[a-z]+-\d{4}$/')
        ->and(Hash::check($credentials[0]['password'], $learner->password))->toBeTrue()
        ->and(Hash::check('password', $learner->password))->toBeFalse()
        ->and($learner->remember_token)->not->toBe('old-token');
});

it('reports a learner who is not enrolled in the classroom as missing', function () {
    $classroom = Classroom::factory()->create();
    $learner = User::factory()->student($classroom->grade_level)->create();
    $originalHash = $learner->password;

    $this->actingAs($classroom->teacher)
        ->put(route('teacher.classrooms.students.password.update', [$classroom, $learner]))
        ->assertNotFound();

    expect($learner->refresh()->password)->toBe($originalHash);
});

it('reports accounts that are not learners as missing', function () {
    $classroom = Classroom::factory()->create();
    $teacher = User::factory()->teacher()->create();
    $originalHash = $teacher->password;

    $this->actingAs($classroom->teacher)
        ->put(route('teacher.classrooms.students.password.update', [$classroom, $teacher]))
        ->assertNotFound();

    expect($teacher->refresh()->password)->toBe($originalHash);
});

it('refuses archived classrooms', function () {
    $classroom = Classroom::factory()->archived()->create();
    $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();
    $originalHash = $learner->password;

    $this->actingAs($classroom->teacher)
        ->put(route('teacher.classrooms.students.password.update', [$classroom, $learner]))
        ->assertForbidden()
        ->assertSeeText(ClassroomPolicy::ARCHIVED_MESSAGE);

    expect($learner->refresh()->password)->toBe($originalHash);
});

it('forbids teachers without the manage learners permission', function () {
    $classroom = Classroom::factory()->create();
    $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();
    $originalHash = $learner->password;
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageStudents);

    $this->actingAs($classroom->teacher)
        ->put(route('teacher.classrooms.students.password.update', [$classroom, $learner]))
        ->assertForbidden()
        ->assertSeeText(RolePermissions::deniedMessage(Permission::ManageStudents));

    expect($learner->refresh()->password)->toBe($originalHash);
});

it('reports another teacher’s classroom as missing', function () {
    $classroom = Classroom::factory()->create();
    $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();
    $originalHash = $learner->password;

    $this->actingAs(User::factory()->teaching($classroom->subject)->create())
        ->put(route('teacher.classrooms.students.password.update', [$classroom, $learner]))
        ->assertNotFound();

    expect($learner->refresh()->password)->toBe($originalHash);
});

it('refuses the owner once the subject was revoked', function () {
    $classroom = Classroom::factory()->revokedSubject()->create();
    $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();

    $this->actingAs($classroom->teacher)
        ->put(route('teacher.classrooms.students.password.update', [$classroom, $learner]))
        ->assertForbidden()
        ->assertSeeText(ClassroomPolicy::SUBJECT_REVOKED_MESSAGE);
});

it('forbids accounts of the other portals', function (UserRole $role) {
    $classroom = Classroom::factory()->create();
    $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();

    $this->actingAs(User::factory()->{$role->value}()->create())
        ->put(route('teacher.classrooms.students.password.update', [$classroom, $learner]))
        ->assertForbidden();
})->with([
    'administrator' => UserRole::Admin,
    'parent' => UserRole::Parent,
]);

it('sends guests to the sign-in page', function () {
    $classroom = Classroom::factory()->create();
    $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();

    $this->put(route('teacher.classrooms.students.password.update', [$classroom, $learner]))->assertRedirectToRoute('login');
});
