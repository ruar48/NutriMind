<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\ForumThread;
use App\Models\User;
use App\Services\RolePermissions;
use App\Services\Settings;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

describe('viewAny', function () {
    it('allows administrators, teachers and parents', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('viewAny', ForumThread::class))->toEqual(Response::allow());
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('denies learners', function () {
        expect(Gate::forUser(User::factory()->student()->create())->inspect('viewAny', ForumThread::class))->toEqual(Response::deny());
    });

    it('tells teachers and parents while the forum is turned off', function (UserRole $role) {
        app(Settings::class)->set('forum_enabled', false);

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('viewAny', ForumThread::class))
            ->toEqual(Response::deny('The parent forum is turned off right now.'));
    })->with([UserRole::Teacher, UserRole::Parent]);

    it('keeps the forum open to administrators while it is turned off', function () {
        app(Settings::class)->set('forum_enabled', false);

        expect(Gate::forUser(User::factory()->admin()->create())->inspect('viewAny', ForumThread::class))->toEqual(Response::allow());
    });

    it('denies a role whose forum access the administrator revoked, and only that role', function () {
        $parent = User::factory()->parent()->create();
        $teacher = User::factory()->teacher()->create();
        app(RolePermissions::class)->revoke(UserRole::Parent, Permission::AccessForum);

        expect(Gate::forUser($parent)->inspect('viewAny', ForumThread::class))
            ->toEqual(Response::deny('You do not have permission to access the parent forum.'));
        expect(Gate::forUser($teacher)->inspect('viewAny', ForumThread::class))->toEqual(Response::allow());
    });
});

describe('view', function () {
    it('allows forum members to open a published thread', function (UserRole $role) {
        $thread = ForumThread::factory()->create();

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('view', $thread))->toEqual(Response::allow());
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('reports a pending or hidden thread of someone else as missing', function (string $state) {
        $thread = ForumThread::factory()->{$state}()->create();

        expect(Gate::forUser(User::factory()->parent()->create())->inspect('view', $thread))->toEqual(Response::denyAsNotFound());
    })->with(['pending', 'hidden']);

    it('lets the author open their own pending or hidden thread', function (string $state) {
        $thread = ForumThread::factory()->{$state}()->create();

        expect(Gate::forUser($thread->author)->inspect('view', $thread))->toEqual(Response::allow());
    })->with(['pending', 'hidden']);

    it('lets administrators open every thread', function (string $state) {
        $thread = ForumThread::factory()->{$state}()->create();

        expect(Gate::forUser(User::factory()->admin()->create())->inspect('view', $thread))->toEqual(Response::allow());
    })->with(['pending', 'hidden']);

    it('denies a published thread while the forum is turned off', function () {
        $thread = ForumThread::factory()->create();
        app(Settings::class)->set('forum_enabled', false);

        expect(Gate::forUser($thread->author)->inspect('view', $thread))
            ->toEqual(Response::deny('The parent forum is turned off right now.'));
    });
});

describe('create', function () {
    it('allows forum members who may post', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('create', ForumThread::class))->toEqual(Response::allow());
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('denies a role whose posting the administrator revoked', function () {
        $parent = User::factory()->parent()->create();
        app(RolePermissions::class)->revoke(UserRole::Parent, Permission::PostInForum);

        expect(Gate::forUser($parent)->inspect('create', ForumThread::class))
            ->toEqual(Response::deny('You do not have permission to post in the parent forum.'));
    });

    it('denies teachers and parents while the forum is turned off', function () {
        app(Settings::class)->set('forum_enabled', false);

        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('create', ForumThread::class))
            ->toEqual(Response::deny('The parent forum is turned off right now.'));
    });

    it('denies learners', function () {
        expect(Gate::forUser(User::factory()->student()->create())->inspect('create', ForumThread::class))->toEqual(Response::deny());
    });
});

