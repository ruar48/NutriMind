<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Http\Requests\Admin\Concerns\ReadsPeopleListFilters;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The filters of the All users list: role tab, search, status and sort.
 */
class IndexUserRequest extends FormRequest
{
    use ReadsPeopleListFilters;

    /**
     * The columns the list can be sorted by.
     *
     * @var list<string>
     */
    public const SORTABLE = ['name', 'last_login_at', 'created_at'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. Filters are read leniently by the accessors instead.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Get the role tab, or null for every role.
     */
    public function role(): ?UserRole
    {
        $role = $this->queryChoice('role', array_column(UserRole::cases(), 'value'));

        return $role === null ? null : UserRole::from($role);
    }

    /**
     * Get the sort column (name by default).
     */
    public function sort(): string
    {
        return $this->queryChoice('sort', self::SORTABLE) ?? 'name';
    }
}
