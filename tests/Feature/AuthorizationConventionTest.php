<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Services\RolePermissions;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Authorization\AuthorizedClassroomController;

/*
| Proves the contract's authorization convention: #[Authorize] on a controller action runs as `can` middleware,
| after route model binding and BEFORE the Form Request is resolved, so a refused user gets 403/404 even with an
| invalid payload, and never sees validation errors.
*/

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:teacher'])->prefix('_test/authorize')->group(function () {
        Route::put('/classrooms/{classroom}', [AuthorizedClassroomController::class, 'update']);
        Route::post('/classrooms', [AuthorizedClassroomController::class, 'store']);
        Route::post('/exports', [AuthorizedClassroomController::class, 'export']);
    });
});

describe('model parameter', function () {
    it('reports another teacher\'s classroom as missing before validating the payload', function () {
        $classroom = Classroom::factory()->create();
        $this->actingAs(User::factory()->teaching($classroom->subject)->create());

        $this->put('/_test/authorize/classrooms/'.$classroom->id, ['name' => ''])
            ->assertNotFound()
            ->assertSessionHasNoErrors();
    });

    it('refuses the owner of a revoked subject with the policy message before validating the payload', function () {
        $classroom = Classroom::factory()->revokedSubject()->create();
        $this->actingAs($classroom->teacher);

        $this->put('/_test/authorize/classrooms/'.$classroom->id, ['name' => ''])
            ->assertForbidden()
            ->assertSeeText('Your access to this subject was removed by the administrator.')
            ->assertSessionHasNoErrors();
    });

    it('validates the payload once the policy allows', function () {
        $classroom = Classroom::factory()->create();
        $this->actingAs($classroom->teacher);

        $this->put('/_test/authorize/classrooms/'.$classroom->id, ['name' => 'abc'])
            ->assertRedirect()
            ->assertSessionHasErrors(['name' => 'The name field must be at least 5 characters.']);
    });

    it('runs the action with the bound model and the validated payload', function () {
        $classroom = Classroom::factory()->create();
        $this->actingAs($classroom->teacher);

        $this->put('/_test/authorize/classrooms/'.$classroom->id, ['name' => 'Grade 6 – Rizal'])
            ->assertOk()
            ->assertSeeText('updated '.$classroom->id.' to Grade 6 – Rizal');
    });

    it('answers a missing record with 404 before authorizing', function () {
        $this->actingAs(User::factory()->teaching()->create());

        $this->put('/_test/authorize/classrooms/999999', ['name' => ''])->assertNotFound();
    });
});

describe('model class', function () {
    it('refuses a teacher without subjects before validating the payload', function () {
        $this->actingAs(User::factory()->teacher()->create());

        $this->post('/_test/authorize/classrooms', ['name' => ''])
            ->assertForbidden()
            ->assertSeeText('No subject is assigned to you yet. Ask your school administrator to assign one.')
            ->assertSessionHasNoErrors();
    });

    it('validates the payload once the policy allows', function () {
        $this->actingAs(User::factory()->teaching()->create());

        $this->post('/_test/authorize/classrooms', ['name' => ''])
            ->assertSessionHasErrors(['name' => 'The name field is required.']);
    });
});

describe('permission enum', function () {
    it('refuses a revoked permission with its message before validating the payload', function () {
        $teacher = User::factory()->teacher()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ExportReports);
        $this->actingAs($teacher);

        $this->post('/_test/authorize/exports', ['name' => ''])
            ->assertForbidden()
            ->assertSeeText('You do not have permission to export reports.')
            ->assertSessionHasNoErrors();
    });

    it('validates the payload when the permission is granted', function () {
        $this->actingAs(User::factory()->teacher()->create());

        $this->post('/_test/authorize/exports', ['name' => ''])
            ->assertSessionHasErrors(['name' => 'The name field is required.']);
    });

    it('runs the action when the permission is granted and the payload is valid', function () {
        $this->actingAs(User::factory()->teacher()->create());

        $this->post('/_test/authorize/exports', ['name' => 'Quarter 1'])
            ->assertOk()
            ->assertSeeText('exported Quarter 1');
    });
});

it('sends guests to the sign-in page before authorizing or validating', function () {
    $this->post('/_test/authorize/exports', ['name' => ''])
        ->assertRedirectToRoute('login')
        ->assertSessionHasNoErrors();
});
