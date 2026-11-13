<?php

namespace Database\Seeders;

use App\Enums\ForumContentStatus;
use App\Enums\UserRole;
use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\User;
use Database\Seeders\Support\BulkInsert;
use Database\Seeders\Support\Catalog\ForumCatalog;
use Database\Seeders\Support\Catalog\PeopleCatalog;
use Database\Seeders\Support\DemoClock;
use Database\Seeders\Support\DemoRandom;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * Seeds the parent forum: categories, threads and replies by parents, teachers and the administrator, and the
 * moderation history (one pinned, one locked, one hidden and two pending threads, a hidden reply, and three reports
 * still waiting for a moderator).
 */
class ForumSeeder extends Seeder
{
    /**
     * When the forum categories were set up.
     */
    private const CATEGORIES_CREATED_AT = '2026-06-18 09:00:00';

    /**
     * The seeded random stream of this seeder.
     */
    private DemoRandom $random;

    /**
     * The anchored clock of this run.
     */
    private DemoClock $clock;

    /**
     * The active parent accounts that may post (the demo parent posts only where the catalog says so), in the order
     * they take turns.
     *
     * @var list<object>
     */
    private array $parents = [];

    /**
     * The position of the next parent to take a turn.
     */
    private int $nextParent = 0;

    /**
     * The IDs of the named authors: "admin", "demo-parent" and "teacher:{key}".
     *
     * @var array<string, int>
     */
    private array $namedAuthors = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->random = DemoRandom::stream('forum');
        $this->clock = DemoClock::start();
        $this->loadAuthors();

        $categoryIds = $this->seedCategories();
        $moderatedAt = $this->moderationMoments();
        $threads = [];
        $replies = [];

        foreach (ForumCatalog::threads() as $threadKey => $thread) {
            $postedAt = $this->clock->now - (int) round($thread['days_ago'] * DemoClock::DAY);
            $authorId = $this->author($thread['author'], $postedAt, []);
            $replyMoments = $this->replyMoments($thread, $postedAt);
            $publishedReplyMoments = array_values(array_filter(
                $replyMoments,
                fn (int $moment, int $index): bool => $thread['replies'][$index]['status'] === 'published',
                ARRAY_FILTER_USE_BOTH,
            ));
            $lastActivityAt = $publishedReplyMoments === [] ? $postedAt : max($publishedReplyMoments);
            $updatedAt = max($lastActivityAt, $moderatedAt[$threadKey] ?? 0, $thread['locked'] ? $lastActivityAt + DemoClock::HOUR : 0);

            $threadId = ForumThread::query()->insertGetId([
                'forum_category_id' => $categoryIds[$thread['category']],
                'author_id' => $authorId,
                'title' => $thread['title'],
                'body' => $thread['body'],
                'status' => ForumContentStatus::from($thread['status'])->value,
                'is_pinned' => $thread['pinned'],
                'is_locked' => $thread['locked'],
                'last_activity_at' => DemoClock::format($lastActivityAt),
                'created_at' => DemoClock::format($postedAt),
                'updated_at' => DemoClock::format(min($updatedAt, $this->clock->now - 60)),
            ]);

            $threads[$threadKey] = ['id' => $threadId, 'author_id' => $authorId, 'posted_at' => $postedAt];
            $repliers = [$authorId];

            foreach ($thread['replies'] as $index => $reply) {
                $replierId = $reply['author'] === 'author' ? $authorId : $this->author($reply['author'], $replyMoments[$index], $repliers);
                $repliers[] = $replierId;
                $replyUpdatedAt = $reply['key'] === null ? $replyMoments[$index] : max($replyMoments[$index], $moderatedAt[$threadKey.'.'.$reply['key']] ?? 0);

                $replyId = ForumReply::query()->insertGetId([
                    'forum_thread_id' => $threadId,
                    'author_id' => $replierId,
                    'body' => $reply['body'],
                    'status' => ForumContentStatus::from($reply['status'])->value,
                    'created_at' => DemoClock::format($replyMoments[$index]),
                    'updated_at' => DemoClock::format($replyUpdatedAt),
                ]);

                if ($reply['key'] !== null) {
                    $replies[$threadKey.'.'.$reply['key']] = ['id' => $replyId, 'author_id' => $replierId, 'posted_at' => $replyMoments[$index]];
                }
            }
        }

