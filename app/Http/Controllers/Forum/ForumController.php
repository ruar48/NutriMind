<?php

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Models\ForumThread;
use App\Services\Forum\ThreadFeed;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class ForumController extends Controller
{
    /**
     * Show the forum home: category navigation, filters and the threads of every open category.
     */
    #[Authorize('viewAny', ForumThread::class)]
    public function index(Request $request, ThreadFeed $feed, Settings $settings): View
    {
        $viewer = $request->user();
        $filters = $feed->filters($request, $viewer);

        return view('forum.index', [
            'threads' => $feed->paginate($viewer, $filters),
            'categories' => $feed->categories($viewer),
            'filters' => $filters,
            'showOptions' => $feed->showOptions($viewer),
            'forumEnabled' => (bool) $settings->get('forum_enabled', true),
        ]);
    }
}
