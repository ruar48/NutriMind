<?php

use App\Enums\Permission;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\RolePermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

describe('viewAny', function () {
    it('allows administrators and teachers', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('viewAny', ReportType::class))->toEqual(Response::allow());
    })->with([UserRole::Admin, UserRole::Teacher]);

    it('denies the other roles', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('viewAny', ReportType::class))->toEqual(Response::deny());
    })->with([UserRole::Parent, UserRole::Student]);
});

describe('view', function () {
    it('allows administrators every report', function (ReportType $report) {
        expect(Gate::forUser(User::factory()->admin()->create())->inspect('view', $report))->toEqual(Response::allow());
    })->with(ReportType::cases());

    it('allows teachers the reports available to teachers', function (ReportType $report) {
        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('view', $report))->toEqual(Response::allow());
    })->with(array_values(array_filter(ReportType::cases(), fn (ReportType $report): bool => $report->availableToTeachers())));

    it('reports the user accounts report as missing to teachers', function () {
        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('view', ReportType::UserAccounts))->toEqual(Response::denyAsNotFound());
    });

    it('reports every report as missing to the other roles', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('view', ReportType::StudentPerformance))->toEqual(Response::denyAsNotFound());
    })->with([UserRole::Parent, UserRole::Student]);
});

describe('export', function () {
    it('allows administrators and teachers holding the export permission', function (UserRole $role) {
        expect(Gate::forUser(User::factory()->{$role->value}()->create())->inspect('export', ReportType::QuizResults))->toEqual(Response::allow());
    })->with([UserRole::Admin, UserRole::Teacher]);

    it('refuses teachers without the export permission with its message', function () {
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ExportReports);

        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('export', ReportType::QuizResults))
            ->toEqual(Response::deny('You do not have permission to export reports.'));
    });

    it('reports the user accounts report as missing to teachers before checking the permission', function () {
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ExportReports);

        expect(Gate::forUser(User::factory()->teacher()->create())->inspect('export', ReportType::UserAccounts))->toEqual(Response::denyAsNotFound());
    });

    it('refuses a deactivated administrator', function () {
        expect(Gate::forUser(User::factory()->admin()->inactive()->create())->inspect('export', ReportType::UserAccounts))
            ->toEqual(Response::deny('You do not have permission to export reports.'));
    });
});
