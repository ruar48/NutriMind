<?php

use App\Enums\GradeLevel;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Tests\Feature\Services\Reports\ReportsFixture;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(ReportsFixture::NOW));
});

it('prints the report with the school header, filters, rows and signature lines', function () {
    $school = ReportsFixture::create();
    app(Settings::class)->setMany([
        'school_name' => 'Mabini Elementary School',
        'school_address' => 'Marikina City',
        'school_id' => '136742',
        'school_head' => 'Dr. Erlinda M. Castillo',
    ]);
    $admin = User::factory()->admin()->create(['name' => 'Josefina Ramos']);

    $response = $this->actingAs($admin)->get(route('admin.reports.print', ['report' => 'student-performance', 'subject_id' => $school->health->id]));

    $response->assertOk()
        ->assertViewIs('admin.reports.print')
        ->assertSeeText('Mabini Elementary School')
        ->assertSeeText('Marikina City')
        ->assertSeeText('School ID: 136742')
        ->assertSeeText('Learner performance')
        ->assertSeeInOrder(['Classrooms:', 'All active classrooms', 'Subject:', 'Health', 'Rows:', '3'])
        ->assertSeeInOrder(['Learners', '3', 'Average score', '81.7%'])
        ->assertSeeInOrder(['Ana Cruz', 'Health 5 – Rizal', '80%', 'Ben Diaz', 'Carla Santos'])
        ->assertSeeInOrder(['Prepared by:', 'Josefina Ramos', 'Administrator', 'Noted by:', 'Dr. Erlinda M. Castillo', 'School Head'])
        ->assertSee('data-print', false)
        ->assertSee(e(route('admin.reports.show', ['report' => 'student-performance', 'subject_id' => $school->health->id])), false);
});

it('prints every row on one page', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    User::factory()->student(GradeLevel::Five)->enrolledIn($classroom)->count(30)->create();

    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.print', ReportType::StudentPerformance));

    expect(substr_count($response->getContent(), '<tr data-report-print-row'))->toBe(30);
});

it('leaves the noted-by line blank without a school head', function () {
    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.print', ReportType::UserAccounts));

    $response->assertOk()
        ->assertSeeText('Noted by:')
        ->assertSeeText('School Head');
    expect($response->getContent())->toMatch('/Noted by:<\/p>\s*<div[^>]*><\/div>\s*<p[^>]*><\/p>/');
});

it('prints a message instead of the table when nothing matches', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reports.print', ['report' => 'quiz-results', 'grade_level' => 6]))
        ->assertOk()
        ->assertSee('data-report-print-empty', false)
        ->assertSeeText('No rows match these filters.')
        ->assertDontSee('data-report-print-table', false);
});

it('rejects invalid filters and returns to the report preview', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reports.print', ['report' => 'mission-progress', 'grade_level' => 4]))
        ->assertRedirect(route('admin.reports.show', ReportType::MissionProgress))
        ->assertSessionHasErrors(['grade_level' => 'The selected grade level is invalid.']);
});

it('sends guests to the sign-in page', function () {
    $this->get(route('admin.reports.print', ReportType::StudentPerformance))->assertRedirectToRoute('login');
});

it('forbids the other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('admin.reports.print', ReportType::StudentPerformance))
        ->assertForbidden();
})->with([UserRole::Teacher, UserRole::Parent, UserRole::Student]);
