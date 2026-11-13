<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\RolePermissions;
use App\Services\StudentImport\ImportColumns;

it('downloads the template with the columns and two example rows', function () {
    $response = $this->actingAs(User::factory()->teacher()->create())->get(route('teacher.imports.template'));

    $response->assertOk()->assertDownload('learner-import-template.csv');
    $lines = explode("\r\n", trim($response->streamedContent()));
    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toBe("\xEF\xBB\xBF".implode(',', ImportColumns::TEMPLATE_HEADERS));
});

it('forbids teachers whose permission was revoked', function () {
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ImportStudents);

    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('teacher.imports.template'))
        ->assertForbidden()
        ->assertSeeText(RolePermissions::deniedMessage(Permission::ImportStudents));
});

it('sends guests to the sign-in page', function () {
    $this->get(route('teacher.imports.template'))->assertRedirectToRoute('login');
});

it('forbids other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('teacher.imports.template'))
        ->assertForbidden();
})->with([UserRole::Admin, UserRole::Parent]);
