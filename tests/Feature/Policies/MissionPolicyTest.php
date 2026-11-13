<?php

use App\Enums\UserRole;
use App\Models\Mission;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

describe('viewAnyProgress', function () {
    it('allows teachers', function () {
        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('viewAnyProgress', Mission::class))->toEqual(Response::allow());
    });

    it('denies the other roles', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('viewAnyProgress', Mission::class))->toEqual(Response::deny());
    })->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);
});

describe('viewProgress', function () {
    it('allows a teacher of the mission’s subject', function () {
        $subject = Subject::factory()->create();
        $mission = Mission::factory()->for($subject)->create();

        expect(Gate::forUser(User::factory()->teaching($subject)->create())->inspect('viewProgress', $mission))->toEqual(Response::allow());
    });

    it('reports missions of other subjects as missing', function () {
        $mission = Mission::factory()->create();

        expect(Gate::forUser(User::factory()->teaching()->create())->inspect('viewProgress', $mission))->toEqual(Response::denyAsNotFound());
    });

    it('reports the mission as missing to the other roles', function (UserRole $role) {
        $mission = Mission::factory()->create();

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('viewProgress', $mission))->toEqual(Response::denyAsNotFound());
    })->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);
});
