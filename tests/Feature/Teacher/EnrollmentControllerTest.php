<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Requests\Teacher\StoreEnrollmentRequest;
use App\Models\Classroom;
use App\Models\User;
use App\Policies\ClassroomPolicy;
use App\Services\RolePermissions;

it('enrolls the chosen learners in the classroom', function () {
    $this->freezeSecond();
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five, 'name' => 'Health 5 – Sampaguita']);
    $elsewhere = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $ana = User::factory()->student(GradeLevel::Five)->enrolledIn($elsewhere)->create(['name' => 'Ana Reyes']);
    $ben = User::factory()->student(GradeLevel::Five)->create(['name' => 'Ben Cruz']);

    $this->actingAs($classroom->teacher)
        ->post(route('teacher.classrooms.enrollments.store', $classroom), ['student_ids' => [$ana->id, (string) $ben->id]])
        ->assertRedirectToRoute('teacher.classrooms.show', $classroom)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', '2 learners were enrolled in Health 5 – Sampaguita.');

    expect($classroom->students()->orderBy('users.id')->pluck('users.id')->all())->toBe([$ana->id, $ben->id])
        ->and($classroom->students()->first()->pivot->enrolled_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($elsewhere->students()->whereKey($ana->id)->exists())->toBeTrue();
});

it('names the learner when only one is enrolled', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Six, 'name' => 'Science 6 – Narra']);
    $learner = User::factory()->student(GradeLevel::Six)->create(['name' => 'Miguel Navarro']);

    $this->actingAs($classroom->teacher)
        ->post(route('teacher.classrooms.enrollments.store', $classroom), ['student_ids' => [$learner->id]])
        ->assertSessionHas('status', 'Miguel Navarro was enrolled in Science 6 – Narra.');
});

it('requires at least one learner', function (array $payload, string $field, string $message) {
    $classroom = Classroom::factory()->create();

    $this->actingAs($classroom->teacher)
        ->post(route('teacher.classrooms.enrollments.store', $classroom), $payload)
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'nothing chosen' => [[], 'student_ids', 'Choose at least one learner to enroll.'],
    'empty list' => [['student_ids' => []], 'student_ids', 'Choose at least one learner to enroll.'],
    'not a list' => [['student_ids' => 'all'], 'student_ids', 'Choose the learners to enroll from the search results.'],
    'not an ID' => [['student_ids' => ['abc']], 'student_ids.0', 'Choose the learners to enroll from the search results.'],
]);

it('rejects more learners than one request may enroll', function () {
    $classroom = Classroom::factory()->create();

    $this->actingAs($classroom->teacher)
        ->post(route('teacher.classrooms.enrollments.store', $classroom), ['student_ids' => range(1, StoreEnrollmentRequest::MAX_LEARNERS + 1)])
        ->assertSessionHasErrors(['student_ids' => 'You can enroll at most 50 learners at a time.']);
});

it('rejects the same learner twice', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $learner = User::factory()->student(GradeLevel::Five)->create();

    $this->actingAs($classroom->teacher)
        ->post(route('teacher.classrooms.enrollments.store', $classroom), ['student_ids' => [$learner->id, $learner->id]])
        ->assertSessionHasErrors(['student_ids.0' => 'Each learner can be chosen only once.']);

    expect($classroom->students()->count())->toBe(0);
});

it('rejects learners who cannot join the classroom and enrolls nobody', function (Closure $makeLearner) {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $eligible = User::factory()->student(GradeLevel::Five)->create();
    $ineligible = $makeLearner($classroom);

    $this->actingAs($classroom->teacher)
        ->post(route('teacher.classrooms.enrollments.store', $classroom), ['student_ids' => [$eligible->id, $ineligible->id]])
        ->assertSessionHasErrors(['student_ids.1' => 'Only active Grade 5 learners who are not in this class yet can be enrolled.']);

    expect($classroom->students()->whereKey($eligible->id)->exists())->toBeFalse();
})->with([
    'another grade' => fn (Classroom $classroom): User => User::factory()->student(GradeLevel::Six)->create(),
    'inactive account' => fn (Classroom $classroom): User => User::factory()->student(GradeLevel::Five)->inactive()->create(),
    'already enrolled' => fn (Classroom $classroom): User => User::factory()->student(GradeLevel::Five)->enrolledIn($classroom)->create(),
    'not a learner' => fn (Classroom $classroom): User => User::factory()->parent()->create(),
    'learner without a profile' => fn (Classroom $classroom): User => User::factory()->studentWithoutProfile()->create(),
    'unknown account' => fn (Classroom $classroom): User => User::factory()->make(['id' => 999999]),
]);

it('refuses archived classrooms', function () {
    $classroom = Classroom::factory()->archived()->create(['grade_level' => GradeLevel::Five]);
    $learner = User::factory()->student(GradeLevel::Five)->create();

    $this->actingAs($classroom->teacher)
        ->post(route('teacher.classrooms.enrollments.store', $classroom), ['student_ids' => [$learner->id]])
        ->assertForbidden()
        ->assertSeeText(ClassroomPolicy::ARCHIVED_MESSAGE);

    expect($classroom->students()->count())->toBe(0);
});

it('forbids teachers without the manage learners permission', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $learner = User::factory()->student(GradeLevel::Five)->create();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageStudents);

    $this->actingAs($classroom->teacher)
        ->post(route('teacher.classrooms.enrollments.store', $classroom), ['student_ids' => [$learner->id]])
        ->assertForbidden()
        ->assertSessionHasNoErrors();

    expect($classroom->students()->count())->toBe(0);
});

it('reports another teacher’s classroom as missing', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $learner = User::factory()->student(GradeLevel::Five)->create();

    $this->actingAs(User::factory()->teaching($classroom->subject)->create())
        ->post(route('teacher.classrooms.enrollments.store', $classroom), ['student_ids' => [$learner->id]])
        ->assertNotFound();

    expect($classroom->students()->count())->toBe(0);
});

it('refuses the owner once the subject was revoked', function () {
    $classroom = Classroom::factory()->revokedSubject()->create(['grade_level' => GradeLevel::Five]);
    $learner = User::factory()->student(GradeLevel::Five)->create();

    $this->actingAs($classroom->teacher)
        ->post(route('teacher.classrooms.enrollments.store', $classroom), ['student_ids' => [$learner->id]])
        ->assertForbidden()
        ->assertSeeText(ClassroomPolicy::SUBJECT_REVOKED_MESSAGE);
});

it('forbids accounts of the other portals', function (UserRole $role) {
    $classroom = Classroom::factory()->create();

    $this->actingAs(User::factory()->{$role->value}()->create())
        ->post(route('teacher.classrooms.enrollments.store', $classroom), ['student_ids' => [1]])
        ->assertForbidden();
})->with([
    'administrator' => UserRole::Admin,
    'parent' => UserRole::Parent,
]);

it('sends guests to the sign-in page', function () {
    $this->post(route('teacher.classrooms.enrollments.store', Classroom::factory()->create()), ['student_ids' => [1]])
        ->assertRedirectToRoute('login');
});
