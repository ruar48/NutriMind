<?php

namespace App\Http\Requests\Admin;

use App\Enums\GradeLevel;
use App\Enums\UserRole;
use App\Http\Requests\Admin\Concerns\ReadsPeopleListFilters;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The filters of the Learners list, shared by the list page and its CSV export so both show the same learners in
 * the same order: search (name, username, LRN), grade, classroom, status, guardian linked, and sort.
 */
class IndexStudentRequest extends FormRequest
{
    use ReadsPeopleListFilters;

    /**
     * The sort keys and the direction each one starts with.
     *
     * @var array<string, string>
     */
    public const SORTABLE = ['name' => 'asc', 'points' => 'desc', 'last_active' => 'desc'];

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
     * Get the grade filter, or null for both grades.
     */
    public function grade(): ?GradeLevel
    {
        $grade = $this->queryChoice('grade', array_map(strval(...), array_column(GradeLevel::cases(), 'value')));

        return $grade === null ? null : GradeLevel::from((int) $grade);
    }

    /**
     * Get the classroom filter (a classroom id), or null for any classroom.
     */
    public function classroom(): ?int
    {
        return $this->queryPositiveInteger('classroom');
    }

    /**
     * Get the guardian filter: "yes" (has a linked parent or guardian), "no" (none) or null.
     */
    public function guardian(): ?string
    {
        return $this->queryChoice('guardian', ['yes', 'no']);
    }

    /**
     * Get the sort key (name by default).
     */
    public function sort(): string
    {
        return $this->queryChoice('sort', array_keys(self::SORTABLE)) ?? 'name';
    }

    /**
     * Build the filtered, deterministically ordered learner query (without eager loads).
     *
     * @return Builder<User>
     */
    public function students(): Builder
    {
        $query = $this->constrainStatus($this->constrainSearch(User::query()->ofRole(UserRole::Student), includeLrn: true))
            ->when($this->grade(), fn (Builder $query, GradeLevel $grade): Builder => $query->whereHas(
                'studentProfile',
                fn (Builder $profile): Builder => $profile->where('grade_level', $grade),
            ))
            ->when($this->classroom(), fn (Builder $query, int $classroomId): Builder => $query->whereHas(
                'enrolledClassrooms',
                fn (Builder $classroom): Builder => $classroom->whereKey($classroomId),
            ))
            ->when($this->guardian() === 'yes', fn (Builder $query): Builder => $query->has('guardians'))
            ->when($this->guardian() === 'no', fn (Builder $query): Builder => $query->doesntHave('guardians'));

        $sort = $this->sort();
        $direction = $this->direction(self::SORTABLE[$sort]);

        return match ($sort) {
            'points' => $query->orderBy($this->profileColumn('total_points'), $direction)->orderBy('users.name')->orderBy('users.id'),
            'last_active' => $query->orderBy($this->profileColumn('last_active_at'), $direction)->orderBy('users.name')->orderBy('users.id'),
            default => $query->orderBy('users.name', $direction)->orderBy('users.id', $direction),
        };
    }

    /**
     * Get a subquery selecting one column of the learner's profile, for ordering.
     *
     * @return Builder<StudentProfile>
     */
    private function profileColumn(string $column): Builder
    {
        return StudentProfile::query()->select($column)->whereColumn('student_profiles.user_id', 'users.id');
    }
}
