<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Services\RolePermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

describe('viewAsTeacher', function () {
    it('allows the teacher of a classroom the learner is enrolled in', function () {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();

        expect(Gate::forUser($classroom->teacher)->inspect('viewAsTeacher', $student))->toEqual(Response::allow());
    });

    it('allows the teacher of an archived classroom the learner was enrolled in', function () {
        $classroom = Classroom::factory()->archived()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();

        expect(Gate::forUser($classroom->teacher)->inspect('viewAsTeacher', $student))->toEqual(Response::allow());
    });

    it('allows the teacher while one of the learner\'s classrooms keeps an assigned subject', function () {
        $teacher = User::factory()->teacher()->create();
        $revokedClassroom = Classroom::factory()->for($teacher, 'teacher')->revokedSubject()->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
        $student = User::factory()->student()->enrolledIn($revokedClassroom, $classroom)->create();

        expect(Gate::forUser($teacher)->inspect('viewAsTeacher', $student))->toEqual(Response::allow());
    });

    it('reports the learner as missing to the teacher of another classroom of the same subject', function () {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();
        $otherTeacher = User::factory()->teaching($classroom->subject)->create();
        Classroom::factory()->for($otherTeacher, 'teacher')->for($classroom->subject)->create();

        expect(Gate::forUser($otherTeacher)->inspect('viewAsTeacher', $student))->toEqual(Response::denyAsNotFound());
    });

    it('reports the learner as missing once the administrator revoked the classroom subject', function () {
        $classroom = Classroom::factory()->revokedSubject()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();

        expect(Gate::forUser($classroom->teacher)->inspect('viewAsTeacher', $student))->toEqual(Response::denyAsNotFound());
    });

    it('reports a learner who is not enrolled anywhere as missing', function () {
        $teacher = User::factory()->teaching()->create();

        expect(Gate::forUser($teacher)->inspect('viewAsTeacher', User::factory()->student()->create()))->toEqual(Response::denyAsNotFound());
    });

    it('reports accounts that are not learners as missing', function (UserRole $role) {
        $classroom = Classroom::factory()->create();
        $account = User::factory()->{$role->value}()->create();
        $classroom->students()->attach($account);

        expect(Gate::forUser($classroom->teacher)->inspect('viewAsTeacher', $account))->toEqual(Response::denyAsNotFound());
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('reports the learner as missing to the learner\'s parent', function () {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();
        $parent = User::factory()->parent()->guardianOf($student)->create();

        expect(Gate::forUser($parent)->inspect('viewAsTeacher', $student))->toEqual(Response::denyAsNotFound());
    });

    it('reports the learner as missing to administrators', function () {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();

        expect(Gate::forUser(User::factory()->admin()->create())->inspect('viewAsTeacher', $student))->toEqual(Response::denyAsNotFound());
    });
});

describe('giveFeedbackTo', function () {
    it('allows the teacher of a classroom the learner is enrolled in', function () {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();

        expect(Gate::forUser($classroom->teacher)->inspect('giveFeedbackTo', $student))->toEqual(Response::allow());
    });

    it('denies the teacher when the administrator revoked the feedback permission', function () {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::GiveFeedback);

        expect(Gate::forUser($classroom->teacher)->inspect('giveFeedbackTo', $student))
            ->toEqual(Response::deny('You do not have permission to give learner feedback.'));
    });

    it('reports a learner of another teacher as missing', function () {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();

        expect(Gate::forUser(User::factory()->teaching($classroom->subject)->create())->inspect('giveFeedbackTo', $student))
            ->toEqual(Response::denyAsNotFound());
    });
});

describe('viewAsGuardian', function () {
    it('allows a parent linked to the learner', function () {
        $student = User::factory()->student()->create();
        $parent = User::factory()->parent()->guardianOf($student)->create();

        expect(Gate::forUser($parent)->inspect('viewAsGuardian', $student))->toEqual(Response::allow());
    });

    it('reports a learner linked to another parent as missing', function () {
        $student = User::factory()->student()->create();
        User::factory()->parent()->guardianOf($student)->create();
        $otherParent = User::factory()->parent()->guardianOf(User::factory()->student()->create())->create();

        expect(Gate::forUser($otherParent)->inspect('viewAsGuardian', $student))->toEqual(Response::denyAsNotFound());
    });

    it('tells a linked parent when the administrator revoked the child progress permission', function () {
        $student = User::factory()->student()->create();
        $parent = User::factory()->parent()->guardianOf($student)->create();
        app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewChildProgress);

        expect(Gate::forUser($parent)->inspect('viewAsGuardian', $student))
            ->toEqual(Response::deny('You do not have permission to view child progress.'));
    });

    it('reports a learner as missing to an unlinked parent even when the permission is revoked', function () {
        $student = User::factory()->student()->create();
        app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewChildProgress);

        expect(Gate::forUser(User::factory()->parent()->create())->inspect('viewAsGuardian', $student))->toEqual(Response::denyAsNotFound());
    });

    it('reports the learner as missing to the learner\'s teacher', function () {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();

        expect(Gate::forUser($classroom->teacher)->inspect('viewAsGuardian', $student))->toEqual(Response::denyAsNotFound());
    });

    it('reports the learner as missing to administrators', function () {
        $student = User::factory()->student()->create();

        expect(Gate::forUser(User::factory()->admin()->create())->inspect('viewAsGuardian', $student))->toEqual(Response::denyAsNotFound());
    });

    it('denies an inactive linked parent', function () {
        $student = User::factory()->student()->create();
        $parent = User::factory()->parent()->inactive()->guardianOf($student)->create();

        expect(Gate::forUser($parent)->inspect('viewAsGuardian', $student))
            ->toEqual(Response::deny('You do not have permission to view child progress.'));
    });
});
