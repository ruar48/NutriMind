<?php

namespace App\Models;

use App\Enums\GradeLevel;
use App\Enums\LearningMode;
use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['school_year_id', 'subject_id', 'name', 'grade_level', 'section', 'room', 'description', 'learning_mode'])]
class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory;

    /**
     * The characters a class code is built from (no 0/O or 1/I look-alikes).
     */
    public const CLASS_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * The number of characters in a generated class code.
     */
    public const CLASS_CODE_LENGTH = 6;

    /**
     * How many candidates are drawn from Str::random() before switching to random_int().
     *
     * Str::random() can be faked to a fixed string in tests; switching sources keeps generation from looping forever.
     */
    private const RANDOM_STRING_ATTEMPTS = 5;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'learning_mode' => 'automatic',
    ];

    /**
     * Bootstrap the model's event listeners.
     */
    protected static function booted(): void
    {
        static::creating(function (Classroom $classroom): void {
            if (blank($classroom->class_code)) {
                $classroom->class_code = self::generateClassCode();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade_level' => GradeLevel::class,
            'learning_mode' => LearningMode::class,
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Get the school year the classroom belongs to.
     *
     * @return BelongsTo<SchoolYear, $this>
     */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    /**
     * Get the subject the classroom studies.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the teacher who handles the classroom.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the learners enrolled in the classroom.
     *
     * @return BelongsToMany<User, $this, ClassroomStudent>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_student', 'classroom_id', 'student_id')
            ->using(ClassroomStudent::class)
            ->withPivot('enrolled_at')
            ->withTimestamps();
    }

    /**
     * Get the quizzes assigned to the classroom.
     *
     * @return BelongsToMany<Quiz, $this, ClassroomQuiz>
     */
    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class)
            ->using(ClassroomQuiz::class)
            ->withPivot('available_from', 'due_at', 'assigned_at')
            ->withTimestamps();
    }

    /**
     * Get the quiz attempts submitted in the classroom.
     *
     * @return HasMany<QuizAttempt, $this>
     */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * Determine whether the classroom has been archived.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Generate a class code that no classroom uses yet and that is not one of the given codes.
     *
     * @param  list<string>  $except  Codes to avoid besides those already stored, e.g. codes handed out to unsaved classrooms.
     */
    public static function generateClassCode(array $except = []): string
    {
        $attempts = 0;

        do {
            $code = ++$attempts <= self::RANDOM_STRING_ATTEMPTS
                ? self::classCodeFromRandomString()
                : self::classCodeFromRandomIntegers();
        } while (in_array($code, $except, true) || static::query()->where('class_code', $code)->exists());

        return $code;
    }

    /**
     * Build a class code from the alphabet characters of a random string.
     */
    private static function classCodeFromRandomString(): string
    {
        $characters = preg_replace('/[^'.self::CLASS_CODE_ALPHABET.']/', '', Str::random(64));

        return strlen($characters) >= self::CLASS_CODE_LENGTH
            ? substr($characters, 0, self::CLASS_CODE_LENGTH)
            : self::classCodeFromRandomIntegers();
    }

    /**
     * Build a class code from cryptographically secure random integers.
     */
    private static function classCodeFromRandomIntegers(): string
    {
        $code = '';

        for ($position = 0; $position < self::CLASS_CODE_LENGTH; $position++) {
            $code .= self::CLASS_CODE_ALPHABET[random_int(0, strlen(self::CLASS_CODE_ALPHABET) - 1)];
        }

        return $code;
    }

    /**
     * Scope the query to classrooms that are not archived.
     *
     * @param  Builder<Classroom>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('archived_at'));
    }

    /**
     * Scope the query to archived classrooms.
     *
     * @param  Builder<Classroom>  $query
     */
    #[Scope]
    protected function archived(Builder $query): void
    {
        $query->whereNotNull($query->qualifyColumn('archived_at'));
    }

    /**
     * Scope the query to the teacher's own classrooms in subjects still assigned to the teacher.
     *
     * @param  Builder<Classroom>  $query
     */
    #[Scope]
    protected function accessibleBy(Builder $query, User $teacher): void
    {
        $query->where($query->qualifyColumn('teacher_id'), $teacher->getKey())
            ->whereIn($query->qualifyColumn('subject_id'), $teacher->assignedSubjectIds());
    }
}
