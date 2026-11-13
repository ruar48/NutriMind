<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Services\RolePermissions;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

describe('role-restricted route bindings', function () {
    dataset('role-bound parameters', [
        'student' => ['student', UserRole::Student],
        'teacher' => ['teacher', UserRole::Teacher],
        'guardian' => ['guardian', UserRole::Parent],
    ]);

    it('resolves the parameter to a user of its role', function (string $parameter, UserRole $role) {
        Route::middleware('web')->get('/_test/bindings/{'.$parameter.'}', fn (User $user) => 'user '.$user->id);
        $user = User::factory()->{$role->value}()->inactive()->create();

        $this->get('/_test/bindings/'.$user->id)
            ->assertOk()
            ->assertSeeText('user '.$user->id);
    })->with('role-bound parameters');

    it('reports a user of another role as missing', function (string $parameter, UserRole $otherRole) {
        Route::middleware('web')->get('/_test/bindings/{'.$parameter.'}', fn (User $user) => 'user '.$user->id);
        $user = User::factory()->{$otherRole->value}()->create();

        $this->get('/_test/bindings/'.$user->id)->assertNotFound();
    })->with([
        'student given an administrator' => ['student', UserRole::Admin],
        'student given a teacher' => ['student', UserRole::Teacher],
        'student given a parent' => ['student', UserRole::Parent],
        'teacher given an administrator' => ['teacher', UserRole::Admin],
        'teacher given a parent' => ['teacher', UserRole::Parent],
        'teacher given a learner' => ['teacher', UserRole::Student],
        'guardian given an administrator' => ['guardian', UserRole::Admin],
        'guardian given a teacher' => ['guardian', UserRole::Teacher],
        'guardian given a learner' => ['guardian', UserRole::Student],
    ]);

    it('reports unknown and malformed IDs as missing', function (string $value) {
        Route::middleware('web')->get('/_test/bindings/{student}', fn (User $student) => 'user '.$student->id);

        $this->get('/_test/bindings/'.$value)->assertNotFound();
    })->with(['999999', 'abc', '1 OR 1=1']);

    it('applies to the portal routes', function (string $path, UserRole $wrongRole) {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(sprintf($path, User::factory()->{$wrongRole->value}()->create()->id))->assertNotFound();
    })->with([
        'teacher profile given a parent' => ['/admin/teachers/%d', UserRole::Parent],
        'learner profile given a teacher' => ['/admin/students/%d', UserRole::Teacher],
        'parent profile given a learner' => ['/admin/parents/%d', UserRole::Student],
    ]);
});

describe('permission gates', function () {
    it('defines one gate per permission, named by its value', function () {
        $teacher = User::factory()->teacher()->create();

        foreach (Permission::cases() as $permission) {
            expect(Gate::has($permission->value))->toBeTrue()
                ->and(Gate::forUser($teacher)->allows($permission->value))->toBe($teacher->hasPermission($permission));
        }
    });

    it('explains a denial with the permission label', function () {
        $teacher = User::factory()->teacher()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ExportReports);

        expect(Gate::forUser($teacher)->inspect(Permission::ExportReports->value))
            ->toEqual(Response::deny('You do not have permission to export reports.'));
    });

    it('denies guests', function () {
        expect(Gate::allows(Permission::AccessForum->value))->toBeFalse();
    });
});

it('renders paginator links with the design-system views', function () {
    User::factory()->parent()->count(3)->create();

    $links = (string) User::query()->orderBy('id')->paginate(2)->links();
    $simpleLinks = (string) User::query()->orderBy('id')->simplePaginate(2)->links();

    expect($links)->toContain('aria-label="Pagination"')->toContain('Showing')->toContain('Page 1 of 2');
    expect($simpleLinks)->toContain('aria-label="Pagination"')->toContain('rel="next"')->not->toContain('Page 1 of');
});

it('eager loads a relation for the whole collection the first time one model reads it', function () {
    Classroom::factory()->count(3)->create();
    $classrooms = Classroom::query()->get();
    DB::enableQueryLog();

    $classrooms->each(fn (Classroom $classroom): string => $classroom->subject->name);

    expect(DB::getQueryLog())->toHaveCount(1);
});

it('prevents lazy loading outside production', function () {
    expect(Model::preventsLazyLoading())->toBeTrue();
});
