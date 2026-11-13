<?php

use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Classroom;
use App\Models\ForumReport;
use App\Models\QuizAttempt;
use App\Models\SchoolYear;
use App\Models\User;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Services\Analytics\AnalyticsSchoolFixture;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(AnalyticsSchoolFixture::NOW));
});

it('sends guests to the sign-in page', function () {
    $this->get(route('admin.dashboard'))->assertRedirectToRoute('login');
});

it('forbids the other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
})->with([
    'teacher' => UserRole::Teacher,
    'parent' => UserRole::Parent,
    'learner' => UserRole::Student,
]);

it('shows a new school the setup checklist and empty states', function () {
    $admin = User::factory()->admin()->create(['name' => 'Liza Bautista']);
    app(Settings::class)->set('school_name', 'San Isidro Elementary School');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewIs('admin.dashboard.index')
        ->assertSeeText('Good morning, Liza Bautista')
        ->assertSeeText('Finish setting up San Isidro Elementary School')
        ->assertSee('data-insights-setup="5"', false)
        ->assertSee('data-setup-step="school-year" data-done="false"', false)
        ->assertSeeText('No active quarter')
        ->assertSeeText('Engagement appears here once learners play in this period.')
        ->assertSeeText('No sign-ins yet')
        ->assertSeeText('All clear')
        ->assertSee('data-chart-empty', false);
});

it('hides the setup checklist once the school is set up', function () {
    $schoolYear = SchoolYear::factory()->active()->create(['name' => '2026–2027']);
    AcademicTerm::factory()->for($schoolYear)->active()->create(['name' => '2nd Quarter']);
    User::factory()->student()->enrolledIn(Classroom::factory()->create())->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('data-insights-setup', false)
        ->assertSeeText('2nd Quarter · SY 2026–2027');
});

it('shows the school’s numbers for the last 30 days', function () {
    $school = AnalyticsSchoolFixture::create();
    ForumReport::factory()->create();
    $admin = User::factory()->admin()->create(['last_login_at' => now()->subMinutes(5)]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertViewHas('headcounts', fn (array $headcounts): bool => $headcounts['learners'] === 4 && $headcounts['teachers'] === 2 && $headcounts['classrooms'] === 3)
        ->assertViewHas('monthOverview', fn (array $overview): bool => $overview['averageScore'] === 75.0 && $overview['averageScoreDelta'] === 17.5)
        ->assertViewHas('weekOverview', fn (array $overview): bool => $overview['attempts'] === 4)
        ->assertViewHas('atRisk', fn (array $atRisk): bool => $atRisk['count'] === 3 && $atRisk['learners'] === [])
        ->assertSeeText('75.0%')
        ->assertSeeText('1 of 2 started missions')
        ->assertSee('data-chart="line"', false)
        ->assertSee('data-chart="stacked"', false)
        ->assertSee('data-chart="hbar"', false)
        ->assertSeeText($school->health6->name)
        ->assertSee(route('admin.users.edit', $admin), false)
        ->assertSee('data-insights-moderation="reports"', false)
        ->assertSee(route('admin.forum.reports.index'), false)
        ->assertSee(route('admin.students.import.create'), false);
});

it('runs the same number of queries however many classrooms have results', function () {
    $school = AnalyticsSchoolFixture::create();
    $admin = User::factory()->admin()->create();

    $countQueries = function () use ($admin): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $before = $countQueries();

    foreach (range(1, 3) as $extra) {
        $classroom = Classroom::factory()->for($school->teresa, 'teacher')->for($school->health)->create();
        QuizAttempt::factory()->for($school->plateQuiz)->for(User::factory()->student()->enrolledIn($classroom), 'student')->for($classroom)->create(['submitted_at' => now()->subDay()]);
    }

    expect($countQueries())->toBe($before);
});
