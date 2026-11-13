<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\SchoolYear;
use App\Models\User;
use App\Services\RolePermissions;
use App\Services\Settings;
use App\View\Composers\LayoutComposer;
use Illuminate\Support\Facades\DB;

/**
 * Compose the layout data for the signed-in user (or a guest).
 *
 * @return array<string, mixed>
 */
function layoutDataFor(?User $user): array
{
    if ($user !== null) {
        test()->actingAs($user);
    }

    $view = view('components.layouts.app');
    app(LayoutComposer::class)->compose($view);

    return $view->getData();
}

it('describes the portal of each role', function (UserRole $role, string $label, string $color) {
    $data = layoutDataFor(User::factory()->{$role->value}()->create());

    expect($data['portal'])->toBe(['key' => $role->value, 'label' => $label, 'color' => $color]);
})->with([
    'administrator' => [UserRole::Admin, 'Administrator Portal', 'grape'],
    'teacher' => [UserRole::Teacher, 'Teacher Portal', 'leaf'],
    'parent' => [UserRole::Parent, 'Parent Portal', 'carrot'],
]);

it('shares the school name from the settings', function () {
    app(Settings::class)->set('school_name', 'Sampaguita Elementary School');

    expect(layoutDataFor(User::factory()->teacher()->create())['schoolName'])->toBe('Sampaguita Elementary School');
});

it('shares the active school year and its active term', function () {
    $schoolYear = SchoolYear::factory()->active()->create(['name' => '2026–2027']);
    AcademicTerm::factory()->for($schoolYear)->create(['name' => '1st Quarter']);
    $activeTerm = AcademicTerm::factory()->for($schoolYear)->active()->create(['name' => '2nd Quarter']);

    $data = layoutDataFor(User::factory()->teacher()->create());

    expect($data['activeSchoolYear'])->toBeInstanceOf(SchoolYear::class)
        ->and($data['activeSchoolYear']->is($schoolYear))->toBeTrue()
        ->and($data['activeTerm']->is($activeTerm))->toBeTrue()
        ->and($data['activeTerm']->relationLoaded('schoolYear'))->toBeTrue();
});

it('shares no term when the active school year has no active term', function () {
    $schoolYear = SchoolYear::factory()->active()->create();
    AcademicTerm::factory()->for($schoolYear)->create();
    AcademicTerm::factory()->for(SchoolYear::factory())->active()->create();

    $data = layoutDataFor(User::factory()->teacher()->create());

    expect($data['activeSchoolYear']->is($schoolYear))->toBeTrue()
        ->and($data['activeTerm'])->toBeNull();
});

it('shares no school year or term while no school year is active', function () {
    AcademicTerm::factory()->for(SchoolYear::factory())->active()->create();

    $data = layoutDataFor(User::factory()->admin()->create());

    expect($data['activeSchoolYear'])->toBeNull()
        ->and($data['activeTerm'])->toBeNull();
});

it('looks up the school year and term once per request', function () {
    $schoolYear = SchoolYear::factory()->active()->create();
    AcademicTerm::factory()->for($schoolYear)->active()->create();
    $user = User::factory()->admin()->create();
    DB::enableQueryLog();

    layoutDataFor($user);
    $data = layoutDataFor($user);

    $academicQueries = collect(DB::getQueryLog())->pluck('query')
        ->filter(fn (string $sql): bool => str_contains($sql, 'school_years') || str_contains($sql, 'academic_terms'));
    expect($academicQueries)->toHaveCount(2)
        ->and($data['activeSchoolYear']->is($schoolYear))->toBeTrue();
});

it('shares the announcement without surrounding whitespace', function () {
    app(Settings::class)->set('announcement', "  Quarterly assessments start on Monday.\n");

    expect(layoutDataFor(User::factory()->parent()->create())['announcement'])->toBe('Quarterly assessments start on Monday.');
});

it('shares no announcement when it is empty', function (?string $announcement) {
    app(Settings::class)->set('announcement', $announcement);

    expect(layoutDataFor(User::factory()->parent()->create())['announcement'])->toBeNull();
})->with(['not set' => null, 'blank' => '   ']);

it('points the global search of each portal to its search page', function (UserRole $role, string $routeName, string $placeholder) {
    $data = layoutDataFor(User::factory()->{$role->value}()->create());

    expect($data['searchUrl'])->toBe(route($routeName))
        ->and($data['searchPlaceholder'])->toBe($placeholder);
})->with([
    'administrator' => [UserRole::Admin, 'admin.users.index', 'Search users by name, email or username'],
    'teacher' => [UserRole::Teacher, 'teacher.students.index', 'Search learners'],
    'parent' => [UserRole::Parent, 'forum.index', 'Search the parent forum'],
]);

it('offers parents no search when they cannot open the forum', function () {
    $parent = User::factory()->parent()->create();
    app(RolePermissions::class)->revoke(UserRole::Parent, Permission::AccessForum);

    $data = layoutDataFor($parent);

    expect($data['searchUrl'])->toBeNull()
        ->and($data['searchPlaceholder'])->toBe('Search…');
});

it('shares no portal or search with guests', function () {
    $data = layoutDataFor(null);

    expect($data)->not->toHaveKey('portal')
        ->and($data['searchUrl'])->toBeNull()
        ->and($data['schoolName'])->toBe('NutriMind Elementary School');
});

it('renders the shell data on the account settings page', function () {
    $schoolYear = SchoolYear::factory()->active()->create(['name' => '2026–2027']);
    AcademicTerm::factory()->for($schoolYear)->active()->create(['name' => '2nd Quarter']);
    app(Settings::class)->setMany(['school_name' => 'Sampaguita Elementary School', 'announcement' => 'Quarterly assessments start on Monday.']);
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('account.edit'))
        ->assertOk()
        ->assertSeeText('Administrator Portal')
        ->assertSeeText('Sampaguita Elementary School')
        ->assertSeeText('SY 2026–2027')
        ->assertSeeText('2nd Quarter')
        ->assertSeeText('Quarterly assessments start on Monday.')
        ->assertSee('action="'.route('admin.users.index').'"', false)
        ->assertSee('placeholder="Search users by name, email or username"', false);
});
