<?php

use App\Enums\GradeLevel;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Services\CsvExport;
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
function adminReportCsv(string $content): array
{
    $lines = preg_split("/\r\n/", rtrim(substr($content, strlen(CsvExport::BYTE_ORDER_MARK)), "\r\n"));

    return array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''), $lines);
}

it('downloads every report as a dated CSV file with a byte order mark', function (ReportType $type, string $firstHeading) {
    ReportsFixture::create();

    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.export', $type));

    $response->assertOk()->assertDownload($type->value.'-2026-11-13.csv');
    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
        ->and($response->streamedContent())->toStartWith(CsvExport::BYTE_ORDER_MARK)
        ->and(adminReportCsv($response->streamedContent())[0][0])->toBe($firstHeading);
})->with([
    'learner performance' => [ReportType::StudentPerformance, 'Learner'],
    'quiz results' => [ReportType::QuizResults, 'Quiz'],
    'mission progress' => [ReportType::MissionProgress, 'Mission'],
    'classroom summary' => [ReportType::ClassroomSummary, 'Classroom'],
    'user accounts' => [ReportType::UserAccounts, 'Name'],
]);

it('writes the headings and every row of the report', function () {
    ReportsFixture::create();

    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.export', ReportType::QuizResults));

    expect(adminReportCsv($response->streamedContent()))->toBe([
        ['Quiz', 'Subject', 'Classrooms', 'Teacher', 'Attempts', 'Learners attempted', 'Learners assigned', 'Average score', 'Pass rate', 'Highest score', 'Lowest score', 'Due'],
        ['Food Groups Quiz', 'Health', 'Health 6 – Mabini', 'Lorna Reyes', '1', '1', '1', '95', '100', '95', '95', '2026-11-20'],
        ['Healthy Plate Quiz', 'Health', 'Health 5 – Rizal', 'Maricel Santos', '3', '2', '2', '75', '50', '80', '70', '2026-11-10'],
        ['Digestion Quiz', 'Science', 'Science 5 – Rizal', 'Maricel Santos', '1', '1', '1', '90', '100', '90', '90', '2026-11-05'],
    ]);
});

it('exports all rows, not only the first preview page', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    User::factory()->student(GradeLevel::Five)->enrolledIn($classroom)->count(30)->create();

    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.export', ReportType::StudentPerformance));

    expect(adminReportCsv($response->streamedContent()))->toHaveCount(31);
});

it('applies the filters to the export', function () {
    $school = ReportsFixture::create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reports.export', ['report' => 'student-performance', 'classroom_id' => $school->health6->id]));

    expect(array_column(array_slice(adminReportCsv($response->streamedContent()), 1), 0))->toBe(['Carla Santos']);
});

it('neutralizes spreadsheet formulas in exported text', function () {
    $admin = User::factory()->admin()->create(['name' => '=HYPERLINK("http://evil.test")']);

    $response = $this->actingAs($admin)->get(route('admin.reports.export', ReportType::UserAccounts));

    expect(adminReportCsv($response->streamedContent())[1][0])->toBe('\'=HYPERLINK("http://evil.test")');
});

it('rejects invalid filters and returns to the report preview', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reports.export', ['report' => 'classroom-summary', 'subject_id' => 999999]))
        ->assertRedirect(route('admin.reports.show', ReportType::ClassroomSummary))
        ->assertSessionHasErrors(['subject_id' => 'The selected subject is invalid.']);
});

it('answers an unknown report with 404', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/reports/attendance/export')
        ->assertNotFound();
});

it('sends guests to the sign-in page', function () {
    $this->get(route('admin.reports.export', ReportType::StudentPerformance))->assertRedirectToRoute('login');
});

it('forbids the other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('admin.reports.export', ReportType::StudentPerformance))
        ->assertForbidden();
})->with([UserRole::Teacher, UserRole::Parent, UserRole::Student]);
