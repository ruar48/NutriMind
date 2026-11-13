<?php

namespace App\Models;

use App\Enums\GradeLevel;
use App\Enums\LessonPlanStatus;
use Database\Factories\LessonPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['subject_id', 'academic_term_id', 'learning_module_id', 'grade_level', 'title', 'competency_code', 'learning_competency', 'objectives', 'week_number', 'starts_on', 'ends_on', 'status', 'notes'])]
class LessonPlan extends Model
{
    /** @use HasFactory<LessonPlanFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'planned',
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
            'week_number' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => LessonPlanStatus::class,
        ];
    }

    /**
     * Get the teacher who wrote the lesson plan.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the subject of the lesson plan.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the academic term of the lesson plan, if any.
     *
     * @return BelongsTo<AcademicTerm, $this>
     */
    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    /**
     * Get the learning module the lesson plan follows, if any.
     *
     * @return BelongsTo<LearningModule, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(LearningModule::class, 'learning_module_id');
    }

    /**
     * Get the learning activities aligned to the lesson plan.
     *
     * @return BelongsToMany<LearningActivity, $this>
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(LearningActivity::class)->withTimestamps();
    }

    /**
     * Get the missions aligned to the lesson plan.
     *
     * @return BelongsToMany<Mission, $this>
     */
    public function missions(): BelongsToMany
    {
        return $this->belongsToMany(Mission::class)->withTimestamps();
    }

    /**
     * Get the quizzes aligned to the lesson plan.
     *
     * @return BelongsToMany<Quiz, $this>
     */
    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class)->withTimestamps();
    }
}
