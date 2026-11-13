<?php

namespace App\Models;

use App\Enums\ActivityType;
use Database\Factories\LearningActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['learning_module_id', 'title', 'type', 'description', 'content_url', 'estimated_minutes', 'points', 'sort_order', 'is_published'])]
class LearningActivity extends Model
{
    /** @use HasFactory<LearningActivityFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'points' => 10,
        'sort_order' => 0,
        'is_published' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'estimated_minutes' => 'integer',
            'points' => 'integer',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Get the learning module the activity belongs to.
     *
     * @return BelongsTo<LearningModule, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(LearningModule::class, 'learning_module_id');
    }

    /**
     * Get the learners' progress on the activity.
     *
     * @return HasMany<ActivityProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(ActivityProgress::class);
    }

    /**
     * Get the lesson plans the activity is aligned to.
     *
     * @return BelongsToMany<LessonPlan, $this>
     */
    public function lessonPlans(): BelongsToMany
    {
        return $this->belongsToMany(LessonPlan::class)->withTimestamps();
    }

    /**
     * Scope the query to activities that are published in the game.
     *
     * @param  Builder<LearningActivity>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_published'), true);
    }
}
