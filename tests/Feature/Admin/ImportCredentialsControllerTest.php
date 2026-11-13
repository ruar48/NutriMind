<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\StudentImport\PendingImports;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Services\StudentImport\Support\StudentImportCsv;

it('downloads the sign-in details of the last import once', function () {
    Storage::fake('local');
    $this->travelTo('2026-11-13 10:00:00');
    $admin = User::factory()->admin()->create();
    $pending = StudentImportCsv::pending($admin, [
        StudentImportCsv::row(['guardian_name' => 'Maria Dela Cruz', 'guardian_email' => 'maria@example.com']),
    ]);
    $this->actingAs($admin)->post(route('admin.students.import.commit', $pending->id))->assertRedirect();
    $credentials = session('credentials');

    $response = $this->get(route('admin.students.import.credentials'));

    $response->assertOk()->assertDownload('learner-sign-in-details-2026-11-13.csv');
    expect($response->streamedContent())->toBe(
        "\xEF\xBB\xBF"
        ."Name,Account,\"Username or email\",Password,\"Signs in to\"\r\n"
        ."\"Juan Dela Cruz\",Learner,jdelacruz,{$credentials[0]['password']},\"NutriMind Game\"\r\n"
        ."\"Maria Dela Cruz\",\"Parent / Guardian\",maria@example.com,{$credentials[1]['password']},\"Parent Portal\"\r\n"
    )->and(session()->has(PendingImports::CREDENTIALS_SESSION_KEY))->toBeFalse();

    $this->from(route('admin.students.import.create'))
        ->get(route('admin.students.import.credentials'))
        ->assertRedirectToRoute('admin.students.import.create')
        ->assertSessionHas('error', PendingImports::NO_CREDENTIALS_MESSAGE);
});

it('neutralizes spreadsheet formulas in names', function () {
    $admin = User::factory()->admin()->create();
    app(PendingImports::class)->putCredentials($admin, [
        ['name' => '=HYPERLINK("http://evil.test")', 'username' => 'evil', 'password' => 'mango-4821', 'role' => UserRole::Student],
    ]);

    $response = $this->actingAs($admin)->get(route('admin.students.import.credentials'));

    expect($response->streamedContent())->toContain("\"'=HYPERLINK(\"\"http://evil.test\"\")\"");
});

it('redirects back with an error when there is nothing to download', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.students.import.credentials'))
        ->assertRedirectToRoute('admin.students.import.create')
        ->assertSessionHas('error', 'There are no sign-in details to download. They can be downloaded once, right after an import.');
});

it('never hands another administrator\'s sign-in details out', function () {
    $owner = User::factory()->admin()->create();
    app(PendingImports::class)->putCredentials($owner, [
        ['name' => 'Juan Dela Cruz', 'username' => 'jdelacruz', 'password' => 'mango-4821', 'role' => UserRole::Student],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.students.import.credentials'))
        ->assertRedirect()
        ->assertSessionHas('error', PendingImports::NO_CREDENTIALS_MESSAGE);
});

it('sends guests to the sign-in page', function () {
    $this->get(route('admin.students.import.credentials'))->assertRedirectToRoute('login');
});

it('forbids other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('admin.students.import.credentials'))
        ->assertForbidden();
})->with([UserRole::Teacher, UserRole::Parent]);
