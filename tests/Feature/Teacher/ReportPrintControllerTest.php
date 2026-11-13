<?php

use App\Enums\Permission;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\RolePermissions;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Tests\Feature\Services\Reports\ReportsFixture;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(ReportsFixture::NOW));
});

it('prints the teacher\'s report with the school header and signature lines', function () {
    $school = ReportsFixture::create();
    app(Settings::class)->setMany(['school_name' => 'Mabini Elementary School', 'school_head' => 'Dr. Erlinda M. Castillo']);

    $response = $this->actingAs($school->santos)
        ->get(route('teacher.reports.print', ['report' => 'classroom-summary', 'grade_level' => 5]));

    $response->assertOk()
        ->assertViewIs('teacher.reports.print')
        ->assertSeeText('Mabini Elementary School')
        ->assertSeeText('Classroom summary')
        ->assertSeeInOrder(['Classrooms:', 'Your active classrooms', 'Grade:', 'Grade 5', 'Rows:', '2'])
        ->assertSeeInOrder(['Health 5 – Rizal', 'Science 5 – Rizal'])
        ->assertDontSeeText('Health 6 – Mabini')
        ->assertSeeInOrder(['Prepared by:', 'Maricel Santos', 'Teacher', 'Noted by:', 'Dr. Erlinda M. Castillo', 'School Head'])
        ->assertSee(e(route('teacher.reports.show', ['report' => 'classroom-summary', 'grade_level' => 5])), false);
});

it('forbids printing without the export permission', function () {
    $school = ReportsFixture::create();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ExportReports);

    $this->actingAs($school->santos)
        ->get(route('teacher.reports.print', ReportType::StudentPerformance))
        ->assertForbidden()
        ->assertSeeText('You do not have permission to export reports.');
});

it('answers the user accounts report with 404', function () {
    $this->actingAs(User::factory()->teaching()->create())
        ->get(route('teacher.reports.print', ReportType::UserAccounts))
        ->assertNotFound();
});

it('rejects an unassigned subject and returns to the report preview', function () {
    $school = ReportsFixture::create();

    $this->actingAs($school->reyes)
        ->get(route('teacher.reports.print', ['report' => 'student-performance', 'subject_id' => $school->science->id]))
        ->assertRedirect(route('teacher.reports.show', ReportType::StudentPerformance))
        ->assertSessionHasErrors(['subject_id' => 'The selected subject is invalid.']);
});

it('sends guests to the sign-in page', function () {
    $this->get(route('teacher.reports.print', ReportType::StudentPerformance))->assertRedirectToRoute('login');
});

it('forbids the other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('teacher.reports.print', ReportType::StudentPerformance))
        ->assertForbidden();
})->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);
