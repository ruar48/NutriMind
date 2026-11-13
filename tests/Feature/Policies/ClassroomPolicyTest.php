<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use App\Services\RolePermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

describe('viewAny', function () {
    it('allows teachers', function () {
        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('viewAny', Classroom::class))->toEqual(Response::allow());
    });

    it('denies the other roles', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('viewAny', Classroom::class))->toEqual(Response::deny());
    })->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);
});

describe('view', function () {
    it('allows the teacher of the classroom', function () {
        $classroom = Classroom::factory()->create();

        expect(Gate::forUser($classroom->teacher)->inspect('view', $classroom))->toEqual(Response::allow());
    });

    it('allows the teacher of an archived classroom', function () {
        $classroom = Classroom::factory()->archived()->create();

        expect(Gate::forUser($classroom->teacher)->inspect('view', $classroom))->toEqual(Response::allow());
    });

    it('reports the classroom of another teacher as missing, even one who teaches the same subject', function () {
        $classroom = Classroom::factory()->create();
        $otherTeacher = User::factory()->teaching($classroom->subject)->create();

        expect(Gate::forUser($otherTeacher)->inspect('view', $classroom))->toEqual(Response::denyAsNotFound());
    });

    it('reports the classroom as missing to the other roles', function (UserRole $role) {
        $classroom = Classroom::factory()->create();

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('view', $classroom))->toEqual(Response::denyAsNotFound());
    })->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);

    it('tells the teacher when the administrator revoked the subject', function () {
        $classroom = Classroom::factory()->revokedSubject()->create();

        expect(Gate::forUser($classroom->teacher)->inspect('view', $classroom))
            ->toEqual(Response::deny('Your access to this subject was removed by the administrator.'));
    });
});

describe('create', function () {
    it('allows a teacher with an active assigned subject', function () {
        $teacher = User::factory()->teaching(Subject::factory()->inactive()->create(), Subject::factory()->create())->create();

        expect(Gate::forUser($teacher)->inspect('create', Classroom::class))->toEqual(Response::allow());
    });

    it('denies a teacher without assigned subjects', function () {
        $teacher = User::factory()->teacher()->create();

        expect(Gate::forUser($teacher)->inspect('create', Classroom::class))
            ->toEqual(Response::deny('No subject is assigned to you yet. Ask your school administrator to assign one.'));
    });

    it('denies a teacher whose assigned subjects are all inactive', function () {
        $teacher = User::factory()->teaching(Subject::factory()->inactive()->create())->create();

        expect(Gate::forUser($teacher)->inspect('create', Classroom::class))
            ->toEqual(Response::deny('None of your assigned subjects is active. Ask your school administrator to assign an active subject.'));
    });

    it('denies teachers when the administrator revoked the permission', function () {
        $teacher = User::factory()->teaching()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageClassrooms);

        expect(Gate::forUser($teacher)->inspect('create', Classroom::class))
            ->toEqual(Response::deny('You do not have permission to manage classrooms.'));
    });

    it('denies the other roles', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('create', Classroom::class))->toEqual(Response::deny());
    })->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);
});

describe('update and delete', function () {
    it('allows the teacher of the classroom', function (string $ability) {
        $classroom = Classroom::factory()->create();

        expect(Gate::forUser($classroom->teacher)->inspect($ability, $classroom))->toEqual(Response::allow());
    })->with(['update', 'delete']);

    it('reports the classroom of another teacher as missing', function (string $ability) {
        $classroom = Classroom::factory()->create();

        expect(Gate::forUser(User::factory()->teaching($classroom->subject)->create())->inspect($ability, $classroom))
            ->toEqual(Response::denyAsNotFound());
    })->with(['update', 'delete']);

    it('tells the teacher when the administrator revoked the subject', function (string $ability) {
        $classroom = Classroom::factory()->revokedSubject()->create();

        expect(Gate::forUser($classroom->teacher)->inspect($ability, $classroom))
            ->toEqual(Response::deny('Your access to this subject was removed by the administrator.'));
    })->with(['update', 'delete']);

    it('denies the teacher when the administrator revoked the permission', function (string $ability) {
        $classroom = Classroom::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageClassrooms);

        expect(Gate::forUser($classroom->teacher)->inspect($ability, $classroom))
            ->toEqual(Response::deny('You do not have permission to manage classrooms.'));
    })->with(['update', 'delete']);
});

describe('manageStudents and importStudents', function () {
    dataset('learner abilities', [
        'manage learners' => ['manageStudents', Permission::ManageStudents, 'You do not have permission to manage classroom learners.'],
        'import learners' => ['importStudents', Permission::ImportStudents, 'You do not have permission to import learners from CSV.'],
    ]);

    it('allows the teacher of an active classroom', function (string $ability) {
        $classroom = Classroom::factory()->create();

        expect(Gate::forUser($classroom->teacher)->inspect($ability, $classroom))->toEqual(Response::allow());
    })->with('learner abilities');

    it('denies changes to the learners of an archived classroom', function (string $ability) {
        $classroom = Classroom::factory()->archived()->create();

        expect(Gate::forUser($classroom->teacher)->inspect($ability, $classroom))
            ->toEqual(Response::deny('This classroom is archived. Restore it before changing its learners.'));
    })->with('learner abilities');

    it('denies the teacher when the administrator revoked the permission', function (string $ability, Permission $permission, string $message) {
        $classroom = Classroom::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, $permission);

        expect(Gate::forUser($classroom->teacher)->inspect($ability, $classroom))->toEqual(Response::deny($message));
    })->with('learner abilities');

    it('keeps the other learner permission when one is revoked', function () {
        $classroom = Classroom::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ImportStudents);

        expect(Gate::forUser($classroom->teacher)->inspect('manageStudents', $classroom))->toEqual(Response::allow());
    });

    it('reports the classroom of another teacher as missing', function (string $ability) {
        $classroom = Classroom::factory()->create();

        expect(Gate::forUser(User::factory()->teaching($classroom->subject)->create())->inspect($ability, $classroom))
            ->toEqual(Response::denyAsNotFound());
    })->with('learner abilities');

    it('tells the teacher when the administrator revoked the subject', function (string $ability) {
        $classroom = Classroom::factory()->revokedSubject()->create();

        expect(Gate::forUser($classroom->teacher)->inspect($ability, $classroom))
            ->toEqual(Response::deny('Your access to this subject was removed by the administrator.'));
    })->with('learner abilities');
});

it('denies an inactive teacher every ability that needs a permission', function (string $ability) {
    $classroom = Classroom::factory()->create();
    $teacher = $classroom->teacher;
    $teacher->is_active = false;
    $teacher->save();

    expect(Gate::forUser($teacher)->inspect($ability, $classroom)->denied())->toBeTrue();
})->with(['update', 'delete', 'manageStudents', 'importStudents']);
