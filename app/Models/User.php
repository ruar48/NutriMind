<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Services\RolePermissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
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
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the learner profile of a student account.
     *
     * @return HasOne<StudentProfile, $this>
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * Get the subjects an administrator assigned to the teacher.
     *
     * @return BelongsToMany<Subject, $this>
     */
    public function assignedSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher', 'teacher_id', 'subject_id')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * Get the classrooms the teacher handles.
     *
     * @return HasMany<Classroom, $this>
     */
    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'teacher_id');
    }

    /**
     * Get the classrooms the learner is enrolled in.
     *
     * @return BelongsToMany<Classroom, $this, ClassroomStudent>
     */
    public function enrolledClassrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_student', 'student_id', 'classroom_id')
            ->using(ClassroomStudent::class)
            ->withPivot('enrolled_at')
            ->withTimestamps();
    }

    /**
     * Get the learners linked to the parent or guardian.
     *
     * @return BelongsToMany<User, $this, GuardianStudent>
     */
    public function linkedStudents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_student', 'guardian_id', 'student_id')
            ->using(GuardianStudent::class)
            ->withPivot('relationship')
            ->withTimestamps();
    }

    /**
     * Get the parents and guardians linked to the learner.
     *
     * @return BelongsToMany<User, $this, GuardianStudent>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_student', 'student_id', 'guardian_id')
            ->using(GuardianStudent::class)
            ->withPivot('relationship')
            ->withTimestamps();
    }

    /**
     * Get the quizzes the teacher created.
     *
     * @return HasMany<Quiz, $this>
     */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'teacher_id');
    }

    /**
     * Get the question bank items the teacher owns.
     *
     * @return HasMany<QuestionBankItem, $this>
     */
    public function questionBankItems(): HasMany
    {
        return $this->hasMany(QuestionBankItem::class, 'teacher_id');
    }

    /**
     * Get the lesson plans the teacher wrote.
     *
     * @return HasMany<LessonPlan, $this>
     */
    public function lessonPlans(): HasMany
    {
        return $this->hasMany(LessonPlan::class, 'teacher_id');
    }

    /**
     * Get the learner's quiz attempts.
     *
     * @return HasMany<QuizAttempt, $this>
     */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'student_id');
    }

    /**
     * Get the learner's mission progress records.
     *
     * @return HasMany<MissionProgress, $this>
     */
    public function missionProgress(): HasMany
    {
        return $this->hasMany(MissionProgress::class, 'student_id');
    }

    /**
     * Get the learner's learning activity progress records.
     *
     * @return HasMany<ActivityProgress, $this>
     */
    public function activityProgress(): HasMany
    {
        return $this->hasMany(ActivityProgress::class, 'student_id');
    }

    /**
     * Get the rewards the learner earned.
     *
     * @return BelongsToMany<Reward, $this, RewardStudent>
     */
    public function rewards(): BelongsToMany
    {
        return $this->belongsToMany(Reward::class, 'reward_student', 'student_id', 'reward_id')
            ->using(RewardStudent::class)
            ->withPivot('awarded_at')
            ->withTimestamps();
    }

    /**
     * Get the feedback teachers wrote for the learner.
     *
     * @return HasMany<LearnerFeedback, $this>
     */
    public function feedbackReceived(): HasMany
    {
        return $this->hasMany(LearnerFeedback::class, 'student_id');
    }

    /**
     * Get the feedback the teacher wrote.
     *
     * @return HasMany<LearnerFeedback, $this>
     */
    public function feedbackGiven(): HasMany
    {
        return $this->hasMany(LearnerFeedback::class, 'teacher_id');
    }

    /**
     * Get the forum threads the user started.
     *
     * @return HasMany<ForumThread, $this>
     */
    public function forumThreads(): HasMany
    {
        return $this->hasMany(ForumThread::class, 'author_id');
    }

    /**
     * Get the forum replies the user wrote.
     *
     * @return HasMany<ForumReply, $this>
     */
    public function forumReplies(): HasMany
    {
        return $this->hasMany(ForumReply::class, 'author_id');
    }

    /**
     * Get the forum reports the user filed.
     *
     * @return HasMany<ForumReport, $this>
     */
    public function forumReports(): HasMany
    {
        return $this->hasMany(ForumReport::class, 'reporter_id');
    }

    /**
     * Determine whether the user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Determine whether the user is a teacher.
     */
    public function isTeacher(): bool
    {
        return $this->role === UserRole::Teacher;
    }

    /**
     * Determine whether the user is a parent or guardian.
     */
    public function isParent(): bool
    {
        return $this->role === UserRole::Parent;
    }

    /**
     * Determine whether the user is a learner.
     */
    public function isStudent(): bool
    {
        return $this->role === UserRole::Student;
    }

    /**
     * Determine whether the user has any of the given roles.
     */
    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Determine whether the administrator-editable permission matrix grants the permission to the user.
     */
    public function hasPermission(Permission $permission): bool
    {
        return app(RolePermissions::class)->allows($this, $permission);
    }

    /**
     * Determine whether the subject is assigned to the teacher.
     */
    public function teachesSubject(Subject|int $subject): bool
    {
        $subjectId = $subject instanceof Subject ? $subject->getKey() : $subject;

        return in_array((int) $subjectId, $this->assignedSubjectIds(), true);
    }

    /**
     * Get the IDs of the subjects assigned to the teacher.
     *
     * The assignedSubjects relation is loaded once and reused, so repeated checks on the same
     * instance cost a single query. After changing assignments through this same instance, call
     * refresh() or unsetRelation('assignedSubjects') to read them again.
     *
     * @return list<int>
     */
    public function assignedSubjectIds(): array
    {
        return array_map(
            fn (mixed $subjectId): int => (int) $subjectId,
            $this->loadMissing('assignedSubjects')->assignedSubjects->modelKeys(),
        );
    }

    /**
     * Get up to two uppercase initials from the user's name, ignoring generational suffixes.
     *
     * @return Attribute<string, never>
     */
    protected function initials(): Attribute
    {
        return Attribute::get(function (): string {
            $words = preg_split('/\s+/u', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            $words = array_values(array_filter(
                $words,
                fn (string $word): bool => preg_match('/^(jr|sr|ii|iii|iv)\.?$/iu', $word) !== 1,
            ));

            $letters = [];

            foreach ($words as $word) {
                if (preg_match('/\p{L}/u', $word, $match) === 1) {
                    $letters[] = $match[0];
                }
            }

            if ($letters === []) {
                return '?';
            }

            $initials = count($letters) > 1 ? $letters[0].$letters[count($letters) - 1] : $letters[0];

            return mb_strtoupper($initials);
        });
    }

    /**
     * Scope the query to users with the given role.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function ofRole(Builder $query, UserRole $role): void
    {
        $query->where($query->qualifyColumn('role'), $role);
    }

    /**
     * Scope the query to active accounts.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_active'), true);
    }

    /**
     * Scope the query to users whose name, email or username contains the term; a blank term changes nothing.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $pattern = '%'.trim($term).'%';

        $query->where(function (Builder $query) use ($pattern): void {
            $query->whereLike($query->qualifyColumn('name'), $pattern)
                ->orWhereLike($query->qualifyColumn('email'), $pattern)
                ->orWhereLike($query->qualifyColumn('username'), $pattern);
        });
    }
}
