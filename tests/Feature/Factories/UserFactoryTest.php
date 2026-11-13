<?php

use App\Enums\GradeLevel;
use App\Enums\GuardianRelationship;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;

it('creates an active, verified teacher with an email address by default', function () {
    $user = User::factory()->create()->fresh();

    expect($user->role)->toBe(UserRole::Teacher);
    expect($user->is_active)->toBeTrue();
    expect($user->email)->not->toBeNull();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->username)->toBeNull();
});

it('creates users with the role of each role state', function (string $state, UserRole $role) {
    expect(User::factory()->{$state}()->create()->fresh()->role)->toBe($role);
})->with([
    'admin' => ['admin', UserRole::Admin],
    'teacher' => ['teacher', UserRole::Teacher],
    'parent' => ['parent', UserRole::Parent],
    'student' => ['student', UserRole::Student],
]);

it('creates a learner with a username, a learner profile and no email address', function () {
    $student = User::factory()->student()->create();

    expect($student->username)->not->toBeNull();
    expect($student->email)->toBeNull();
    expect($student->studentProfile)->not->toBeNull();
});

it('creates the learner profile in the requested grade', function (GradeLevel $grade) {
    $student = User::factory()->student($grade)->create();

    expect($student->studentProfile->grade_level)->toBe($grade);
})->with(GradeLevel::cases());

it('creates a learner account without a profile when asked', function () {
    $student = User::factory()->studentWithoutProfile()->create();

    expect($student->role)->toBe(UserRole::Student);
    expect($student->studentProfile()->exists())->toBeFalse();
});

it('creates deactivated and unverified accounts', function () {
    expect(User::factory()->inactive()->create()->fresh()->is_active)->toBeFalse();
    expect(User::factory()->unverified()->create()->fresh()->email_verified_at)->toBeNull();
});

it('assigns the given subjects to the teacher', function () {
    $subjects = Subject::factory()->count(2)->create();

    $teacher = User::factory()->teaching(...$subjects)->create();

    expect($teacher->assignedSubjects()->pluck('subjects.id')->all())->toEqualCanonicalizing($subjects->modelKeys());
});

it('assigns a new subject to the teacher when none is given', function () {
    $teacher = User::factory()->teaching()->create();

    expect($teacher->assignedSubjects()->count())->toBe(1);
});

it('enrolls the learner in the given classrooms', function () {
    $classrooms = Classroom::factory()->count(2)->create();

    $student = User::factory()->student()->enrolledIn(...$classrooms)->create();

    expect($student->enrolledClassrooms()->pluck('classrooms.id')->all())->toEqualCanonicalizing($classrooms->modelKeys());
});

it('links the parent to the learner with the given relationship', function () {
    $student = User::factory()->student()->create();

    $guardian = User::factory()->parent()->guardianOf($student, GuardianRelationship::Father)->create();

    $this->assertDatabaseHas('guardian_student', [
        'guardian_id' => $guardian->id,
        'student_id' => $student->id,
        'relationship' => GuardianRelationship::Father->value,
    ]);
});
