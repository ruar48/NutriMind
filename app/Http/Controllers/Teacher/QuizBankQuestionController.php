<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\Concerns\ManagesQuizQuestions;
use App\Http\Requests\Teacher\StoreQuizBankQuestionRequest;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\User;
use App\Services\QuestionMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuizBankQuestionController extends Controller
{
    use ManagesQuizQuestions;

    /**
     * Bank items per page of the picker.
     */
    private const PER_PAGE = 20;

    /**
     * List the bank items the teacher may add to the quiz: visible to them, of the quiz's subject and grade.
     */
    #[Authorize('update', 'quiz')]
    #[Authorize('viewAny', QuestionBankItem::class)]
    public function create(Request $request, Quiz $quiz): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfQuestionsLocked($quiz)) {
            return $redirect;
        }

        /** @var User $teacher */
        $teacher = $request->user();
        $filters = [
            'difficulty' => is_string($request->query('difficulty')) ? Difficulty::tryFrom($request->query('difficulty')) : null,
            'type' => is_string($request->query('type')) ? QuestionType::tryFrom($request->query('type')) : null,
            'reviewed' => $request->query('reviewed') === '1',
            'search' => is_string($request->query('search')) ? trim($request->query('search')) : '',
        ];

        $items = QuestionBankItem::query()
            ->visibleTo($teacher)
            ->where('subject_id', $quiz->subject_id)
            ->where('grade_level', $quiz->grade_level)
            ->when($filters['difficulty'], fn (Builder $query, Difficulty $difficulty) => $query->where('difficulty', $difficulty))
            ->when($filters['type'], fn (Builder $query, QuestionType $type) => $query->where('type', $type))
            ->when($filters['reviewed'], fn (Builder $query) => $query->reviewed())
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('prompt', '%'.$filters['search'].'%')
                ->orWhereLike('topic', '%'.$filters['search'].'%')
                ->orWhereLike('competency_code', '%'.$filters['search'].'%')))
            ->with(['teacher', 'module'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('teacher.quizzes.bank-questions.create', [
            'quiz' => $quiz->load('subject'),
            'items' => $items,
            'filters' => $filters,
            'addedItemIds' => $quiz->questions()
                ->whereNotNull('question_bank_item_id')
                ->pluck('question_bank_item_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all(),
            'hasAnyItems' => QuestionBankItem::query()
                ->visibleTo($teacher)
                ->where('subject_id', $quiz->subject_id)
                ->where('grade_level', $quiz->grade_level)
                ->exists(),
        ]);
    }

    /**
     * Copy the selected bank items to the end of the quiz and count each use.
     */
    #[Authorize('update', 'quiz')]
    #[Authorize('viewAny', QuestionBankItem::class)]
    public function store(StoreQuizBankQuestionRequest $request, Quiz $quiz, QuestionMedia $media): RedirectResponse
    {
        if ($redirect = $this->redirectIfQuestionsLocked($quiz)) {
            return $redirect;
        }

        $items = $request->items();

        DB::transaction(function () use ($quiz, $items, $media): void {
            $sortOrder = $this->nextSortOrder($quiz);

            foreach ($items as $item) {
                $quiz->questions()->create([
                    ...$media->duplicateContent($item),
                    'question_bank_item_id' => $item->id,
                    'points' => 1,
                    'sort_order' => $sortOrder++,
                ]);

                QuestionBankItem::query()->whereKey($item->id)->toBase()->increment('times_used');
            }

            $this->markQuestionsChanged($quiz);
        });

        $count = count($items);

        return redirect()->to($this->builderUrl($quiz))
            ->with('status', trans_choice(':count question was added from the question bank.|:count questions were added from the question bank.', $count));
    }
}
