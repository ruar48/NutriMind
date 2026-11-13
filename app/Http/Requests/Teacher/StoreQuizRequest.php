<?php

namespace App\Http\Requests\Teacher;

use App\Models\Subject;

/**
 * Validates a new quiz: its settings and the classrooms it is assigned to.
 */
class StoreQuizRequest extends QuizSettingsRequest
{
    /**
     * Get the subject ids a new quiz may use: the teacher's active assigned subjects.
     *
     * @return list<int>
     */
    protected function allowedSubjectIds(): array
    {
        $this->teacher()->assignedSubjectIds();

        return $this->teacher()->assignedSubjects
            ->where('is_active', true)
            ->map(fn (Subject $subject): int => (int) $subject->getKey())
            ->values()
            ->all();
    }
}
