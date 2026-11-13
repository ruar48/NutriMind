<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Services\RolePermissions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRolePermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * A role whose boxes are all unchecked is missing from the payload, which means it is granted nothing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $roles = array_map(fn (UserRole $role): string => $role->value, RolePermissions::configurableRoles());

        return [
            'reset' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array:'.implode(',', $roles)],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['string', Rule::enum(Permission::class)],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.array' => 'Permissions can only be changed for teachers, parents and learners.',
            'permissions.*.array' => 'The permissions of a role must be a list.',
            'permissions.*.*.enum' => 'One of the permissions does not exist. Reload the page and try again.',
        ];
    }

    /**
     * Refuse a permission that cannot be granted to the role it was sent for.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->boolean('reset')) {
                    return;
                }

                foreach ((array) $this->input('permissions', []) as $roleValue => $permissionValues) {
                    $role = UserRole::from((string) $roleValue);

                    foreach ((array) $permissionValues as $permissionValue) {
                        $permission = Permission::from((string) $permissionValue);

                        if (! in_array($role, $permission->roles(), true)) {
                            $validator->errors()->add(
                                'permissions.'.$role->value,
                                "{$permission->label()} cannot be granted to {$role->label()} accounts.",
                            );
                        }
                    }
                }
            },
        ];
    }

    /**
     * Get the submitted matrix of role value to granted permission values.
     *
     * @return array<string, list<string>>
     */
    public function matrix(): array
    {
        return array_map(
            fn (array $values): array => array_values(array_map('strval', $values)),
            (array) $this->validated('permissions', []),
        );
    }
}
