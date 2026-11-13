<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use App\Services\RolePermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

describe('viewAny', function () {
    it('allows teachers', function () {
        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('viewAny', Quiz::class))->toEqual(Response::allow());
    });

    it('denies the other roles', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('viewAny', Quiz::class))->toEqual(Response::deny());
    })->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);
});

describe('view and viewResults', function () {
    it('allows the teacher who owns the quiz', function (string $ability) {
        $quiz = Quiz::factory()->create();

        expect(Gate::forUser($quiz->teacher)->inspect($ability, $quiz))->toEqual(Response::allow());
    })->with(['view', 'viewResults']);

    it('reports the quiz of another teacher as missing, even one who teaches the same subject', function (string $ability) {
        $quiz = Quiz::factory()->published()->create();

        expect(Gate::forUser(User::factory()->teaching($quiz->subject)->create())->inspect($ability, $quiz))
            ->toEqual(Response::denyAsNotFound());
    })->with(['view', 'viewResults']);

    it('reports the quiz as missing to the other roles', function (UserRole $role) {
        $quiz = Quiz::factory()->create();

        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('view', $quiz))->toEqual(Response::denyAsNotFound());
    })->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);

    it('tells the teacher when the administrator revoked the subject', function (string $ability) {
        $quiz = Quiz::factory()->revokedSubject()->create();

        expect(Gate::forUser($quiz->teacher)->inspect($ability, $quiz))
            ->toEqual(Response::deny('Your access to this subject was removed by the administrator.'));
    })->with(['view', 'viewResults']);

    it('lets the teacher see the results without the quiz permission', function () {
        $quiz = Quiz::factory()->published()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes);

        expect(Gate::forUser($quiz->teacher)->inspect('viewResults', $quiz))->toEqual(Response::allow());
    });
});

describe('create', function () {
    it('allows a teacher with an assigned subject, active or not', function () {
        $teacher = User::factory()->teaching(Subject::factory()->inactive()->create())->create();

        expect(Gate::forUser($teacher)->inspect('create', Quiz::class))->toEqual(Response::allow());
    });

    it('denies a teacher without assigned subjects', function () {
        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('create', Quiz::class))
            ->toEqual(Response::deny('No subject is assigned to you yet. Ask your school administrator to assign one.'));
    });

    it('denies teachers when the administrator revoked the permission', function () {
        $teacher = User::factory()->teaching()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes);

        expect(Gate::forUser($teacher)->inspect('create', Quiz::class))
            ->toEqual(Response::deny('You do not have permission to create and manage quizzes.'));
    });

    it('denies the other roles', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('create', Quiz::class))->toEqual(Response::deny());
    })->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);
});

describe('update', function () {
    it('allows the teacher who owns the quiz', function () {
        $quiz = Quiz::factory()->create();

        expect(Gate::forUser($quiz->teacher)->inspect('update', $quiz))->toEqual(Response::allow());
    });

    it('reports the quiz of another teacher as missing', function () {
        $quiz = Quiz::factory()->create();

        expect(Gate::forUser(User::factory()->teaching($quiz->subject)->create())->inspect('update', $quiz))
            ->toEqual(Response::denyAsNotFound());
    });

    it('tells the teacher when the administrator revoked the subject', function () {
        $quiz = Quiz::factory()->revokedSubject()->create();

        expect(Gate::forUser($quiz->teacher)->inspect('update', $quiz))
            ->toEqual(Response::deny('Your access to this subject was removed by the administrator.'));
    });

    it('denies the teacher when the administrator revoked the permission', function () {
        $quiz = Quiz::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes);

        expect(Gate::forUser($quiz->teacher)->inspect('update', $quiz))
            ->toEqual(Response::deny('You do not have permission to create and manage quizzes.'));
    });
});

describe('delete', function () {
    it('allows the teacher to delete a quiz nobody attempted', function () {
        $quiz = Quiz::factory()->published()->create();

        expect(Gate::forUser($quiz->teacher)->inspect('delete', $quiz))->toEqual(Response::allow());
    });

    it('denies deleting a quiz with learner attempts', function () {
        $quiz = Quiz::factory()->published()->create();
        QuizAttempt::factory()->for($quiz)->create();

        expect(Gate::forUser($quiz->teacher)->inspect('delete', $quiz))
            ->toEqual(Response::deny('This quiz already has learner attempts. Close it instead.'));
    });

    it('counts unfinished attempts too', function () {
        $quiz = Quiz::factory()->published()->create();
        QuizAttempt::factory()->inProgress()->for($quiz)->create();

        expect(Gate::forUser($quiz->teacher)->inspect('delete', $quiz)->allowed())->toBeFalse();
    });

    it('denies a quiz loaded with withExists from its attempts flag without querying the attempts', function () {
        $quiz = Quiz::factory()->published()->create();
        QuizAttempt::factory()->for($quiz)->create();
        $teacher = $quiz->teacher;
        $teacher->assignedSubjectIds();
        $loaded = Quiz::query()->withExists('attempts')->findOrFail($quiz->id);
        DB::enableQueryLog();

        $response = Gate::forUser($teacher)->inspect('delete', $loaded);

        expect($response)->toEqual(Response::deny('This quiz already has learner attempts. Close it instead.'));
        expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'quiz_attempts')))->toBeEmpty();
    });

    it('allows a quiz loaded with withExists when its attempts flag is false', function () {
        $quiz = Quiz::factory()->published()->create();
        $loaded = Quiz::query()->withExists('attempts')->findOrFail($quiz->id);

        expect(Gate::forUser($quiz->teacher)->inspect('delete', $loaded))->toEqual(Response::allow());
    });

    it('reports the quiz of another teacher as missing before looking at attempts', function () {
        $quiz = Quiz::factory()->published()->create();
        QuizAttempt::factory()->for($quiz)->create();

        expect(Gate::forUser(User::factory()->teaching($quiz->subject)->create())->inspect('delete', $quiz))
            ->toEqual(Response::denyAsNotFound());
    });

    it('denies the teacher when the administrator revoked the permission', function () {
        $quiz = Quiz::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes);

        expect(Gate::forUser($quiz->teacher)->inspect('delete', $quiz))
            ->toEqual(Response::deny('You do not have permission to create and manage quizzes.'));
    });
});
