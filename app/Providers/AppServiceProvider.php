<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\RolePermissions;
use App\View\Composers\LayoutComposer;
use App\View\Composers\NavigationComposer;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The route parameters that resolve only users of one role.
     *
     * @var array<string, UserRole>
     */
    private const ROLE_BOUND_PARAMETERS = [
        'student' => UserRole::Student,
        'teacher' => UserRole::Teacher,
        'guardian' => UserRole::Parent,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::automaticallyEagerLoadRelationships();
        Model::preventLazyLoading(! $this->app->isProduction());

        Password::defaults(fn (): Password => Password::min(8)->letters()->numbers());

        Paginator::defaultView('pagination.default');
        Paginator::defaultSimpleView('pagination.simple');

        $this->definePermissionGates();
        $this->bindRoleRestrictedUsers();

        View::composers([
            NavigationComposer::class => 'components.layouts.app',
            LayoutComposer::class => 'components.layouts.app',
        ]);
    }

    /**
     * Register one gate per permission, named by the permission value (usable with #[Authorize(Permission::X)]).
     */
    private function definePermissionGates(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): Response => $user->hasPermission($permission)
                ? Response::allow()
                : Response::deny(RolePermissions::deniedMessage($permission)));
        }
    }

    /**
     * Resolve {student}, {teacher} and {guardian} route parameters only to users of that role; any other ID is 404.
     */
    private function bindRoleRestrictedUsers(): void
    {
        foreach (self::ROLE_BOUND_PARAMETERS as $parameter => $role) {
            Route::bind($parameter, fn (string $value): User => User::query()->ofRole($role)->findOrFail($value));
        }
    }
}
