<?php

use App\Enums\UserRole;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

describe('update', function () {
    it('allows the author while the thread is not locked', function () {
        $reply = ForumReply::factory()->create();

        expect(Gate::forUser($reply->author)->inspect('update', $reply))->toEqual(Response::allow());
    });

    it('denies the author once the thread is locked', function () {
        $reply = ForumReply::factory()->for(ForumThread::factory()->locked(), 'thread')->create();

        expect(Gate::forUser($reply->author)->inspect('update', $reply))->toEqual(Response::deny('This thread is locked.'));
    });

    it('denies everyone else, administrators included', function (UserRole $role) {
        $reply = ForumReply::factory()->create();

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('update', $reply))
            ->toEqual(Response::deny('Only the author can edit this reply.'));
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('denies the author of the thread who did not write the reply', function () {
        $reply = ForumReply::factory()->create();

        expect(Gate::forUser($reply->thread->author)->inspect('update', $reply))
            ->toEqual(Response::deny('Only the author can edit this reply.'));
    });
});

describe('delete', function () {
    it('allows the author, even in a locked thread', function () {
        $reply = ForumReply::factory()->for(ForumThread::factory()->locked(), 'thread')->create();

        expect(Gate::forUser($reply->author)->inspect('delete', $reply))->toEqual(Response::allow());
    });

    it('allows administrators', function () {
        $reply = ForumReply::factory()->hidden()->create();

        expect(Gate::forUser(User::factory()->admin()->create())->inspect('delete', $reply))->toEqual(Response::allow());
    });

    it('denies other teachers and parents', function (UserRole $role) {
        $reply = ForumReply::factory()->create();

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('delete', $reply))
            ->toEqual(Response::deny('Only the author or an administrator can delete this reply.'));
    })->with([UserRole::Teacher, UserRole::Parent]);
});

describe('moderate', function () {
    it('allows administrators', function () {
        expect(Gate::forUser(User::factory()->admin()->create())->inspect('moderate', ForumReply::factory()->create()))->toEqual(Response::allow());
    });

    it('denies teachers and parents', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('moderate', ForumReply::factory()->create()))
            ->toEqual(Response::deny('Only administrators can moderate the forum.'));
    })->with([UserRole::Teacher, UserRole::Parent]);
});

describe('report', function () {
    it('allows forum members to report a reply of someone else', function (UserRole $role) {
        $reply = ForumReply::factory()->create();

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('report', $reply))->toEqual(Response::allow());
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('denies the author', function () {
        $reply = ForumReply::factory()->create();

        expect(Gate::forUser($reply->author)->inspect('report', $reply))->toEqual(Response::deny('You cannot report your own post.'));
    });

    it('reports a reply in a thread the user cannot open as missing', function () {
        $reply = ForumReply::factory()->for(ForumThread::factory()->pending(), 'thread')->create();

        expect(Gate::forUser(User::factory()->parent()->create())->inspect('report', $reply))->toEqual(Response::denyAsNotFound());
    });

    it('reports a hidden reply as missing', function () {
        $reply = ForumReply::factory()->hidden()->create();

        expect(Gate::forUser(User::factory()->parent()->create())->inspect('report', $reply))->toEqual(Response::denyAsNotFound());
    });

    it('denies teachers and parents while the forum is turned off', function () {
        $reply = ForumReply::factory()->create();
        app(Settings::class)->set('forum_enabled', false);

        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('report', $reply))
            ->toEqual(Response::deny('The parent forum is turned off right now.'));
    });
});