describe('reply', function () {
    it('allows forum members to reply to an open published thread', function (UserRole $role) {
        $thread = ForumThread::factory()->create();

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('reply', $thread))->toEqual(Response::allow());
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('denies replies to a locked thread', function () {
        $thread = ForumThread::factory()->locked()->create();

        expect(Gate::forUser($thread->author)->inspect('reply', $thread))->toEqual(Response::deny('This thread is locked.'));
    });

    it('lets administrators reply to a locked thread', function () {
        $thread = ForumThread::factory()->locked()->create();

        expect(Gate::forUser(User::factory()->admin()->create())->inspect('reply', $thread))->toEqual(Response::allow());
    });

    it('reports a pending thread of someone else as missing', function () {
        $thread = ForumThread::factory()->pending()->create();

        expect(Gate::forUser(User::factory()->parent()->create())->inspect('reply', $thread))->toEqual(Response::denyAsNotFound());
    });

    it('denies a role whose posting the administrator revoked', function () {
        $thread = ForumThread::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::PostInForum);

        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('reply', $thread))
            ->toEqual(Response::deny('You do not have permission to post in the parent forum.'));
    });
});

describe('update', function () {
    it('allows the author while the thread is not locked', function () {
        $thread = ForumThread::factory()->pending()->create();

        expect(Gate::forUser($thread->author)->inspect('update', $thread))->toEqual(Response::allow());
    });

    it('denies the author once the thread is locked', function () {
        $thread = ForumThread::factory()->locked()->create();

        expect(Gate::forUser($thread->author)->inspect('update', $thread))->toEqual(Response::deny('This thread is locked.'));
    });

    it('denies everyone else who can open the thread, administrators included', function (UserRole $role) {
        $thread = ForumThread::factory()->create();

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('update', $thread))
            ->toEqual(Response::deny('Only the author can edit this thread.'));
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('reports a pending thread of someone else as missing', function () {
        $thread = ForumThread::factory()->pending()->create();

        expect(Gate::forUser(User::factory()->parent()->create())->inspect('update', $thread))->toEqual(Response::denyAsNotFound());
    });
});

describe('delete', function () {
    it('allows the author, even of a locked thread', function () {
        $thread = ForumThread::factory()->locked()->create();

        expect(Gate::forUser($thread->author)->inspect('delete', $thread))->toEqual(Response::allow());
    });

    it('allows administrators', function () {
        $thread = ForumThread::factory()->hidden()->create();

        expect(Gate::forUser(User::factory()->admin()->create())->inspect('delete', $thread))->toEqual(Response::allow());
    });

    it('denies other teachers and parents who can open the thread', function (UserRole $role) {
        $thread = ForumThread::factory()->create();

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('delete', $thread))
            ->toEqual(Response::deny('Only the author or an administrator can delete this thread.'));
    })->with([UserRole::Teacher, UserRole::Parent]);

    it('reports a hidden thread of someone else as missing', function () {
        $thread = ForumThread::factory()->hidden()->create();

        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('delete', $thread))->toEqual(Response::denyAsNotFound());
    });
});

describe('moderate', function () {
    it('allows administrators', function () {
        expect(Gate::forUser(User::factory()->admin()->create())->inspect('moderate', ForumThread::factory()->create()))->toEqual(Response::allow());
    });

    it('denies teachers and parents', function (UserRole $role) {
        $thread = ForumThread::factory()->create();

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('moderate', $thread))
            ->toEqual(Response::deny('Only administrators can moderate the forum.'));
    })->with([UserRole::Teacher, UserRole::Parent]);

    it('denies the author of the thread', function () {
        $thread = ForumThread::factory()->create();

        expect(Gate::forUser($thread->author)->inspect('moderate', $thread))
            ->toEqual(Response::deny('Only administrators can moderate the forum.'));
    });
});

describe('report', function () {
    it('allows forum members to report a thread of someone else', function (UserRole $role) {
        $thread = ForumThread::factory()->create();

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('report', $thread))->toEqual(Response::allow());
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('denies the author', function () {
        $thread = ForumThread::factory()->create();

        expect(Gate::forUser($thread->author)->inspect('report', $thread))->toEqual(Response::deny('You cannot report your own post.'));
    });

    it('reports a pending thread of someone else as missing', function () {
        $thread = ForumThread::factory()->pending()->create();

        expect(Gate::forUser(User::factory()->parent()->create())->inspect('report', $thread))->toEqual(Response::denyAsNotFound());
    });

    it('denies a role whose forum access the administrator revoked', function () {
        $thread = ForumThread::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::AccessForum);

        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('report', $thread))
            ->toEqual(Response::deny('You do not have permission to access the parent forum.'));
    });
});