        $this->seedReports($threads, $replies);
    }

    /**
     * Load the named authors and the parents who take turns posting.
     */
    private function loadAuthors(): void
    {
        $emails = User::query()->toBase()->pluck('id', 'email');

        $this->namedAuthors = [
            'admin' => (int) $emails[PeopleCatalog::ADMIN['email']],
            'demo-parent' => (int) $emails[PeopleCatalog::DEMO_PARENT_EMAIL],
        ];

        foreach (PeopleCatalog::teachers() as $key => $teacher) {
            $this->namedAuthors['teacher:'.$key] = (int) $emails[$teacher['email']];
        }

        $this->parents = $this->random->shuffle(User::query()
            ->ofRole(UserRole::Parent)
            ->active()
            ->where('email', '!=', PeopleCatalog::DEMO_PARENT_EMAIL)
            ->orderBy('id')
            ->toBase()
            ->get(['id', 'created_at'])
            ->all());
    }

    /**
     * Seed the forum categories and get their IDs keyed by slug.
     *
     * @return array<string, int>
     */
    private function seedCategories(): array
    {
        $rows = [];

        foreach (ForumCatalog::categories() as $slug => $category) {
            $rows[] = [
                'name' => $category['name'],
                'slug' => $slug,
                'description' => $category['description'],
                'color' => $category['color'],
                'icon' => $category['icon'],
                'sort_order' => count($rows) + 1,
                'is_active' => true,
                'created_at' => self::CATEGORIES_CREATED_AT,
                'updated_at' => self::CATEGORIES_CREATED_AT,
            ];
        }

        BulkInsert::models(ForumCategory::class, $rows);

        return ForumCategory::query()->pluck('id', 'slug')->all();
    }

    /**
     * Get when moderators hid content, keyed by thread key or "thread key.reply key".
     *
     * @return array<string, int>
     */
    private function moderationMoments(): array
    {
        $threads = ForumCatalog::threads();
        $moments = [];

        foreach (ForumCatalog::reports() as $report) {
            if ($report['resolution'] !== ForumReport::RESOLUTION_CONTENT_HIDDEN) {
                continue;
            }

            $thread = $threads[$report['thread']];
            $postedAt = $this->clock->now - (int) round($thread['days_ago'] * DemoClock::DAY);
            $contentAt = $report['reply'] === null ? $postedAt : $postedAt + $this->replyOffset($thread, $report['reply']);
            $key = $report['reply'] === null ? $report['thread'] : $report['thread'].'.'.$report['reply'];

            $moments[$key] = $contentAt + (int) round($report['resolved_after'] * DemoClock::HOUR);
        }

        return $moments;
    }

    /**
     * Get the moment of each reply: hours after the thread was posted, always in order and in the past.
     *
     * @param  array{replies: list<array{after: float}>}  $thread
     * @return list<int>
     */
    private function replyMoments(array $thread, int $postedAt): array
    {
        $moments = [];
        $previous = $postedAt;

        foreach ($thread['replies'] as $reply) {
            $previous = min(max($postedAt + (int) round($reply['after'] * DemoClock::HOUR), $previous + 60), $this->clock->now - 5 * 60);
            $moments[] = $previous;
        }

        return $moments;
    }

    /**
     * Get the number of seconds between a thread and one of its keyed replies.
     *
     * @param  array{replies: list<array{after: float, key: string|null}>}  $thread
     */
    private function replyOffset(array $thread, string $replyKey): int
    {
        foreach ($thread['replies'] as $reply) {
            if ($reply['key'] === $replyKey) {
                return (int) round($reply['after'] * DemoClock::HOUR);
            }
        }

        return 0;
    }

    /**
     * Resolve an author: a named author, or the next parent whose account existed at that moment and who has not
     * posted in the thread yet.
     *
     * @param  list<int>  $excluded
     */
    private function author(string $author, int $moment, array $excluded): int
    {
        if ($author !== 'parent') {
            return $this->namedAuthors[$author];
        }

        $count = count($this->parents);

        foreach ([true, false] as $avoidExcluded) {
            for ($step = 0; $step < $count; $step++) {
                $parent = $this->parents[($this->nextParent + $step) % $count];

                if (DemoClock::at($parent->created_at) < $moment && (! $avoidExcluded || ! in_array((int) $parent->id, $excluded, true))) {
                    $this->nextParent = ($this->nextParent + $step + 1) % $count;

                    return (int) $parent->id;
                }
            }
        }

        throw new LogicException('No demo parent account existed on '.DemoClock::format($moment).' to write this forum post.');
    }

    /**
     * Seed the moderation reports: three still open, the others dismissed or resolved by hiding the content.
     *
     * @param  array<string, array{id: int, author_id: int, posted_at: int}>  $threads
     * @param  array<string, array{id: int, author_id: int, posted_at: int}>  $replies
     */
    private function seedReports(array $threads, array $replies): void
    {
        $rows = [];

        foreach (ForumCatalog::reports() as $report) {
            $thread = $threads[$report['thread']];
            $target = $report['reply'] === null ? $thread : $replies[$report['thread'].'.'.$report['reply']];
            $reportedAt = min($target['posted_at'] + (int) round($report['after'] * DemoClock::HOUR), $this->clock->now - 120);
            $resolvedAt = $report['resolution'] === null
                ? null
                : min($target['posted_at'] + (int) round($report['resolved_after'] * DemoClock::HOUR), $this->clock->now - 60);
            $reporterId = $report['reporter'] === 'author'
                ? $thread['author_id']
                : $this->author($report['reporter'], $reportedAt, [$target['author_id']]);

            $rows[] = [
                'forum_thread_id' => $thread['id'],
                'forum_reply_id' => $report['reply'] === null ? null : $target['id'],
                'reporter_id' => $reporterId,
                'reason' => $report['reason'],
                'details' => $report['details'],
                'resolved_at' => $resolvedAt === null ? null : DemoClock::format($resolvedAt),
                'resolved_by' => $resolvedAt === null ? null : $this->namedAuthors['admin'],
                'resolution' => $report['resolution'],
                'created_at' => DemoClock::format($reportedAt),
                'updated_at' => DemoClock::format($resolvedAt ?? $reportedAt),
            ];
        }

        BulkInsert::models(ForumReport::class, $rows);
    }
}
