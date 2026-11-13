<?php

use App\Enums\GradeLevel;
use App\Enums\GuardianRelationship;
use App\Enums\UserRole;
use App\Http\Controllers\Admin\StudentExportController;
use App\Models\Classroom;
use App\Models\User;
use App\Services\CsvExport;
use Carbon\CarbonImmutable;

/**
 * Read the streamed CSV into rows of cells (the byte order mark removed).
 *
 * @return list<list<string>>
 */
$csvRows = function (string $content): array {
    expect(str_starts_with($content, CsvExport::BYTE_ORDER_MARK))->toBeTrue();

    $lines = preg_split('/\r\n/', trim(substr($content, strlen(CsvExport::BYTE_ORDER_MARK))));

    return array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''), $lines);
};

it('downloads the learners as CSV with classrooms, guardians and the computed level', function () use ($csvRows) {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    $classroom = Classroom::factory()->create(['name' => 'Health 5 – Sampaguita', 'grade_level' => GradeLevel::Five]);
    $archived = Classroom::factory()->archived()->create(['name' => 'Health 5 – Old', 'grade_level' => GradeLevel::Five]);
    $learner = User::factory()->student(GradeLevel::Five)->enrolledIn($classroom, $archived)->create(['name' => 'Sofia Navarro', 'username' => 'sofia_navarro']);
    $learner->studentProfile->forceFill([
        'lrn' => '136742210001',
        'sex' => 'female',
        'birthdate' => '2016-02-14',
        'total_points' => 1240,
        'level' => 1,
        'last_active_at' => '2026-11-12 08:00:00',
    ])->save();
    User::factory()->parent()->guardianOf($learner, GuardianRelationship::Mother)->create(['name' => 'Ana Navarro']);

    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.students.export'));

    $response->assertOk()->assertDownload('learners-2026-11-13.csv');
    expect($csvRows($response->streamedContent()))->toBe([
        StudentExportController::HEADINGS,
        ['Sofia Navarro', 'sofia_navarro', '136742210001', 'Grade 5', 'Female', '2016-02-14', 'Health 5 – Sampaguita', 'Ana Navarro (Mother)', '1240', '3', 'Active', '2026-11-12 08:00:00'],
    ]);
});

it('exports only the filtered learners in the list order', function () use ($csvRows) {
    User::factory()->student(GradeLevel::Six)->create(['name' => 'Zeny Cruz']);
    User::factory()->student(GradeLevel::Six)->inactive()->create(['name' => 'Ana Reyes']);
    User::factory()->student(GradeLevel::Five)->create(['name' => 'Miguel Navarro']);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.students.export', ['grade' => '6']));

    expect(array_column(array_slice($csvRows($response->streamedContent()), 1), 0))->toBe(['Ana Reyes', 'Zeny Cruz']);
});

it('writes empty cells for a learner without profile details', function () use ($csvRows) {
    User::factory()->studentWithoutProfile()->create(['name' => 'New Learner', 'username' => 'newlearner']);

    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.students.export'));

    expect($csvRows($response->streamedContent())[1])->toBe(['New Learner', 'newlearner', '', '', '', '', '', '', '0', '1', 'Active', '']);
});

it('neutralizes spreadsheet formulas in names', function () use ($csvRows) {
    User::factory()->student()->create(['name' => '=HYPERLINK("http://example.test")']);

    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.students.export'));

    expect($csvRows($response->streamedContent())[1][0])->toBe('\'=HYPERLINK("http://example.test")');
});

it('sends guests to the sign-in page', function () {
    $this->get(route('admin.students.export'))->assertRedirectToRoute('login');
});

it('forbids other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('admin.students.export'))
        ->assertForbidden();
})->with([
    'teacher' => UserRole::Teacher,
    'parent' => UserRole::Parent,
    'learner' => UserRole::Student,
]);
