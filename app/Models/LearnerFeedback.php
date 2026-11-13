<?php

namespace App\Models;

use App\Enums\FeedbackType;
use Database\Factories\LearnerFeedbackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('learner_feedback')]
#[Fillable(['student_id', 'classroom_id', 'quiz_attempt_id', 'type', 'message', 'is_visible_to_guardians', 'read_at'])]
class LearnerFeedback extends Model
{
    /** @use HasFactory<LearnerFeedbackFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_visible_to_guardians' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FeedbackType::class,
            'is_visible_to_guardians' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Get the teacher who wrote the feedback.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the learner the feedback is about.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the classroom the feedback relates to, if any.
     *
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Get the quiz attempt the feedback relates to, if any.
     *
     * @return BelongsTo<QuizAttempt, $this>
     */
    public function quizAttempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class);
    }

    /**
     * Scope the query to feedback that parents and guardians may read.
     *
     * @param  Builder<LearnerFeedback>  $query
     */
    #[Scope]
    protected function visibleToGuardians(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_visible_to_guardians'), true);
    }

    /**
     * Scope the query to feedback no parent or guardian has read yet.
     *
     * @param  Builder<LearnerFeedback>  $query
     */
    #[Scope]
    protected function unread(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('read_at'));
    }
}
