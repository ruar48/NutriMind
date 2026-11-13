<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\LearnerFeedback;
use App\Models\User;
use App\Services\RolePermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

describe('viewAny and create', function () {
    it('allows teachers with the feedback permission', function (string $ability) {
        expect(Gate::forUser(User::factory()->teacher()->create())->inspect($ability, LearnerFeedback::class))->toEqual(Response::allow());
    })->with(['viewAny', 'create']);

    it('denies teachers when the administrator revoked the permission', function (string $ability) {
        $teacher = User::factory()->teacher()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::GiveFeedback);

        expect(Gate::forUser($teacher)->inspect($ability, LearnerFeedback::class))
            ->toEqual(Response::deny('You do not have permission to give learner feedback.'));
    })->with(['viewAny', 'create']);

    it('denies the other roles', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('create', LearnerFeedback::class))->toEqual(Response::deny());
    })->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);
});

describe('update and delete', function () {
    it('allows the teacher who wrote the feedback', function (string $ability) {
        $feedback = LearnerFeedback::factory()->create();

        expect(Gate::forUser($feedback->teacher)->inspect($ability, $feedback))->toEqual(Response::allow());
    })->with(['update', 'delete']);

    it('reports the feedback of another teacher as missing', function (string $ability) {
        $feedback = LearnerFeedback::factory()->create();

        expect(Gate::forUser(User::factory()->teacher()->create())->inspect($ability, $feedback))->toEqual(Response::denyAsNotFound());
    })->with(['update', 'delete']);

    it('reports the feedback as missing to the other roles, the learner included', function (string $ability) {
        $feedback = LearnerFeedback::factory()->create();
        $parent = User::factory()->parent()->guardianOf($feedback->student)->create();

        expect(Gate::forUser($parent)->inspect($ability, $feedback))->toEqual(Response::denyAsNotFound())
            ->and(Gate::forUser($feedback->student)->inspect($ability, $feedback))->toEqual(Response::denyAsNotFound())
            ->and(Gate::forUser(User::factory()->admin()->create())->inspect($ability, $feedback))->toEqual(Response::denyAsNotFound());
    })->with(['update', 'delete']);

    it('denies the author when the administrator revoked the permission', function (string $ability) {
        $feedback = LearnerFeedback::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::GiveFeedback);

        expect(Gate::forUser($feedback->teacher)->inspect($ability, $feedback))
            ->toEqual(Response::deny('You do not have permission to give learner feedback.'));
    })->with(['update', 'delete']);
});
