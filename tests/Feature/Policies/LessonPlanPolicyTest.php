<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\LessonPlan;
use App\Models\User;
use App\Services\RolePermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

describe('viewAny', function () {
    it('allows teachers with the lesson plan permission', function () {
        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('viewAny', LessonPlan::class))->toEqual(Response::allow());
    });

    it('denies teachers when the administrator revoked the permission', function () {
        $teacher = User::factory()->teacher()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageLessonPlans);

        expect(Gate::forUser($teacher)->inspect('viewAny', LessonPlan::class))
            ->toEqual(Response::deny('You do not have permission to manage lesson plans.'));
    });

    it('denies the other roles', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('viewAny', LessonPlan::class))->toEqual(Response::deny());
    })->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);
});

describe('create', function () {
    it('allows a teacher with an assigned subject', function () {
        expect(Gate::forUser(User::factory()->teaching()->create())->inspect('create', LessonPlan::class))->toEqual(Response::allow());
    });

    it('denies a teacher without assigned subjects', function () {
        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('create', LessonPlan::class))
            ->toEqual(Response::deny('No subject is assigned to you yet. Ask your school administrator to assign one.'));
    });

    it('denies teachers when the administrator revoked the permission', function () {
        $teacher = User::factory()->teaching()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageLessonPlans);

        expect(Gate::forUser($teacher)->inspect('create', LessonPlan::class))
            ->toEqual(Response::deny('You do not have permission to manage lesson plans.'));
    });

    it('denies the other roles', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('create', LessonPlan::class))->toEqual(Response::deny());
    })->with([UserRole::Admin, UserRole::Parent]);
});

describe('view, update and delete', function () {
    dataset('lesson plan abilities', ['view', 'update', 'delete']);

    it('allows the teacher who wrote the lesson plan', function (string $ability) {
        $lessonPlan = LessonPlan::factory()->create();

        expect(Gate::forUser($lessonPlan->teacher)->inspect($ability, $lessonPlan))->toEqual(Response::allow());
    })->with('lesson plan abilities');

    it('reports the lesson plan of another teacher as missing, even one who teaches the same subject', function (string $ability) {
        $lessonPlan = LessonPlan::factory()->create();

        expect(Gate::forUser(User::factory()->teaching($lessonPlan->subject)->create())->inspect($ability, $lessonPlan))
            ->toEqual(Response::denyAsNotFound());
    })->with('lesson plan abilities');

    it('reports the lesson plan as missing to the other roles', function (UserRole $role) {
        $lessonPlan = LessonPlan::factory()->create();

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('view', $lessonPlan))->toEqual(Response::denyAsNotFound());
    })->with([UserRole::Admin, UserRole::Parent]);

    it('tells the teacher when the administrator revoked the subject', function (string $ability) {
        $lessonPlan = LessonPlan::factory()->revokedSubject()->create();

        expect(Gate::forUser($lessonPlan->teacher)->inspect($ability, $lessonPlan))
            ->toEqual(Response::deny('Your access to this subject was removed by the administrator.'));
    })->with('lesson plan abilities');

    it('denies the teacher when the administrator revoked the permission', function (string $ability) {
        $lessonPlan = LessonPlan::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageLessonPlans);

        expect(Gate::forUser($lessonPlan->teacher)->inspect($ability, $lessonPlan))
            ->toEqual(Response::deny('You do not have permission to manage lesson plans.'));
    })->with('lesson plan abilities');
});
