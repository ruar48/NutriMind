<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreForumCategoryRequest;
use App\Http\Requests\Admin\UpdateForumCategoryRequest;
use App\Models\ForumCategory;
use App\Models\ForumThread;
use App\Services\Forum\ForumCategories;
use App\Services\Forum\ThreadFeed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

#[Authorize('moderate', ForumThread::class)]
class ForumCategoryController extends Controller
{
    /**
     * The status filter options, as value => label.
     *
     * @var array<string, string>
     */
    public const STATUSES = ['active' => 'Active', 'inactive' => 'Inactive'];

    /**
     * How many categories one page lists.
     */
    public const PER_PAGE = 24;

    /**
     * List the forum categories in their display order.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $status = is_string($status) && array_key_exists($status, self::STATUSES) ? $status : null;
        $search = $request->query('search');
        $search = is_string($search) ? mb_substr(trim($search), 0, ThreadFeed::SEARCH_MAX_LENGTH) : '';

        $categories = ForumCategory::query()
            ->when($status !== null, fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->when($search !== '', fn (Builder $query) => $query->whereAny(['name', 'description'], 'like', '%'.$search.'%'))
            ->withCount('threads')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.forum-categories.index', [
            'categories' => $categories,
            'statusOptions' => self::STATUSES,
        ]);
    }

    /**
     * Show the form to create a category, placed after the existing ones.
     */
    public function create(): View
    {
        return view('admin.forum-categories.create', [
            'category' => new ForumCategory([
                'sort_order' => min(9999, (int) ForumCategory::query()->max('sort_order') + 1),
            ]),
        ]);
    }

    /**
     * Create a category.
     */
    public function store(StoreForumCategoryRequest $request, ForumCategories $categories): RedirectResponse
    {
        $category = $categories->create($request->validated());

        return redirect()->route('admin.forum-categories.index')->with('status', '“'.$category->name.'” was created.');
    }

    /**
     * Show the form to edit a category.
     */
    public function edit(ForumCategory $forumCategory): View
    {
        return view('admin.forum-categories.edit', [
            'category' => $forumCategory->loadCount('threads'),
        ]);
    }

    /**
     * Save changes to a category; renaming it also changes its link.
     */
    public function update(UpdateForumCategoryRequest $request, ForumCategory $forumCategory, ForumCategories $categories): RedirectResponse
    {
        $categories->update($forumCategory, $request->validated());

        return redirect()->route('admin.forum-categories.index')->with('status', '“'.$forumCategory->name.'” was updated.');
    }

    /**
     * Delete an empty category; categories with threads can only be deactivated.
     */
    public function destroy(ForumCategory $forumCategory): RedirectResponse
    {
        if ($forumCategory->threads()->exists()) {
            return redirect()->route('admin.forum-categories.index')->with('error', ForumCategories::HAS_THREADS_MESSAGE);
        }

        $forumCategory->delete();

        return redirect()->route('admin.forum-categories.index')->with('status', '“'.$forumCategory->name.'” was deleted.');
    }
}
