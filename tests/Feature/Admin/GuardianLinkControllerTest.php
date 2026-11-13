<?php

use App\Enums\GuardianRelationship;
use App\Enums\UserRole;
use App\Models\GuardianStudent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$otherRoles = [
    'teacher' => UserRole::Teacher,
    'parent' => UserRole::Parent,
    'learner' => UserRole::Student,
];

describe('store', function () use ($otherRoles) {
    it('links the learner with the relationship', function () {
        $parent = User::factory()->parent()->create(['name' => 'Ana Navarro']);
        $learner = User::factory()->student()->create(['name' => 'Sofia Navarro']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.parents.children.store', $parent), ['student_id' => $learner->id, 'relationship' => 'mother'])
            ->assertRedirect(route('admin.parents.show', $parent))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Sofia Navarro is now linked to Ana Navarro (Mother).');

        expect($parent->linkedStudents()->sole())
            ->is($learner)->toBeTrue()
            ->pivot->relationship->toBe(GuardianRelationship::Mother);
    });

    it('refuses a learner who is already linked', function () {
        $learner = User::factory()->student()->create();
        $parent = User::factory()->parent()->guardianOf($learner, GuardianRelationship::Father)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.parents.show', [$parent, 'learner_search' => 'x']))
            ->post(route('admin.parents.children.store', $parent), ['student_id' => $learner->id, 'relationship' => 'mother'])
            ->assertRedirect(route('admin.parents.show', [$parent, 'learner_search' => 'x']))
            ->assertSessionHasErrorsIn('linkLearner', ['student_id' => 'Already linked.']);

        expect($parent->linkedStudents()->sole()->pivot->relationship)->toBe(GuardianRelationship::Father);
    });

    it('refuses a link that a simultaneous request created first', function () {
        $parent = User::factory()->parent()->create();
        $learner = User::factory()->student()->create();
        GuardianStudent::creating(function () use ($parent, $learner): void {
            DB::table('guardian_student')->insert([
                'guardian_id' => $parent->id,
                'student_id' => $learner->id,
                'relationship' => 'father',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.parents.show', $parent))
            ->post(route('admin.parents.children.store', $parent), ['student_id' => $learner->id, 'relationship' => 'mother'])
            ->assertRedirect(route('admin.parents.show', $parent))
            ->assertSessionHasErrorsIn('linkLearner', ['student_id' => 'Already linked.']);

        expect(DB::table('guardian_student')->count())->toBe(1);
    });

    it('rejects an invalid link', function (Closure $payload, string $field, string $message) {
        $parent = User::factory()->parent()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.parents.children.store', $parent), $payload())
            ->assertSessionHasErrorsIn('linkLearner', [$field => $message]);

        expect($parent->linkedStudents()->exists())->toBeFalse();
    })->with([
        'no learner' => [fn (): array => ['relationship' => 'mother'], 'student_id', 'Choose a learner to link.'],
        'not a number' => [fn (): array => ['student_id' => 'abc', 'relationship' => 'mother'], 'student_id', 'Choose a learner to link.'],
        'unknown learner' => [fn (): array => ['student_id' => 999999, 'relationship' => 'mother'], 'student_id', 'Choose a learner to link.'],
        'a teacher account' => [fn (): array => ['student_id' => User::factory()->teacher()->create()->id, 'relationship' => 'mother'], 'student_id', 'Choose a learner to link.'],
        'no relationship' => [fn (): array => ['student_id' => User::factory()->student()->create()->id], 'relationship', 'Choose how the parent is related to the learner.'],
        'unknown relationship' => [fn (): array => ['student_id' => User::factory()->student()->create()->id, 'relationship' => 'neighbor'], 'relationship', 'Choose how the parent is related to the learner.'],
    ]);

    it('reports accounts that are not parents as missing', function () {
        $teacher = User::factory()->teacher()->create();
        $learner = User::factory()->student()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.parents.children.store', $teacher), ['student_id' => $learner->id, 'relationship' => 'mother'])
            ->assertNotFound();

        $this->assertDatabaseMissing('guardian_student', ['student_id' => $learner->id]);
    });

    it('sends guests to the sign-in page', function () {
        $parent = User::factory()->parent()->create();

        $this->post(route('admin.parents.children.store', $parent), ['student_id' => User::factory()->student()->create()->id, 'relationship' => 'mother'])
            ->assertRedirectToRoute('login');

        expect($parent->linkedStudents()->exists())->toBeFalse();
    });

    it('forbids other roles', function (UserRole $role) {
        $parent = User::factory()->parent()->create();

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->post(route('admin.parents.children.store', $parent), ['student_id' => User::factory()->student()->create()->id, 'relationship' => 'mother'])
            ->assertForbidden();

        expect($parent->linkedStudents()->exists())->toBeFalse();
    })->with($otherRoles);
});

describe('destroy', function () use ($otherRoles) {
    it('unlinks the learner and keeps both accounts', function () {
        $learner = User::factory()->student()->create(['name' => 'Sofia Navarro']);
        $parent = User::factory()->parent()->guardianOf($learner)->create(['name' => 'Ana Navarro']);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.parents.children.destroy', [$parent, $learner]))
            ->assertRedirect(route('admin.parents.show', $parent))
            ->assertSessionHas('status', 'Sofia Navarro was unlinked from Ana Navarro.');

        expect($parent->linkedStudents()->exists())->toBeFalse();
        $this->assertModelExists($learner);
        $this->assertModelExists($parent);
    });

    it('goes back to the learner profile when asked', function () {
        $learner = User::factory()->student()->create();
        $parent = User::factory()->parent()->guardianOf($learner)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.parents.children.destroy', [$parent, $learner]), ['return_to' => 'student'])
            ->assertRedirect(route('admin.students.show', $learner));
    });

    it('ignores an unknown return target', function () {
        $learner = User::factory()->student()->create();
        $parent = User::factory()->parent()->guardianOf($learner)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.parents.children.destroy', [$parent, $learner]), ['return_to' => 'https://evil.example'])
            ->assertRedirect(route('admin.parents.show', $parent));
    });

    it('reports a learner who is not linked as missing', function () {
        $parent = User::factory()->parent()->create();
        $learner = User::factory()->student()->create();
        User::factory()->parent()->guardianOf($learner)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.parents.children.destroy', [$parent, $learner]))
            ->assertNotFound();

        expect($learner->guardians()->count())->toBe(1);
    });

    it('reports accounts of other roles as missing', function () {
        $teacher = User::factory()->teacher()->create();
        $parent = User::factory()->parent()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.parents.children.destroy', [$parent, $teacher]))
            ->assertNotFound();
    });

    it('sends guests to the sign-in page', function () {
        $learner = User::factory()->student()->create();
        $parent = User::factory()->parent()->guardianOf($learner)->create();

        $this->delete(route('admin.parents.children.destroy', [$parent, $learner]))->assertRedirectToRoute('login');

        expect($parent->linkedStudents()->exists())->toBeTrue();
    });

    it('forbids other roles', function (UserRole $role) {
        $learner = User::factory()->student()->create();
        $parent = User::factory()->parent()->guardianOf($learner)->create();

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->delete(route('admin.parents.children.destroy', [$parent, $learner]))
            ->assertForbidden();

        expect($parent->linkedStudents()->exists())->toBeTrue();
    })->with($otherRoles);
});
