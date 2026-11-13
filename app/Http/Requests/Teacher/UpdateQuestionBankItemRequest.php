<?php

namespace App\Http\Requests\Teacher;

use App\Models\QuestionBankItem;
use App\Models\QuizQuestion;
use App\Models\Subject;

/**
 * Validates changes to a question bank item the teacher owns.
 */
class UpdateQuestionBankItemRequest extends StoreQuestionBankItemRequest
{
    /**
     * Get the item being edited.
     */
    public function item(): QuestionBankItem
    {
        return $this->route('item');
    }

    /**
     * Get the subject ids the item may use: the teacher's active assigned subjects and its current subject.
     *
     * @return list<int>
     */
    protected function allowedSubjectIds(): array
    {
        $currentSubjectId = (int) $this->item()->subject_id;

        return $this->assignedSubjects()
            ->filter(fn (Subject $subject): bool => $subject->is_active || (int) $subject->getKey() === $currentSubjectId)
            ->map(fn (Subject $subject): int => (int) $subject->getKey())
            ->values()
            ->all();
    }

    /**
     * Get the item being edited.
     */
    protected function currentQuestion(): QuizQuestion|QuestionBankItem|null
    {
        return $this->item();
    }
}
