<?php

namespace App\Models;

use App\Enums\GradeLevel;
use App\Enums\QuizStatus;
use Database\Factories\QuizFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['subject_id', 'learning_module_id', 'academic_term_id', 'grade_level', 'title', 'instructions', 'time_limit_minutes', 'passing_score', 'max_attempts', 'shuffle_questions', 'show_correct_answers', 'status', 'reviewed_at', 'published_at'])]
class Quiz extends Model
{
    /** @use HasFactory<QuizFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'passing_score' => 75,
        'shuffle_questions' => false,
        'show_correct_answers' => true,
        'status' => 'draft',
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
            'time_limit_minutes' => 'integer',
            'passing_score' => 'integer',
            'max_attempts' => 'integer',
            'shuffle_questions' => 'boolean',
            'show_correct_answers' => 'boolean',
            'status' => QuizStatus::class,
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get the teacher who created the quiz.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the subject of the quiz.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the learning module the quiz assesses, if any.
     *
     * @return BelongsTo<LearningModule, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(LearningModule::class, 'learning_module_id');
    }

    /**
     * Get the academic term of the quiz, if any.
     *
     * @return BelongsTo<AcademicTerm, $this>
     */
    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    /**
     * Get the quiz questions in display order.
     *
     * @return HasMany<QuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get the classrooms the quiz is assigned to.
     *
     * @return BelongsToMany<Classroom, $this, ClassroomQuiz>
     */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class)
            ->using(ClassroomQuiz::class)
            ->withPivot('available_from', 'due_at', 'assigned_at')
            ->withTimestamps();
    }

    /**
     * Get the learners' attempts at the quiz.
     *
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * Get the lesson plans the quiz is aligned to.
     *
     * @return BelongsToMany<LessonPlan, $this>
     */
    public function lessonPlans(): BelongsToMany
    {
        return $this->belongsToMany(LessonPlan::class)->withTimestamps();
    }

    /**
     * Determine whether the quiz is still a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === QuizStatus::Draft;
    }

    /**
     * Determine whether the quiz is published to learners.
     */
    public function isPublished(): bool
    {
        return $this->status === QuizStatus::Published;
    }

    /**
     * Determine whether the quiz is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === QuizStatus::Closed;
    }

    /**
     * Determine whether the teacher reviewed the quiz since its questions last changed.
     */
    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }

    /**
     * Scope the query to published quizzes.
     *
     * @param  Builder<Quiz>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), QuizStatus::Published);
    }

    /**
     * Scope the query to quizzes with the given status.
     *
     * @param  Builder<Quiz>  $query
     */
    #[Scope]
    protected function ofStatus(Builder $query, QuizStatus $status): void
    {
        $query->where($query->qualifyColumn('status'), $status);
    }
}
