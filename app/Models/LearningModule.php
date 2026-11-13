<?php

namespace App\Models;

use App\Enums\GradeLevel;
use Database\Factories\LearningModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subject_id', 'grade_level', 'quarter', 'title', 'world_name', 'description', 'competency_code', 'learning_competency', 'sort_order', 'is_published'])]
class LearningModule extends Model
{
    /** @use HasFactory<LearningModuleFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
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
            'grade_level' => GradeLevel::class,
            'quarter' => 'integer',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Get the subject the module teaches.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the module's learning activities in play order.
     *
     * @return HasMany<LearningActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(LearningActivity::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get the missions that belong to the module.
     *
     * @return HasMany<Mission, $this>
     */
    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    /**
     * Get the quizzes written for the module.
     *
     * @return HasMany<Quiz, $this>
     */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    /**
     * Scope the query to modules that are published in the game.
     *
     * @param  Builder<LearningModule>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_published'), true);
    }

    /**
     * Scope the query to modules for the given grade level.
     *
     * @param  Builder<LearningModule>  $query
     */
    #[Scope]
    protected function forGrade(Builder $query, GradeLevel $grade): void
    {
        $query->where($query->qualifyColumn('grade_level'), $grade);
    }
}
