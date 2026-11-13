<?php

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Models\ForumCategory;
use App\Models\ForumThread;
use App\Services\Forum\ThreadFeed;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * Show the threads of one category. Inactive categories are only open to administrators.
     */
    #[Authorize('viewAny', ForumThread::class)]
    public function show(Request $request, ForumCategory $category, ThreadFeed $feed): View
    {
        $viewer = $request->user();

        abort_unless($category->is_active || $viewer->isAdmin(), 404);

        $filters = $feed->filters($request, $viewer);

        return view('forum.categories.show', [
            'category' => $category,
            'threads' => $feed->paginate($viewer, $filters, $category),
            'categories' => $feed->categories($viewer),
            'filters' => $filters,
            'showOptions' => $feed->showOptions($viewer),
        ]);
    }
}
