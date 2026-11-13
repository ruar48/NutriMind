<?php

namespace App\Services\Forum;

use App\Enums\ForumContentStatus;
use App\Models\ForumCategory;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Lists forum threads for one reader: the forum home page and the category pages.
 *
 * Readers see published threads and their own; administrators see every thread. Threads of inactive categories are
 * left out for everyone but administrators. Pinned threads come first, then the most recently active.
 */
class ThreadFeed
{
    /**
     * How many threads one page lists.
     */
    public const PER_PAGE = 15;

    /**
     * The longest search term used; longer input is cut.
     */
    public const SEARCH_MAX_LENGTH = 100;

    /**
     * The "show" filter that keeps the reader's own threads.
     */
    public const SHOW_MINE = 'mine';

    /**
     * Get the "show" filter options the reader may use, as value => label.
     *
     * @return array<string, string>
     */
    public function showOptions(User $viewer): array
    {
        $options = [self::SHOW_MINE => 'Started by me'];

        if ($viewer->isAdmin()) {
            $options[ForumContentStatus::Pending->value] = 'Waiting for approval';
            $options[ForumContentStatus::Hidden->value] = 'Hidden by a moderator';
        }

        return $options;
    }

    /**
     * Read the list filters from the query string, keeping only values the reader may use.
     *
     * @return array{search: string|null, show: string|null}
     */
    public function filters(Request $request, User $viewer): array
    {
        $search = $request->query('search');
        $search = is_string($search) ? mb_substr(trim($search), 0, self::SEARCH_MAX_LENGTH) : '';
        $show = $request->query('show');

        return [
            'search' => $search === '' ? null : $search,
            'show' => is_string($show) && array_key_exists($show, $this->showOptions($viewer)) ? $show : null,
        ];
    }

    /**
     * Get one page of the threads the reader may see, optionally in one category.
     *
     * @param  array{search: string|null, show: string|null}  $filters
     * @return LengthAwarePaginator<int, ForumThread>
     */
    public function paginate(User $viewer, array $filters, ?ForumCategory $category = null): LengthAwarePaginator
    {
        return ForumThread::query()
            ->visibleTo($viewer)
            ->when($category, fn (Builder $query, ForumCategory $category) => $query->whereBelongsTo($category, 'category'))
            ->unless($viewer->isAdmin(), fn (Builder $query) => $query->whereHas('category', fn (Builder $query) => $query->active()))
            ->when($filters['search'], fn (Builder $query, string $search) => $query->whereAny(['title', 'body'], 'like', '%'.$search.'%'))
            ->when($filters['show'] === self::SHOW_MINE, fn (Builder $query) => $query->whereBelongsTo($viewer, 'author'))
            ->when(
                ForumContentStatus::tryFrom((string) $filters['show']),
                fn (Builder $query, ForumContentStatus $status) => $query->where('status', $status),
            )
            ->with(['category', 'author'])
            ->withCount(['replies' => fn (Builder $query) => $query->published()])
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Get the categories the reader can browse, with the number of threads they may see in each.
     *
     * Administrators also get inactive categories.
     *
     * @return Collection<int, ForumCategory>
     */
    public function categories(User $viewer): Collection
    {
        return ForumCategory::query()
            ->unless($viewer->isAdmin(), fn (Builder $query) => $query->active())
            ->withCount(['threads' => fn (Builder $query) => $query->visibleTo($viewer)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }
}
