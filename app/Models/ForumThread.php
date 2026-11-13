<?php

namespace App\Models;

use App\Enums\ForumContentStatus;
use Database\Factories\ForumThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['forum_category_id', 'title', 'body', 'status', 'is_pinned', 'is_locked', 'last_activity_at'])]
class ForumThread extends Model
{
    /** @use HasFactory<ForumThreadFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'published',
        'is_pinned' => false,
        'is_locked' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ForumContentStatus::class,
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Get the category the thread is posted in.
     *
     * @return BelongsTo<ForumCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'forum_category_id');
    }

    /**
     * Get the user who started the thread.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the replies to the thread.
     *
     * @return HasMany<ForumReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ForumReply::class);
    }

    /**
     * Get the reports filed against the thread or its replies.
     *
     * @return HasMany<ForumReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ForumReport::class);
    }

    /**
     * Scope the query to published threads.
     *
     * @param  Builder<ForumThread>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), ForumContentStatus::Published);
    }

    /**
     * Scope the query to threads the user may see: administrators see every thread, everyone else
     * sees published threads and their own.
     *
     * @param  Builder<ForumThread>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->where($query->qualifyColumn('status'), ForumContentStatus::Published)
                ->orWhere($query->qualifyColumn('author_id'), $user->getKey());
        });
    }
}
