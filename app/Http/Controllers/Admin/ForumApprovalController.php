<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ForumContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateForumApprovalRequest;
use App\Models\ForumCategory;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Services\Forum\ApprovalDecision;
use App\Services\Forum\ForumModeration;
use App\Services\Forum\ThreadFeed;
use App\Services\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

#[Authorize('moderate', ForumThread::class)]
class ForumApprovalController extends Controller
{
    /**
     * How many pending threads one page lists.
     */
    public const PER_PAGE = 15;

    /**
     * List the threads waiting for approval, oldest first.
     */
    public function index(Request $request, Settings $settings): View
    {
        $categories = ForumCategory::query()->orderBy('sort_order')->orderBy('name')->orderBy('id')->get(['id', 'name', 'slug']);
        $requestedCategory = $request->query('category');
        $category = is_string($requestedCategory) ? $categories->firstWhere('slug', $requestedCategory) : null;
        $search = $request->query('search');
        $search = is_string($search) ? mb_substr(trim($search), 0, ThreadFeed::SEARCH_MAX_LENGTH) : '';

        $threads = ForumThread::query()
            ->where('status', ForumContentStatus::Pending)
            ->when($category, fn (Builder $query, ForumCategory $category) => $query->whereBelongsTo($category, 'category'))
            ->when($search !== '', fn (Builder $query) => $query->whereAny(['title', 'body'], 'like', '%'.$search.'%'))
            ->with(['category', 'author'])
            ->oldest()
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.forum.pending.index', [
            'threads' => $threads,
            'categoryOptions' => $categories->pluck('name', 'slug')->all(),
            'requiresApproval' => (bool) $settings->get('forum_requires_approval', false),
            'openCount' => ForumReport::query()->unresolved()->count(),
            'pendingCount' => ForumThread::query()->where('status', ForumContentStatus::Pending)->count(),
        ]);
    }

    /**
     * Approve a pending thread (it is published) or reject it (it is hidden).
     */
    public function update(UpdateForumApprovalRequest $request, ForumThread $thread, ForumModeration $moderation): RedirectResponse
    {
        $back = route('admin.forum.pending.index');

        if ($thread->status !== ForumContentStatus::Pending) {
            return redirect()->back(fallback: $back)->with('error', ForumModeration::ALREADY_REVIEWED_MESSAGE);
        }

        $title = $moderation->quotedTitle($thread);

        if ($request->enum('decision', ApprovalDecision::class) === ApprovalDecision::Approve) {
            $moderation->approve($thread);

            return redirect()->back(fallback: $back)->with('status', $title.' is published.');
        }

        $moderation->reject($thread);

        return redirect()->back(fallback: $back)->with('status', $title.' was rejected. Only its author and administrators can see it.');
    }
}
