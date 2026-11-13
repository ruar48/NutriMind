<?php

namespace Database\Factories\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps teacher-owned, subject-scoped records consistent with the subject assignment matrix.
 */
trait AssignsSubjectToTeacher
{
    /**
     * Indicate that the record's subject is not (or no longer) assigned to its teacher.
     */
    public function revokedSubject(): static
    {
        return $this->afterCreating(function (Model $record): void {
            $this->teacherOf($record)?->assignedSubjects()->detach($record->getAttribute('subject_id'));
        });
    }

    /**
     * Assign the record's subject to its teacher unless it is already assigned.
     */
    protected function assignSubjectToTeacher(Model $record): void
    {
        $this->teacherOf($record)?->assignedSubjects()->syncWithoutDetaching([$record->getAttribute('subject_id')]);
    }

    /**
     * Load a fresh instance of the record's teacher, if the record has one.
     */
    private function teacherOf(Model $record): ?User
    {
        $teacherId = $record->getAttribute('teacher_id');

        return $teacherId === null ? null : User::query()->findOrFail($teacherId);
    }
}
