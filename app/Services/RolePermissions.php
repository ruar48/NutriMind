<?php

namespace App\Services;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Container\Attributes\Scoped;

/**
 * The administrator-editable permission matrix.
 *
 * The matrix is stored in the "role_permissions" setting as a map of role value to granted permission values,
 * for every role except Administrator (administrators always hold every permission). While the setting is
 * null, each permission is granted to its enum default roles. Permissions that do not apply to a role are
 * never granted to it, whatever is stored.
 */
#[Scoped]
class RolePermissions
{
    /**
     * The settings key that holds the stored matrix.
     */
    public const SETTING_KEY = 'role_permissions';

    public function __construct(private readonly Settings $settings) {}

    /**
     * Get the permissions granted to the role, in enum case order.
     *
     * @return list<Permission>
     */
    public function granted(UserRole $role): array
    {
        if ($role === UserRole::Admin) {
            return Permission::cases();
        }

        $stored = $this->settings->get(self::SETTING_KEY);

        if (! is_array($stored) || ! array_key_exists($role->value, $stored)) {
            return $this->defaultsFor($role);
        }

        return $this->applicable($role, $stored[$role->value]);
    }

    /**
     * Determine whether the user holds the permission: inactive accounts hold none, administrators hold all.
     */
    public function allows(User $user, Permission $permission): bool
    {
        if (! $user->is_active || ! $user->role instanceof UserRole) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        return in_array($permission, $this->granted($user->role), true);
    }

    /**
     * Get the granted permission values of every configurable role, keyed by role value.
     *
     * @return array<string, list<string>>
     */
    public function matrix(): array
    {
        $matrix = [];

        foreach (self::configurableRoles() as $role) {
            $matrix[$role->value] = array_map(
                fn (Permission $permission): string => $permission->value,
                $this->granted($role),
            );
        }

        return $matrix;
    }

    /**
     * Store a new matrix of role value to granted permission values.
     *
     * A configurable role missing from the matrix is granted nothing. Unknown roles, the Administrator
     * role, unknown permission values and permissions that do not apply to a role are dropped.
     *
     * @param  array<string, list<string>>  $matrix
     */
    public function update(array $matrix): void
    {
        $normalized = [];

        foreach (self::configurableRoles() as $role) {
            $normalized[$role->value] = array_map(
                fn (Permission $permission): string => $permission->value,
                $this->applicable($role, $matrix[$role->value] ?? []),
            );
        }

        $this->settings->set(self::SETTING_KEY, $normalized);
    }

    /**
     * Restore the enum default roles of every permission.
     */
    public function reset(): void
    {
        $this->settings->set(self::SETTING_KEY, null);
    }

    /**
     * Get the roles whose permissions an administrator can change, in display order.
     *
     * @return list<UserRole>
     */
    public static function configurableRoles(): array
    {
        return array_values(array_filter(
            UserRole::cases(),
            fn (UserRole $role): bool => $role !== UserRole::Admin,
        ));
    }

    /**
     * Get the permissions granted to the role by default.
     *
     * @return list<Permission>
     */
    private function defaultsFor(UserRole $role): array
    {
        return array_values(array_filter(
            Permission::cases(),
            fn (Permission $permission): bool => in_array($role, $permission->defaultRoles(), true),
        ));
    }

    /**
     * Keep the known permissions that apply to the role, in enum case order.
     *
     * @return list<Permission>
     */
    private function applicable(UserRole $role, mixed $values): array
    {
        $values = is_array($values) ? array_filter($values, 'is_string') : [];

        return array_values(array_filter(
            Permission::cases(),
            fn (Permission $permission): bool => in_array($permission->value, $values, true)
                && in_array($role, $permission->roles(), true),
        ));
    }
}
