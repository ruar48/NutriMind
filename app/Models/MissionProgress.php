<?php

namespace App\Models;

use App\Enums\ProgressStatus;
use Database\Factories\MissionProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('mission_progress')]
#[Fillable(['mission_id', 'status', 'progress_percent', 'points_earned', 'started_at', 'completed_at', 'synced_at'])]
class MissionProgress extends Model
{
    /** @use HasFactory<MissionProgressFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'in_progress',
        'progress_percent' => 0,
        'points_earned' => 0,
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
            'progress_percent' => 'integer',
            'points_earned' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Get the mission the progress is for.
     *
     * @return BelongsTo<Mission, $this>
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
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
     * Scope the query to completed missions.
     *
     * @param  Builder<MissionProgress>  $query
     */
    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), ProgressStatus::Completed);
    }
}
