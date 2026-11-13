<?php

use App\Enums\GuardianRelationship;
use App\Enums\UserRole;
use App\Http\Controllers\Admin\ParentController;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

$otherRoles = [
    'teacher' => UserRole::Teacher,
    'parent' => UserRole::Parent,
    'learner' => UserRole::Student,
];

describe('index', function () use ($otherRoles) {
    it('lists parents with their linked learners', function () {
        $sofia = User::factory()->student()->create(['name' => 'Sofia Navarro']);
        $miguel = User::factory()->student()->create(['name' => 'Miguel Navarro']);
        $ana = User::factory()->parent()
            ->guardianOf($sofia, GuardianRelationship::Mother)
            ->guardianOf($miguel, GuardianRelationship::Mother)
            ->create(['name' => 'Ana Navarro', 'last_login_at' => null]);
        User::factory()->teacher()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.parents.index'))
            ->assertOk()
            ->assertViewIs('admin.parents.index')
            ->assertViewHas('parents', fn (LengthAwarePaginator $parents): bool => $parents->total() === 1
                && $parents->first()->is($ana)
                && $parents->first()->linkedStudents->pluck('name')->all() === ['Miguel Navarro', 'Sofia Navarro'])
            ->assertSeeText(['Ana Navarro', 'Miguel Navarro', 'Sofia Navarro', 'Mother', 'Never'])
            ->assertSee('href="'.route('admin.students.show', $sofia).'"', false);
    });

    it('shows at most three learner chips and counts the rest', function () {
        $parent = User::factory()->parent();

        foreach (User::factory()->student()->count(4)->create() as $learner) {
            $parent = $parent->guardianOf($learner);
        }

        $parent->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.parents.index'))
            ->assertOk()
            ->assertSee('data-learner-chips="4"', false)
            ->assertSeeText('+1');
    });

    it('filters by search, status and linked learners', function (array $query, array $expectedNames) {
        $learner = User::factory()->student()->create();
        User::factory()->parent()->guardianOf($learner)->create(['name' => 'Ana Navarro', 'email' => 'ana@example.com']);
        User::factory()->parent()->create(['name' => 'Lorna Cruz']);
        User::factory()->parent()->inactive()->create(['name' => 'Rodel Javier']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.parents.index', $query))
            ->assertViewHas('parents', fn (LengthAwarePaginator $parents): bool => $parents->pluck('name')->all() === $expectedNames);
    })->with([
        'search by email' => [['search' => 'ana@'], ['Ana Navarro']],
        'inactive' => [['status' => 'inactive'], ['Rodel Javier']],
        'with learners' => [['linked' => 'yes'], ['Ana Navarro']],
        'without learners' => [['linked' => 'no'], ['Lorna Cruz', 'Rodel Javier']],
        'tampered filters' => [['linked' => ['yes'], 'status' => 'gone'], ['Ana Navarro', 'Lorna Cruz', 'Rodel Javier']],
    ]);

    it('renders several parents without lazy loading', function () {
        foreach (range(1, 4) as $index) {
            User::factory()->parent()->guardianOf(User::factory()->student()->create())->create();
        }

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.parents.index'))
            ->assertOk()
            ->assertSee('data-parent-row', false);
    });

    it('shows an empty state without parents and one for filters without matches', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.parents.index'))->assertOk()->assertSeeText('No parent accounts yet');
        $this->actingAs($admin)->get(route('admin.parents.index', ['search' => 'nobody']))->assertOk()->assertSeeText('No parents match your filters');
    });

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.parents.index'))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.parents.index'))
            ->assertForbidden();
    })->with($otherRoles);
});

describe('show', function () use ($otherRoles) {
    it('shows the profile, the linked learners and the link search prompt', function () {
        $classroom = Classroom::factory()->create(['name' => 'Health 5 – Sampaguita']);
        $sofia = User::factory()->student()->enrolledIn($classroom)->create(['name' => 'Sofia Navarro']);
        $parent = User::factory()->parent()->guardianOf($sofia, GuardianRelationship::Mother)->create(['name' => 'Ana Navarro', 'email' => 'ana@example.com']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.parents.show', $parent))
            ->assertOk()
            ->assertViewIs('admin.parents.show')
            ->assertViewHas('results', null)
            ->assertSeeText(['Ana Navarro', 'ana@example.com', 'Sofia Navarro', 'Mother', 'Health 5 – Sampaguita', 'Start typing to find the learner.'])
            ->assertSee('action="'.route('admin.parents.children.destroy', [$parent, $sofia]).'"', false)
            ->assertSee('action="'.route('admin.users.password.update', $parent).'"', false);
    });

    it('finds learners to link by name, username or LRN and marks the linked ones', function (string $term) {
        $linked = User::factory()->student()->create(['name' => 'Sofia Navarro', 'username' => 'sofia_navarro']);
        $linked->studentProfile->update(['lrn' => '136742210077']);
        $other = User::factory()->student()->create(['name' => 'Sofia Cruz', 'username' => 'sofia_cruz']);
        $other->studentProfile->update(['lrn' => '136742210078']);
        User::factory()->student()->create(['name' => 'Miguel Reyes', 'username' => 'miguel_reyes']);
        User::factory()->parent()->create(['name' => 'Sofia Parent']);
        $parent = User::factory()->parent()->guardianOf($linked)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.parents.show', [$parent, 'learner_search' => $term]))
            ->assertOk()
            ->assertViewHas('learnerSearch', $term)
            ->assertViewHas('results', fn ($results): bool => $results->pluck('name')->all() === ['Sofia Cruz', 'Sofia Navarro'])
            ->assertSee('data-learner-result="'.$other->id.'"', false)
            ->assertSee('name="student_id" value="'.$other->id.'"', false)
            ->assertDontSee('name="student_id" value="'.$linked->id.'"', false)
            ->assertSeeText('Already linked');
    })->with([
        'name' => 'Sofia',
        'username' => 'sofia_',
        'LRN' => '13674221007',
    ]);

    it('lists at most ten matches and says when there are more', function () {
        User::factory()->student()->count(ParentController::LEARNER_SEARCH_LIMIT + 1)->sequence(fn ($sequence) => ['name' => 'Learner '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT)])->create();
        $parent = User::factory()->parent()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.parents.show', [$parent, 'learner_search' => 'Learner']))
            ->assertOk()
            ->assertViewHas('results', fn ($results): bool => $results->count() === 10 && $results->last()->name === 'Learner 09')
            ->assertViewHas('hasMoreResults', true)
            ->assertSeeText('Showing the first 10 matches.');
    });

    it('says when no learner matches', function () {
        $parent = User::factory()->parent()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.parents.show', [$parent, 'learner_search' => 'zzz']))
            ->assertOk()
            ->assertSeeText('No learners match “zzz”');
    });

    it('ignores a tampered search', function () {
        $parent = User::factory()->parent()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.parents.show', [$parent, 'learner_search' => ['x']]))
            ->assertOk()
            ->assertViewHas('learnerSearch', null);
    });

    it('shows the empty state of a parent without linked learners', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.parents.show', User::factory()->parent()->create()))
            ->assertOk()
            ->assertSeeText('No learners linked yet');
    });

    it('reports accounts that are not parents as missing', function (Closure $account) {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.parents.show', $account()))
            ->assertNotFound();
    })->with([
        'teacher' => fn () => User::factory()->teacher()->create(),
        'learner' => fn () => User::factory()->student()->create(),
        'unknown id' => fn () => 999999,
    ]);

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.parents.show', User::factory()->parent()->create()))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.parents.show', User::factory()->parent()->create()))
            ->assertForbidden();
    })->with($otherRoles);
});
