<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\QuizStatus;
use App\Http\Controllers\Controller;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\User;
use App\Services\QuestionMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuizDuplicateController extends Controller
{
    /**
     * The longest quiz title.
     */
    private const TITLE_MAX_LENGTH = 150;

    /**
     * Copy the quiz as a new draft: same settings and questions (with copies of their media files), no classrooms,
     * not reviewed and not published.
     */
    #[Authorize('update', 'quiz')]
    public function store(Request $request, Quiz $quiz, QuestionMedia $media): RedirectResponse
    {
        /** @var User $teacher */
        $teacher = $request->user();
        $quiz->load('questions');

        $copy = DB::transaction(function () use ($teacher, $quiz, $media): Quiz {
            $copy = $teacher->quizzes()->create([
                ...$quiz->only([
                    'subject_id', 'learning_module_id', 'academic_term_id', 'grade_level', 'instructions', 'time_limit_minutes',
                    'passing_score', 'max_attempts', 'shuffle_questions', 'show_correct_answers',
                ]),
                'title' => rtrim(Str::limit('Copy of '.$quiz->title, self::TITLE_MAX_LENGTH, '')),
                'status' => QuizStatus::Draft,
                'reviewed_at' => null,
                'published_at' => null,
            ]);

            foreach ($quiz->questions->values() as $index => $question) {
                $copy->questions()->create([
                    ...$media->duplicateContent($question),
                    'question_bank_item_id' => $question->question_bank_item_id,
                    'points' => $question->points,
                    'sort_order' => $index + 1,
                ]);
            }

            $quiz->questions->whereNotNull('question_bank_item_id')->countBy('question_bank_item_id')
                ->each(fn (int $copies, int|string $itemId) => QuestionBankItem::query()->whereKey($itemId)->toBase()->increment('times_used', $copies));

            return $copy;
        });

        return redirect()->route('teacher.quizzes.show', $copy)
            ->with('status', 'Quiz duplicated. The copy is a draft without classrooms: review it, assign classrooms, then publish.');
    }
}
