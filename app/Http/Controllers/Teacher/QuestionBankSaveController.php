<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Difficulty;
use App\Http\Controllers\Controller;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\QuestionMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;

class QuestionBankSaveController extends Controller
{
    /**
     * Save a copy of a quiz question to the teacher's question bank, filed under the quiz's subject, grade and module.
     */
    #[Authorize('view', 'quiz')]
    #[Authorize('create', QuestionBankItem::class)]
    public function store(Request $request, Quiz $quiz, QuizQuestion $question, QuestionMedia $media): RedirectResponse
    {
        /** @var User $teacher */
        $teacher = $request->user();

        DB::transaction(function () use ($teacher, $quiz, $question, $media): void {
            $teacher->questionBankItems()->create([
                ...$media->duplicateContent($question),
                'subject_id' => $quiz->subject_id,
                'grade_level' => $quiz->grade_level,
                'learning_module_id' => $quiz->learning_module_id,
                'difficulty' => Difficulty::Easy,
                'is_shared' => false,
                'reviewed_at' => $quiz->isReviewed() ? now() : null,
            ]);
        });

        return redirect()->to(route('teacher.quizzes.show', $quiz).'#question-'.$question->id)
            ->with('status', 'Question saved to your question bank.');
    }
}
