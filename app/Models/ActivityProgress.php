<?php

namespace App\Models;

use App\Enums\ProgressStatus;
use Database\Factories\ActivityProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('activity_progress')]
#[Fillable(['learning_activity_id', 'status', 'score', 'points_earned', 'time_spent_seconds', 'completed_at', 'synced_at'])]
class ActivityProgress extends Model
{
    /** @use HasFactory<ActivityProgressFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'in_progress',
        'points_earned' => 0,
        'time_spent_seconds' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProgressStatus::class,
            'score' => 'integer',
            'points_earned' => 'integer',
            'time_spent_seconds' => 'integer',
            'completed_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Get the learning activity the progress is for.
     *
     * @return BelongsTo<LearningActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(LearningActivity::class, 'learning_activity_id');
    }

    /**
     * Get the learner who made the progress.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Scope the query to completed activities.
     *
     * @param  Builder<ActivityProgress>  $query
     */
    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), ProgressStatus::Completed);
    }
}
