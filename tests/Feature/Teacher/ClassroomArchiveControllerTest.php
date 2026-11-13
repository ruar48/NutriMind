<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Policies\ClassroomPolicy;
use App\Services\RolePermissions;

describe('store', function () {
    it('archives the classroom and keeps its learners', function () {
        $this->freezeSecond();
        $classroom = Classroom::factory()->create(['name' => 'Health 6 – Narra']);
        $learner = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();

        $this->actingAs($classroom->teacher)
            ->post(route('teacher.classrooms.archive.store', $classroom))
            ->assertRedirectToRoute('teacher.classrooms.show', $classroom)
            ->assertSessionHas('status', 'Health 6 – Narra was archived. Its learners and results stay available, read-only.');

        expect($classroom->refresh()->archived_at->toDateTimeString())->toBe(now()->toDateTimeString())
            ->and($classroom->students()->whereKey($learner->id)->exists())->toBeTrue();
    });

    it('refuses to archive a classroom twice', function () {
        $classroom = Classroom::factory()->archived()->create(['name' => 'Health 6 – Narra']);
        $archivedAt = $classroom->archived_at->toDateTimeString();
        $this->travel(1)->day();

        $this->actingAs($classroom->teacher)
            ->post(route('teacher.classrooms.archive.store', $classroom))
            ->assertRedirectToRoute('teacher.classrooms.show', $classroom)
            ->assertSessionHas('error', 'Health 6 – Narra is already archived.');

        expect($classroom->refresh()->archived_at->toDateTimeString())->toBe($archivedAt);
    });

    it('sends guests to the sign-in page', function () {
        $this->post(route('teacher.classrooms.archive.store', Classroom::factory()->create()))->assertRedirectToRoute('login');
    });

    it('forbids accounts of the other portals', function (UserRole $role) {
        $classroom = Classroom::factory()->create();

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->post(route('teacher.classrooms.archive.store', $classroom))
            ->assertForbidden();

        expect($classroom->refresh()->archived_at)->toBeNull();
    })->with([
        'administrator' => UserRole::Admin,
        'parent' => UserRole::Parent,
    ]);

    it('reports another teacher’s classroom as missing', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs(User::factory()->teaching($classroom->subject)->create())
            ->post(route('teacher.classrooms.archive.store', $classroom))
            ->assertNotFound();

        expect($classroom->refresh()->archived_at)->toBeNull();
    });

    it('refuses the owner once the subject was revoked', function () {
        $classroom = Classroom::factory()->revokedSubject()->create();

        $this->actingAs($classroom->teacher)
            ->post(route('teacher.classrooms.archive.store', $classroom))
            ->assertForbidden()
            ->assertSeeText(ClassroomPolicy::SUBJECT_REVOKED_MESSAGE);

        expect($classroom->refresh()->archived_at)->toBeNull();
    });

    it('forbids teachers without the manage classrooms permission', function () {
        $classroom = Classroom::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageClassrooms);

        $this->actingAs($classroom->teacher)
            ->post(route('teacher.classrooms.archive.store', $classroom))
            ->assertForbidden()
            ->assertSeeText(RolePermissions::deniedMessage(Permission::ManageClassrooms));

        expect($classroom->refresh()->archived_at)->toBeNull();
    });
});

describe('destroy', function () {
    it('restores an archived classroom', function () {
        $classroom = Classroom::factory()->archived()->create(['name' => 'Health 6 – Narra']);

        $this->actingAs($classroom->teacher)
            ->delete(route('teacher.classrooms.archive.destroy', $classroom))
            ->assertRedirectToRoute('teacher.classrooms.show', $classroom)
            ->assertSessionHas('status', 'Health 6 – Narra was restored.');

        expect($classroom->refresh()->archived_at)->toBeNull();
    });

    it('refuses to restore a classroom that is not archived', function () {
        $classroom = Classroom::factory()->create(['name' => 'Health 6 – Narra']);

        $this->actingAs($classroom->teacher)
            ->delete(route('teacher.classrooms.archive.destroy', $classroom))
            ->assertRedirectToRoute('teacher.classrooms.show', $classroom)
            ->assertSessionHas('error', 'Health 6 – Narra is not archived.');

        expect($classroom->refresh()->archived_at)->toBeNull();
    });

    it('sends guests to the sign-in page', function () {
        $this->delete(route('teacher.classrooms.archive.destroy', Classroom::factory()->archived()->create()))->assertRedirectToRoute('login');
    });

    it('reports another teacher’s classroom as missing', function () {
        $classroom = Classroom::factory()->archived()->create();

        $this->actingAs(User::factory()->teaching($classroom->subject)->create())
            ->delete(route('teacher.classrooms.archive.destroy', $classroom))
            ->assertNotFound();

        expect($classroom->refresh()->archived_at)->not->toBeNull();
    });

    it('forbids teachers without the manage classrooms permission', function () {
        $classroom = Classroom::factory()->archived()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageClassrooms);

        $this->actingAs($classroom->teacher)
            ->delete(route('teacher.classrooms.archive.destroy', $classroom))
            ->assertForbidden();

        expect($classroom->refresh()->archived_at)->not->toBeNull();
    });
});
