<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ForumContentStatus;
use App\Enums\ForumReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateForumReportRequest;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Services\Forum\ForumModeration;
use App\Services\Forum\ReportDecision;
use App\Services\Forum\ThreadFeed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

#[Authorize('moderate', ForumThread::class)]
class ForumReportController extends Controller
{
    /**
     * The status filter options of the reports queue, as value => label.
     *
     * @var array<string, string>
     */
    public const STATUSES = ['open' => 'Open', 'resolved' => 'Resolved', 'all' => 'All reports'];

    /**
     * How many reports one page lists.
     */
    public const PER_PAGE = 20;

    /**
     * List the forum reports: open ones by default, unresolved first when every report is listed.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $status = is_string($status) && array_key_exists($status, self::STATUSES) ? $status : 'open';
        $reason = $request->query('reason');
        $reason = is_string($reason) ? ForumReportReason::tryFrom($reason) : null;
        $search = $request->query('search');
        $search = is_string($search) ? mb_substr(trim($search), 0, ThreadFeed::SEARCH_MAX_LENGTH) : '';

        $reports = ForumReport::query()
            ->when($status === 'open', fn (Builder $query) => $query->unresolved())
            ->when($status === 'resolved', fn (Builder $query) => $query->whereNotNull('resolved_at'))
            ->when($reason, fn (Builder $query, ForumReportReason $reason) => $query->where('reason', $reason))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = '%'.$search.'%';

                $query->where(function (Builder $query) use ($pattern, $search): void {
                    $query->whereLike('details', $pattern)
                        ->orWhereHas('thread', fn (Builder $query) => $query->whereLike('title', $pattern))
                        ->orWhereHas('reply', fn (Builder $query) => $query->whereLike('body', $pattern))
                        ->orWhereHas('reporter', fn (Builder $query) => $query->search($search));
                });
            })
            ->with(['thread.author', 'reply.author', 'reporter', 'resolver'])
            ->orderByRaw('CASE WHEN resolved_at IS NULL THEN 0 ELSE 1 END')
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.forum.reports.index', [
            'reports' => $reports,
            'status' => $status,
            'statusOptions' => self::STATUSES,
            'reasonOptions' => ForumReportReason::options(),
            'openCount' => ForumReport::query()->unresolved()->count(),
            'pendingCount' => ForumThread::query()->where('status', ForumContentStatus::Pending)->count(),
        ]);
    }

    /**
     * Resolve a report: dismiss it, or hide the reported content (which also resolves the other open reports
     * about that content).
     */
    public function update(UpdateForumReportRequest $request, ForumReport $forumReport, ForumModeration $moderation): RedirectResponse
    {
        $back = route('admin.forum.reports.index');

        if ($forumReport->resolved_at !== null) {
            return redirect()->back(fallback: $back)->with('error', ForumModeration::REPORT_RESOLVED_MESSAGE);
        }

        if ($request->enum('decision', ReportDecision::class) === ReportDecision::Dismiss) {
            $moderation->dismiss($forumReport, $request->user());

            return redirect()->back(fallback: $back)->with('status', 'Report dismissed. The post stays visible.');
        }

        $resolved = $moderation->hideReportedContent($forumReport, $request->user());
        $target = $forumReport->forum_reply_id === null ? 'thread' : 'reply';

        return redirect()->back(fallback: $back)->with('status', $resolved > 1
            ? "The reported {$target} is hidden and {$resolved} reports about it are resolved."
            : "The reported {$target} is hidden and the report is resolved.");
    }
}
