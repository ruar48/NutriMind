<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GuardianRelationship;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexParentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Parent and guardian accounts and the learners linked to them.
 */
class ParentController extends Controller
{
    /**
     * Parents per page.
     */
    private const PER_PAGE = 20;

    /**
     * The most learners the link search lists at once.
     */
    public const LEARNER_SEARCH_LIMIT = 10;

    /**
     * List the parents with their linked learners, search, status and linked filters.
     */
    public function index(IndexParentRequest $request): View
    {
        $sort = $request->sort();

        $parents = $request->constrainStatus($request->constrainSearch(User::query()->ofRole(UserRole::Parent)))
            ->when($request->linked() === 'yes', fn (Builder $query): Builder => $query->has('linkedStudents'))
            ->when($request->linked() === 'no', fn (Builder $query): Builder => $query->doesntHave('linkedStudents'))
            ->with(['linkedStudents' => fn (BelongsToMany $learners): BelongsToMany => $learners->orderBy('name')->orderBy('users.id')])
            ->orderBy($sort, $request->direction($sort === 'name' ? 'asc' : 'desc'))
            ->when($sort !== 'name', fn (Builder $query): Builder => $query->orderBy('name'))
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.parents.index', [
            'parents' => $parents,
            'isFiltered' => $request->search() !== null || $request->status() !== null || $request->linked() !== null,
        ]);
    }

    /**
     * Show the parent's profile and linked learners, with a learner search (?learner_search=) to link more.
     */
    public function show(Request $request, User $guardian): View
    {
        $guardian->load(['linkedStudents' => fn (BelongsToMany $learners): BelongsToMany => $learners
            ->with(['studentProfile', 'enrolledClassrooms' => fn (BelongsToMany $classrooms): BelongsToMany => $classrooms
                ->active()
                ->with('subject')
                ->orderBy('name')
                ->orderBy('classrooms.id')])
            ->orderBy('name')
            ->orderBy('users.id')]);

        $learnerSearch = $this->learnerSearch($request);
        $results = $learnerSearch === null ? null : $this->searchLearners($learnerSearch);

        return view('admin.parents.show', [
            'guardian' => $guardian,
            'learnerSearch' => $learnerSearch,
            'results' => $results?->take(self::LEARNER_SEARCH_LIMIT),
            'hasMoreResults' => $results !== null && $results->count() > self::LEARNER_SEARCH_LIMIT,
            'linkedIds' => $guardian->linkedStudents->modelKeys(),
            'relationshipOptions' => GuardianRelationship::options(),
        ]);
    }

    /**
     * Read the learner search term (trimmed, at most 100 characters), or null when blank.
     */
    private function learnerSearch(Request $request): ?string
    {
        $value = $request->query('learner_search');

        if (! is_string($value)) {
            return null;
        }

        $term = Str::substr(Str::squish($value), 0, 100);

        return $term === '' ? null : $term;
    }

    /**
     * Find learners by name, username or LRN; one more than the limit is loaded to know whether more exist.
     *
     * @return EloquentCollection<int, User>
     */
    private function searchLearners(string $term): EloquentCollection
    {
        return User::query()
            ->ofRole(UserRole::Student)
            ->where(fn (Builder $query): Builder => $query
                ->search($term)
                ->orWhereHas('studentProfile', fn (Builder $profile): Builder => $profile->whereLike('lrn', '%'.$term.'%')))
            ->with(['studentProfile', 'enrolledClassrooms' => fn (BelongsToMany $classrooms): BelongsToMany => $classrooms
                ->active()
                ->with('subject')
                ->orderBy('name')
                ->orderBy('classrooms.id')])
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LEARNER_SEARCH_LIMIT + 1)
            ->get();
    }
}
