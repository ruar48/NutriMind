<?php

use App\Enums\GradeLevel;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use App\Services\Analytics\AnalyticsPeriod;
use Carbon\CarbonImmutable;
use Tests\Feature\Services\Analytics\AnalyticsSchoolFixture;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(AnalyticsSchoolFixture::NOW));
});

it('sends guests to the sign-in page', function () {
    $this->get(route('admin.analytics.index'))->assertRedirectToRoute('login');
});

it('forbids the other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('admin.analytics.index'))
        ->assertForbidden();
})->with([
    'teacher' => UserRole::Teacher,
    'parent' => UserRole::Parent,
    'learner' => UserRole::Student,
]);

it('shows designed empty states for a school without data', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.analytics.index'))
        ->assertOk()
        ->assertViewIs('admin.analytics.index')
        ->assertViewHas('period', fn (AnalyticsPeriod $period): bool => $period->key === '30d')
        ->assertSeeText('No learning activity in this view')
        ->assertSeeText('No quiz results in this period yet.')
        ->assertSeeText('No tricky questions yet')
        ->assertSeeText('Everyone is on track')
        ->assertSeeText('No active teachers yet')
        ->assertSeeText('Points appear here once learners play the game.');
});

it('shows the full metric set of the last 30 days', function () {
    $school = AnalyticsSchoolFixture::create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.analytics.index'))
        ->assertOk()
        ->assertViewHas('overview', fn (array $overview): bool => $overview['averageScore'] === 75.0 && $overview['passRate'] === 60.0 && $overview['attemptsDelta'] === 4)
        ->assertViewHas('teachers', fn ($teachers): bool => $teachers->total() === 2)
        ->assertDontSeeText('No learning activity in this view')
        ->assertSeeText('Oct 15 – Nov 13, 2026')
        ->assertSeeText('Which food is a GO food?')
        ->assertSeeText('“Fruit”', false)
        ->assertSee('data-insights-at-risk="'.$school->ben->id.'"', false)
        ->assertSee(route('admin.students.show', $school->ben), false)
        ->assertSee(route('admin.teachers.show', $school->teresa), false)
        ->assertSeeText('The Healthy Plate')
        ->assertSeeText($school->health6->name)
        ->assertSee('data-insights-grade="6"', false)
        ->assertSee('data-insights-completion="missions"', false)
        ->assertDontSeeText($school->dina->name);
});

it('limits the figures to the chosen grade and subject', function () {
    $school = AnalyticsSchoolFixture::create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.analytics.index', ['grade' => GradeLevel::Five->value, 'subject' => $school->science->id]))
        ->assertOk()
        ->assertViewHas('grade', GradeLevel::Five)
        ->assertViewHas('subject', fn (Subject $subject): bool => $subject->is($school->science))
        ->assertViewHas('overview', fn (array $overview): bool => $overview['averageScore'] === 65.0 && $overview['learners'] === 2)
        ->assertViewHas('teachers', fn ($teachers): bool => $teachers->pluck('id')->all() === [$school->teresa->id])
        ->assertDontSeeText('Which food is a GO food?');
});

it('measures the chosen period', function (array $query, string $from, string $to, string $label) {
    $schoolYear = SchoolYear::factory()->active()->create(['name' => '2026–2027']);
    AcademicTerm::factory()->for($schoolYear)->active()->create(['name' => '2nd Quarter', 'starts_on' => '2026-09-01', 'ends_on' => '2026-11-20']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.analytics.index', $query))
        ->assertOk()
        ->assertViewHas('period', fn (AnalyticsPeriod $period): bool => $period->from->toDateString() === $from && $period->to->toDateString() === $to && $period->label === $label);
})->with([
    'last 7 days' => [['period' => '7d'], '2026-11-07', '2026-11-13', 'Last 7 days'],
    'last 90 days' => [['period' => '90d'], '2026-08-16', '2026-11-13', 'Last 90 days'],
    'current quarter' => [['period' => 'quarter'], '2026-09-01', '2026-11-20', '2nd Quarter'],
    'custom range' => [['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31'], '2026-10-01', '2026-10-31', 'Oct 1 – Oct 31, 2026'],
    'custom range without dates' => [['period' => 'custom'], '2026-10-15', '2026-11-13', 'Oct 15 – Nov 13, 2026'],
]);

it('ignores dates unless a custom range is chosen', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.analytics.index', ['period' => '7d', 'from' => 'not-a-date', 'to' => '2099-01-01']))
        ->assertOk()
        ->assertSessionHasNoErrors();
});

it('rejects invalid filters with a message and shows the default view', function (array $query, string $field, string $message) {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.analytics.index', $query))
        ->assertRedirectToRoute('admin.analytics.index')
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'unknown grade' => [['grade' => 7], 'grade', 'Choose Grade 5 or Grade 6.'],
    'grade that is not a number' => [['grade' => 'six'], 'grade', 'Choose Grade 5 or Grade 6.'],
    'unknown subject' => [['subject' => 999999], 'subject', 'Choose a subject from the list.'],
    'unknown period' => [['period' => 'forever'], 'period', 'Choose a period from the list.'],
    'current quarter without one' => [['period' => 'quarter'], 'period', 'Choose a period from the list.'],
    'malformed start date' => [['period' => 'custom', 'from' => '2026-13-01'], 'from', 'Enter a valid start date.'],
    'start date in the future' => [['period' => 'custom', 'from' => '2026-11-14'], 'from', 'The start date cannot be in the future.'],
    'malformed end date' => [['period' => 'custom', 'to' => '13/11/2026'], 'to', 'Enter a valid end date.'],
    'end date in the future' => [['period' => 'custom', 'to' => '2026-12-01'], 'to', 'The end date cannot be in the future.'],
    'end before start' => [['period' => 'custom', 'from' => '2026-11-10', 'to' => '2026-11-01'], 'to', 'The end date must be on or after the start date.'],
    'longer than a year' => [['period' => 'custom', 'from' => '2025-11-12', 'to' => '2026-11-13'], 'to', 'Choose a date range of 366 days or fewer.'],
]);

it('accepts a custom range of exactly 366 days', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.analytics.index', ['period' => 'custom', 'from' => '2025-11-13', 'to' => '2026-11-13']))
        ->assertOk()
        ->assertViewHas('period', fn (AnalyticsPeriod $period): bool => $period->days() === 366);
});

it('explains the refused filters on the page it falls back to', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->followingRedirects()
        ->get(route('admin.analytics.index', ['grade' => 9]))
        ->assertOk()
        ->assertSeeText('Some filters were not valid, so the default view is shown')
        ->assertSeeText('Choose Grade 5 or Grade 6.');
});

it('pages through the teacher activity', function () {
    foreach (range(1, 12) as $number) {
        User::factory()->teacher()->create(['name' => sprintf('Teacher %02d', $number)]);
    }

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.analytics.index', ['teachers' => 2]))
        ->assertOk()
        ->assertViewHas('teachers', fn ($teachers): bool => $teachers->currentPage() === 2 && $teachers->pluck('name')->all() === ['Teacher 11', 'Teacher 12'])
        ->assertSeeText('Teacher 12')
        ->assertDontSeeText('Teacher 03');
});
