<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\GradeLevel;
use App\Models\Concerns\HasQuestionContent;
use Database\Factories\QuestionBankItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subject_id', 'learning_module_id', 'grade_level', 'topic', 'competency_code', 'type', 'prompt', 'choices', 'correct_answer', 'explanation', 'image_path', 'video_path', 'video_url', 'difficulty', 'is_shared', 'reviewed_at'])]
class QuestionBankItem extends Model
{
    /** @use HasFactory<QuestionBankItemFactory> */
    use HasFactory, HasQuestionContent;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'difficulty' => 'easy',
        'is_shared' => false,
        'times_used' => 0,
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
            'difficulty' => Difficulty::class,
            'is_shared' => 'boolean',
            'reviewed_at' => 'datetime',
            'times_used' => 'integer',
        ];
    }

    /**
     * Get the teacher who owns the item (null once that account is deleted).
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the subject of the item.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the learning module the item belongs to, if any.
     *
     * @return BelongsTo<LearningModule, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(LearningModule::class, 'learning_module_id');
    }

    /**
     * Get the quiz questions copied from the item.
     *
     * @return HasMany<QuizQuestion, $this>
     */
    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    /**
     * Scope the query to items the teacher may see: in a subject still assigned to the teacher,
     * and either owned by the teacher or shared.
     *
     * @param  Builder<QuestionBankItem>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $teacher): void
    {
        $query->whereIn($query->qualifyColumn('subject_id'), $teacher->assignedSubjectIds())
            ->where(function (Builder $query) use ($teacher): void {
                $query->where($query->qualifyColumn('teacher_id'), $teacher->getKey())
                    ->orWhere($query->qualifyColumn('is_shared'), true);
            });
    }

    /**
     * Scope the query to items a teacher marked as reviewed.
     *
     * @param  Builder<QuestionBankItem>  $query
     */
    #[Scope]
    protected function reviewed(Builder $query): void
    {
        $query->whereNotNull($query->qualifyColumn('reviewed_at'));
    }
}
