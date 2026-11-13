<?php

namespace App\Models;

use App\Enums\PerformanceBand;
use Database\Factories\QuizAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['quiz_id', 'classroom_id', 'attempt_number', 'score', 'max_score', 'percentage', 'passed', 'started_at', 'submitted_at', 'duration_seconds', 'is_offline', 'synced_at'])]
class QuizAttempt extends Model
{
    /** @use HasFactory<QuizAttemptFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'attempt_number' => 1,
        'score' => 0,
        'max_score' => 0,
        'percentage' => 0,
        'passed' => false,
        'is_offline' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'score' => 'integer',
            'max_score' => 'integer',
            'percentage' => 'decimal:2',
            'passed' => 'boolean',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'duration_seconds' => 'integer',
            'is_offline' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Get the quiz that was attempted.
     *
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Get the learner who made the attempt.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the classroom the attempt was made in, if any.
     *
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Get the learner's answers in the attempt.
     *
     * @return HasMany<QuizAttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class);
    }

    /**
     * Get the performance band of the attempt (no data until it is submitted).
     */
    public function band(): PerformanceBand
    {
        return $this->submitted_at === null
            ? PerformanceBand::NoData
            : PerformanceBand::fromScore((float) $this->percentage);
    }

    /**
     * Scope the query to submitted attempts.
     *
     * @param  Builder<QuizAttempt>  $query
     */
    #[Scope]
    protected function submitted(Builder $query): void
    {
        $query->whereNotNull($query->qualifyColumn('submitted_at'));
    }
}
