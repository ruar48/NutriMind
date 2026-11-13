<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolePermissionRequest;
use App\Services\RolePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The roles & permissions matrix (administrator only, see SchoolYearController).
 */
#[Middleware('role:admin')]
class RolePermissionController extends Controller
{
    /**
     * Show the permission matrix grouped by permission group, with a summary per configurable role.
     */
    public function index(RolePermissions $permissions): View
    {
        $matrix = $permissions->matrix();

        $groups = collect(Permission::cases())
            ->groupBy(fn (Permission $permission): string => $permission->group())
            ->map(fn (Collection $groupPermissions, string $group): array => [
                'label' => $group,
                'permissions' => $groupPermissions->values()->all(),
            ])
            ->values();

        $roleSummaries = collect(RolePermissions::configurableRoles())->map(function (UserRole $role) use ($matrix): array {
            $applicable = array_values(array_filter(
                Permission::cases(),
                fn (Permission $permission): bool => in_array($role, $permission->roles(), true),
            ));
            $defaults = array_map(
                fn (Permission $permission): string => $permission->value,
                array_filter($applicable, fn (Permission $permission): bool => in_array($role, $permission->defaultRoles(), true)),
            );
            $granted = $matrix[$role->value] ?? [];

            return [
                'role' => $role,
                'granted' => count($granted),
                'applicable' => count($applicable),
                'changed' => count(array_diff($granted, $defaults)) + count(array_diff($defaults, $granted)),
            ];
        });

        return view('admin.roles.index', [
            'groups' => $groups,
            'roles' => UserRole::cases(),
            'matrix' => $matrix,
            'roleSummaries' => $roleSummaries,
            'isDefault' => $roleSummaries->sum('changed') === 0,
        ]);
    }

    /**
     * Save the submitted matrix, or restore the defaults when reset=1.
     */
    public function update(UpdateRolePermissionRequest $request, RolePermissions $permissions): RedirectResponse
    {
        if ($request->boolean('reset')) {
            $permissions->reset();

            return redirect()->route('admin.roles.index')->with('status', 'Role permissions were reset to the defaults.');
        }

        $before = $permissions->matrix();
        $permissions->update($request->matrix());
        $after = $permissions->matrix();

        $granted = 0;
        $revoked = 0;

        foreach ($after as $role => $values) {
            $granted += count(array_diff($values, $before[$role] ?? []));
            $revoked += count(array_diff($before[$role] ?? [], $values));
        }

        $message = $granted === 0 && $revoked === 0
            ? 'Role permissions saved. Nothing changed.'
            : 'Role permissions saved: '.trans_choice(':count permission granted|:count permissions granted', $granted)
                .', '.$revoked.' revoked.';

        return redirect()->route('admin.roles.index')->with('status', $message);
    }
}
