<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ReadsPeopleListFilters;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The filters of the Parents & guardians list: search, status, linked learners and sort.
 */
class IndexParentRequest extends FormRequest
{
    use ReadsPeopleListFilters;

    /**
     * The columns the list can be sorted by.
     *
     * @var list<string>
     */
    public const SORTABLE = ['name', 'last_login_at'];

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
     * Get the linked-learners filter: "yes" (has linked learners), "no" (none) or null.
     */
    public function linked(): ?string
    {
        return $this->queryChoice('linked', ['yes', 'no']);
    }

    /**
     * Get the sort column (name by default).
     */
    public function sort(): string
    {
        return $this->queryChoice('sort', self::SORTABLE) ?? 'name';
    }
}
