<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\GradeLevel;
use App\Enums\MissionType;
use Database\Factories\MissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subject_id', 'learning_module_id', 'grade_level', 'title', 'description', 'objective', 'type', 'difficulty', 'points_reward', 'starts_on', 'ends_on', 'sort_order', 'is_active'])]
class Mission extends Model
{
    /** @use HasFactory<MissionFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'difficulty' => 'easy',
        'points_reward' => 50,
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade_level' => GradeLevel::class,
            'type' => MissionType::class,
            'difficulty' => Difficulty::class,
            'points_reward' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the subject the mission belongs to.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the learning module the mission belongs to, if any.
     *
     * @return BelongsTo<LearningModule, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(LearningModule::class, 'learning_module_id');
    }

    /**
     * Get the learners' progress on the mission.
     *
     * @return HasMany<MissionProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(MissionProgress::class);
    }

    /**
     * Get the lesson plans the mission is aligned to.
     *
     * @return BelongsToMany<LessonPlan, $this>
     */
    public function lessonPlans(): BelongsToMany
    {
        return $this->belongsToMany(LessonPlan::class)->withTimestamps();
    }

    /**
     * Scope the query to active missions.
     *
     * @param  Builder<Mission>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_active'), true);
    }

    /**
     * Scope the query to missions playable by the grade level (missions without a grade are open to both grades).
     *
     * @param  Builder<Mission>  $query
     */
    #[Scope]
    protected function forGrade(Builder $query, GradeLevel $grade): void
    {
        $query->where(function (Builder $query) use ($grade): void {
            $query->where($query->qualifyColumn('grade_level'), $grade)
                ->orWhereNull($query->qualifyColumn('grade_level'));
        });
    }
}
