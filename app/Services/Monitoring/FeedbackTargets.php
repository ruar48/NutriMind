<?php

namespace App\Services\Monitoring;

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * What a teacher may attach to learner feedback: their learners, the learner's classrooms and quiz attempts.
 *
 * The same rules back the feedback form's choices and its validation, so a value offered in the form is always
 * accepted and a forged one never is.
 */
final class FeedbackTargets
{
    /**
     * The number of recent attempts offered in the feedback form.
     */
    public const ATTEMPT_CHOICES = 30;

    /**
     * Get the learners of the teacher's active classrooms as [id => "Name · Classroom, …"], ordered by name.
     *
     * @return Collection<int, string>
     */
    public function learnerOptions(User $teacher): Collection
    {
        return User::query()
            ->ofRole(UserRole::Student)
            ->whereHas('enrolledClassrooms', fn (Builder $query) => $query->accessibleBy($teacher)->active())
            ->with(['enrolledClassrooms' => fn (BelongsToMany $query) => $query
                ->accessibleBy($teacher)
                ->active()
                ->orderBy('classrooms.name')
                ->orderBy('classrooms.id')])
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (User $student): array => [
                $student->getKey() => $student->name.' · '.$student->enrolledClassrooms->pluck('name')->implode(', '),
            ]);
    }

    /**
     * Get the teacher's classrooms the learner is enrolled in (archived ones included), ordered by name.
     *
     * @return EloquentCollection<int, Classroom>
     */
    public function classrooms(User $teacher, User $student): EloquentCollection
    {
        return $this->classroomsQuery($teacher, $student)
            ->with('subject')
            ->orderBy('classrooms.name')
            ->orderBy('classrooms.id')
            ->get();
    }

    /**
     * Determine whether the classroom is one of the teacher's classrooms the learner is enrolled in.
     */
    public function allowsClassroom(User $teacher, User $student, int $classroomId): bool
    {
        return $this->classroomsQuery($teacher, $student)->whereKey($classroomId)->exists();
    }

    /**
     * Get the learner's latest submitted attempts at the teacher's quizzes, with the quiz loaded.
     *
     * @return EloquentCollection<int, QuizAttempt>
     */
    public function attempts(User $teacher, User $student): EloquentCollection
    {
        return $this->attemptsQuery($teacher, $student)
            ->with('quiz')
            ->latest('submitted_at')
            ->orderByDesc('id')
            ->limit(self::ATTEMPT_CHOICES)
            ->get();
    }

    /**
     * Determine whether the attempt is one of the learner's submitted attempts at the teacher's quizzes.
     */
    public function allowsAttempt(User $teacher, User $student, int $attemptId): bool
    {
        return $this->attemptsQuery($teacher, $student)->whereKey($attemptId)->exists();
    }

    /**
     * Get the attempt as an option label: "Quiz title · Attempt 2 · 80% · Nov 3, 2026".
     */
    public static function attemptLabel(QuizAttempt $attempt): string
    {
        $percentage = rtrim(rtrim(number_format((float) $attempt->percentage, 1), '0'), '.');

        return $attempt->quiz->title
            .' · Attempt '.$attempt->attempt_number
            .' · '.$percentage.'%'
            .($attempt->submitted_at ? ' · '.$attempt->submitted_at->format('M j, Y') : '');
    }

    /**
     * Query the teacher's classrooms (in subjects still assigned to them) the learner is enrolled in.
     *
     * @return BelongsToMany<Classroom, User>
     */
    private function classroomsQuery(User $teacher, User $student): BelongsToMany
    {
        return $student->enrolledClassrooms()->accessibleBy($teacher);
    }

    /**
     * Query the learner's submitted attempts at the teacher's quizzes in subjects still assigned to them.
     *
     * @return HasMany<QuizAttempt, User>
     */
    private function attemptsQuery(User $teacher, User $student): HasMany
    {
        return $student->quizAttempts()
            ->submitted()
            ->whereHas('quiz', fn (Builder $quiz) => $quiz
                ->where('quizzes.teacher_id', $teacher->getKey())
                ->whereIn('quizzes.subject_id', $teacher->assignedSubjectIds()));
    }
}
