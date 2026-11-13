<?php

namespace App\Http\Requests\Teacher;

use App\Models\QuestionBankItem;
use App\Models\QuizQuestion;

/**
 * Validates changes to a question of a draft quiz.
 */
class UpdateQuizQuestionRequest extends StoreQuizQuestionRequest
{
    /**
     * Get the question being edited.
     */
    protected function currentQuestion(): QuizQuestion|QuestionBankItem|null
    {
        return $this->route('question');
    }
}
