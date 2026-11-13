<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\StudentImport\CsvParser;
use App\Services\StudentImport\ImportColumns;

it('downloads the template with the columns and two example rows', function () {
    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.students.import.template'));

    $response->assertOk()->assertDownload('learner-import-template.csv');
    expect($response->headers->get('Content-Type'))->toBe('text/csv; charset=UTF-8')
        ->and($response->streamedContent())->toBe(
            "\xEF\xBB\xBF"
            ."first_name,last_name,lrn,grade_level,sex,birthdate,username,guardian_name,guardian_email,guardian_relationship\r\n"
            ."Juan,\"Dela Cruz\",136742210001,5,male,2015-06-15,,\"Maria Dela Cruz\",maria.delacruz@example.com,mother\r\n"
            ."\"Ana Marie\",Santos,136742200002,\"Grade 6\",F,03/21/2014,anasantos,\"Roberto Santos\",roberto.santos@example.com,father\r\n"
        );
});

it('downloads a template that passes the upload checks', function () {
    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.students.import.template'));

    $csv = app(CsvParser::class)->parse($response->streamedContent());

    expect(ImportColumns::problems($csv, gradeFromClassroom: false))->toBe([])
        ->and($csv->rows)->toHaveCount(2);
});

it('sends guests to the sign-in page', function () {
    $this->get(route('admin.students.import.template'))->assertRedirectToRoute('login');
});

it('forbids other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('admin.students.import.template'))
        ->assertForbidden();
})->with([UserRole::Teacher, UserRole::Parent]);
