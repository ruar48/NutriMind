<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ReadsPeopleListFilters;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The filters of the Teachers list: search, status, assigned subject and sort.
 */
class IndexTeacherRequest extends FormRequest
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
     * Get the subject filter (a subject id), or null for any subject.
     */
    public function subject(): ?int
    {
        return $this->queryPositiveInteger('subject');
    }

    /**
     * Get the sort column (name by default).
     */
    public function sort(): string
    {
        return $this->queryChoice('sort', self::SORTABLE) ?? 'name';
    }
}
