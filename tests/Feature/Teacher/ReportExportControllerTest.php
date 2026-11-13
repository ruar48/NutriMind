<?php

use App\Enums\Permission;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\CsvExport;
use App\Services\RolePermissions;
use Carbon\CarbonImmutable;
use Tests\Feature\Services\Reports\ReportsFixture;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(ReportsFixture::NOW));
});

/**
 * Parse a downloaded report CSV into rows of cells (the byte order mark removed).
 *
 * @return list<list<string>>
 */
function teacherReportCsv(string $content): array
{
    $lines = preg_split("/\r\n/", rtrim(substr($content, strlen(CsvExport::BYTE_ORDER_MARK)), "\r\n"));

    return array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''), $lines);
}

it('downloads the learner performance of the teacher\'s classrooms only', function () {
    $school = ReportsFixture::create();

    $response = $this->actingAs($school->santos)->get(route('teacher.reports.export', ReportType::StudentPerformance));

    $response->assertOk()->assertDownload('student-performance-2026-11-13.csv');
    expect($response->streamedContent())->toStartWith(CsvExport::BYTE_ORDER_MARK)
        ->and(teacherReportCsv($response->streamedContent()))->toBe([
            ['Learner', 'LRN', 'Grade', 'Classrooms', 'Quizzes taken', 'Average score', 'Highest score', 'Lowest score', 'Quizzes passed', 'Missions completed', 'Points', 'Level', 'Performance band'],
            ['Ana Cruz', '136742210001', 'Grade 5', 'Health 5 – Rizal; Science 5 – Rizal', '2', '85', '90', '80', '2', '3', '1240', '3', 'Very Satisfactory'],
            ['Ben Diaz', '136742210002', 'Grade 5', 'Health 5 – Rizal', '1', '70', '70', '70', '0', '0', '300', '1', 'Did Not Meet Expectations'],
        ]);
});

it('leaves the teacher column out of the teacher\'s quiz results', function () {
    $school = ReportsFixture::create();

    $response = $this->actingAs($school->reyes)->get(route('teacher.reports.export', ReportType::QuizResults));

    expect(teacherReportCsv($response->streamedContent()))->toBe([
        ['Quiz', 'Subject', 'Classrooms', 'Attempts', 'Learners attempted', 'Learners assigned', 'Average score', 'Pass rate', 'Highest score', 'Lowest score', 'Due'],
        ['Food Groups Quiz', 'Health', 'Health 6 – Mabini', '1', '1', '1', '95', '100', '95', '95', '2026-11-20'],
    ]);
});

it('applies the teacher\'s filters to the export', function () {
    $school = ReportsFixture::create();

    $response = $this->actingAs($school->santos)
        ->get(route('teacher.reports.export', ['report' => 'mission-progress', 'subject_id' => $school->science->id]));

    expect(array_column(array_slice(teacherReportCsv($response->streamedContent()), 1), 0))->toBe(['Water Warrior', 'Old Quest']);
});

it('forbids exporting without the export permission', function () {
    $school = ReportsFixture::create();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ExportReports);

    $this->actingAs($school->santos)
        ->get(route('teacher.reports.export', ReportType::StudentPerformance))
        ->assertForbidden()
        ->assertSeeText('You do not have permission to export reports.');
});

it('answers the user accounts report with 404', function () {
    $this->actingAs(User::factory()->teaching()->create())
        ->get(route('teacher.reports.export', ReportType::UserAccounts))
        ->assertNotFound();
});

it('rejects another teacher\'s classroom and returns to the report preview', function () {
    $school = ReportsFixture::create();

    $this->actingAs($school->santos)
        ->get(route('teacher.reports.export', ['report' => 'quiz-results', 'classroom_id' => $school->health6->id]))
        ->assertRedirect(route('teacher.reports.show', ReportType::QuizResults))
        ->assertSessionHasErrors(['classroom_id' => 'The selected classroom is invalid.']);
});

it('sends guests to the sign-in page', function () {
    $this->get(route('teacher.reports.export', ReportType::StudentPerformance))->assertRedirectToRoute('login');
});

it('forbids the other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('teacher.reports.export', ReportType::StudentPerformance))
        ->assertForbidden();
})->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);
