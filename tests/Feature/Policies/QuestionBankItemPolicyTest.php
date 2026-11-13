<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\QuestionBankItem;
use App\Models\User;
use App\Services\RolePermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

describe('viewAny', function () {
    it('allows teachers with the question bank permission', function () {
        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('viewAny', QuestionBankItem::class))->toEqual(Response::allow());
    });

    it('denies teachers when the administrator revoked the permission', function () {
        $teacher = User::factory()->teacher()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageQuestionBank);

        expect(Gate::forUser($teacher)->inspect('viewAny', QuestionBankItem::class))
            ->toEqual(Response::deny('You do not have permission to use the question bank.'));
    });

    it('denies the other roles', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('viewAny', QuestionBankItem::class))->toEqual(Response::deny());
    })->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);
});

describe('create', function () {
    it('allows a teacher with an assigned subject', function () {
        expect(Gate::forUser(User::factory()->teaching()->create())->inspect('create', QuestionBankItem::class))->toEqual(Response::allow());
    });

    it('denies a teacher without assigned subjects', function () {
        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('create', QuestionBankItem::class))
            ->toEqual(Response::deny('No subject is assigned to you yet. Ask your school administrator to assign one.'));
    });

    it('denies teachers when the administrator revoked the permission', function () {
        $teacher = User::factory()->teaching()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageQuestionBank);

        expect(Gate::forUser($teacher)->inspect('create', QuestionBankItem::class))
            ->toEqual(Response::deny('You do not have permission to use the question bank.'));
    });

    it('denies the other roles', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('create', QuestionBankItem::class))->toEqual(Response::deny());
    })->with([UserRole::Admin, UserRole::Parent]);
});

describe('view', function () {
    it('allows the owner', function () {
        $item = QuestionBankItem::factory()->create();

        expect(Gate::forUser($item->teacher)->inspect('view', $item))->toEqual(Response::allow());
    });

    it('allows another teacher of the subject to open a shared item', function () {
        $item = QuestionBankItem::factory()->shared()->create();

        expect(Gate::forUser(User::factory()->teaching($item->subject)->create())->inspect('view', $item))->toEqual(Response::allow());
    });

    it('reports a private item of another teacher as missing', function () {
        $item = QuestionBankItem::factory()->create();

        expect(Gate::forUser(User::factory()->teaching($item->subject)->create())->inspect('view', $item))
            ->toEqual(Response::denyAsNotFound());
    });

    it('reports a shared item of a subject the teacher does not teach as missing', function () {
        $item = QuestionBankItem::factory()->shared()->create();

        expect(Gate::forUser(User::factory()->teaching()->create())->inspect('view', $item))->toEqual(Response::denyAsNotFound());
    });

    it('reports the owner\'s item as missing once the administrator revoked its subject', function () {
        $item = QuestionBankItem::factory()->revokedSubject()->create();

        expect(Gate::forUser($item->teacher)->inspect('view', $item))->toEqual(Response::denyAsNotFound());
    });

    it('reports the item as missing to the other roles', function (UserRole $role) {
        $item = QuestionBankItem::factory()->shared()->create();

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('view', $item))->toEqual(Response::denyAsNotFound());
    })->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);

    it('denies the owner when the administrator revoked the permission', function () {
        $item = QuestionBankItem::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageQuestionBank);

        expect(Gate::forUser($item->teacher)->inspect('view', $item))
            ->toEqual(Response::deny('You do not have permission to use the question bank.'));
    });
});

describe('update and delete', function () {
    it('allows the owner', function (string $ability) {
        $item = QuestionBankItem::factory()->shared()->create();

        expect(Gate::forUser($item->teacher)->inspect($ability, $item))->toEqual(Response::allow());
    })->with(['update', 'delete']);

    it('denies another teacher of the subject a shared item they can see', function (string $ability) {
        $item = QuestionBankItem::factory()->shared()->create();

        expect(Gate::forUser(User::factory()->teaching($item->subject)->create())->inspect($ability, $item))
            ->toEqual(Response::deny('Only the teacher who created this question can change it. Duplicate it to make your own copy.'));
    })->with(['update', 'delete']);

    it('reports a private item of another teacher as missing', function (string $ability) {
        $item = QuestionBankItem::factory()->create();

        expect(Gate::forUser(User::factory()->teaching($item->subject)->create())->inspect($ability, $item))
            ->toEqual(Response::denyAsNotFound());
    })->with(['update', 'delete']);

    it('reports the owner\'s item as missing once the administrator revoked its subject', function (string $ability) {
        $item = QuestionBankItem::factory()->revokedSubject()->create();

        expect(Gate::forUser($item->teacher)->inspect($ability, $item))->toEqual(Response::denyAsNotFound());
    })->with(['update', 'delete']);

    it('denies the owner when the administrator revoked the permission', function (string $ability) {
        $item = QuestionBankItem::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageQuestionBank);

        expect(Gate::forUser($item->teacher)->inspect($ability, $item))
            ->toEqual(Response::deny('You do not have permission to use the question bank.'));
    })->with(['update', 'delete']);

    it('treats an item whose teacher was deleted as nobody\'s', function () {
        $item = QuestionBankItem::factory()->shared()->create();
        $viewer = User::factory()->teaching($item->subject)->create();
        $item->teacher->delete();

        expect(Gate::forUser($viewer)->inspect('update', $item->refresh()))
            ->toEqual(Response::deny('Only the teacher who created this question can change it. Duplicate it to make your own copy.'));
    });
});

describe('share', function () {
    it('allows the owner', function () {
        $item = QuestionBankItem::factory()->create();

        expect(Gate::forUser($item->teacher)->inspect('share', $item))->toEqual(Response::allow());
    });

    it('denies the owner when the administrator revoked the share permission', function () {
        $item = QuestionBankItem::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ShareQuestions);

        expect(Gate::forUser($item->teacher)->inspect('share', $item))
            ->toEqual(Response::deny('You do not have permission to share questions.'));
    });

    it('denies another teacher of the subject', function () {
        $item = QuestionBankItem::factory()->shared()->create();

        expect(Gate::forUser(User::factory()->teaching($item->subject)->create())->inspect('share', $item))
            ->toEqual(Response::deny('Only the teacher who created this question can change it. Duplicate it to make your own copy.'));
    });

    it('reports a private item of another teacher as missing', function () {
        $item = QuestionBankItem::factory()->create();

        expect(Gate::forUser(User::factory()->teaching($item->subject)->create())->inspect('share', $item))
            ->toEqual(Response::denyAsNotFound());
    });
});
