<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Difficulty;
use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreQuestionBankItemRequest;
use App\Http\Requests\Teacher\UpdateQuestionBankItemRequest;
use App\Models\LearningModule;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use App\Services\QuestionMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuestionBankController extends Controller
{
    /**
     * Items per page of the bank.
     */
    private const PER_PAGE = 18;

    /**
     * List the teacher's own items (tab "mine") or the items other teachers share (tab "shared") in their subjects.
     */
    #[Authorize('viewAny', QuestionBankItem::class)]
    public function index(Request $request): View
    {
        /** @var User $teacher */
        $teacher = $request->user();
        $teacher->assignedSubjectIds();
        $subjectOptions = $teacher->assignedSubjects->sortBy('name')->mapWithKeys(fn (Subject $subject): array => [$subject->id => $subject->name]);

        $tab = $request->query('tab') === 'shared' ? 'shared' : 'mine';
        $subjectId = filter_var($request->query('subject'), FILTER_VALIDATE_INT);
        $grade = filter_var($request->query('grade'), FILTER_VALIDATE_INT);
        $filters = [
            'subject' => $subjectId !== false && $subjectOptions->has($subjectId) ? $subjectId : null,
            'grade' => $grade === false ? null : GradeLevel::tryFrom($grade),
            'type' => is_string($request->query('type')) ? QuestionType::tryFrom($request->query('type')) : null,
            'difficulty' => is_string($request->query('difficulty')) ? Difficulty::tryFrom($request->query('difficulty')) : null,
            'reviewed' => in_array($request->query('reviewed'), ['yes', 'no'], true) ? $request->query('reviewed') : null,
            'search' => is_string($request->query('search')) ? trim($request->query('search')) : '',
        ];

        $filtered = QuestionBankItem::query()
            ->visibleTo($teacher)
            ->when($filters['subject'], fn (Builder $query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters['grade'], fn (Builder $query, GradeLevel $grade) => $query->where('grade_level', $grade))
            ->when($filters['type'], fn (Builder $query, QuestionType $type) => $query->where('type', $type))
            ->when($filters['difficulty'], fn (Builder $query, Difficulty $difficulty) => $query->where('difficulty', $difficulty))
            ->when($filters['reviewed'] === 'yes', fn (Builder $query) => $query->reviewed())
            ->when($filters['reviewed'] === 'no', fn (Builder $query) => $query->whereNull('reviewed_at'))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('prompt', '%'.$filters['search'].'%')
                ->orWhereLike('topic', '%'.$filters['search'].'%')
                ->orWhereLike('competency_code', '%'.$filters['search'].'%')));

        $mine = fn (Builder $query) => $query->where('teacher_id', $teacher->id);
        $sharedByOthers = fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->whereNull('teacher_id')
            ->orWhere('teacher_id', '!=', $teacher->id));

        $items = (clone $filtered)
            ->where($tab === 'mine' ? $mine : $sharedByOthers)
            ->with(['subject', 'teacher', 'module'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('teacher.question-bank.index', [
            'items' => $items,
            'tab' => $tab,
            'counts' => [
                'mine' => (clone $filtered)->where($mine)->count(),
                'shared' => (clone $filtered)->where($sharedByOthers)->count(),
            ],
            'filters' => $filters,
            'subjectOptions' => $subjectOptions->all(),
        ]);
    }

    /**
     * Show the form for a new bank item of the requested type (?type=), optionally prefilled (?subject=, ?grade=).
     */
    #[Authorize('create', QuestionBankItem::class)]
    public function create(Request $request, QuestionMedia $media): View
    {
        $type = is_string($request->query('type')) ? QuestionType::tryFrom($request->query('type')) : null;
        $subjectId = filter_var($request->query('subject'), FILTER_VALIDATE_INT);
        $grade = filter_var($request->query('grade'), FILTER_VALIDATE_INT);

        $item = new QuestionBankItem([
            'type' => $type ?? QuestionType::MultipleChoice,
            'subject_id' => $subjectId === false ? null : $subjectId,
            'grade_level' => $grade === false ? null : GradeLevel::tryFrom($grade),
            'difficulty' => Difficulty::Easy,
        ]);

        return view('teacher.question-bank.form', $this->formData($request->user(), $item, $item->type, $media));
    }

    /**
     * Add the item to the teacher's bank.
     */
    #[Authorize('create', QuestionBankItem::class)]
    public function store(StoreQuestionBankItemRequest $request, QuestionMedia $media): RedirectResponse
    {
        /** @var User $teacher */
        $teacher = $request->user();

        $item = $teacher->questionBankItems()->make([
            ...$request->itemAttributes(),
            'is_shared' => $request->boolean('is_shared') && $teacher->hasPermission(Permission::ShareQuestions),
            'reviewed_at' => $request->boolean('reviewed') ? now() : null,
        ]);

        DB::transaction(fn () => $media->save($item, $request->mediaInput()));

        return redirect()->route('teacher.question-bank.show', $item)->with('status', 'Question added to your bank.');
    }

    /**
     * Preview the item with its answer key and where it is used.
     */
    #[Authorize('view', 'item')]
    public function show(Request $request, QuestionBankItem $item): View
    {
        $item->load(['subject', 'teacher', 'module']);

        return view('teacher.question-bank.show', [
            'item' => $item,
            'quizzes' => Quiz::query()
                ->whereBelongsTo($request->user(), 'teacher')
                ->whereHas('questions', fn (Builder $questions) => $questions->where('question_bank_item_id', $item->id))
                ->orderBy('title')
                ->orderBy('id')
                ->get(['id', 'title', 'status', 'subject_id']),
        ]);
    }

    /**
     * Show the form for editing an item the teacher owns; ?type= switches it to another question type.
     */
    #[Authorize('update', 'item')]
    public function edit(Request $request, QuestionBankItem $item, QuestionMedia $media): View
    {
        $type = is_string($request->query('type')) ? QuestionType::tryFrom($request->query('type')) : null;

        return view('teacher.question-bank.form', $this->formData($request->user(), $item, $type ?? $item->type, $media));
    }

    /**
     * Save the item's changes.
     */
    #[Authorize('update', 'item')]
    public function update(UpdateQuestionBankItemRequest $request, QuestionBankItem $item, QuestionMedia $media): RedirectResponse
    {
        /** @var User $teacher */
        $teacher = $request->user();

        $item->fill([
            ...$request->itemAttributes(),
            'is_shared' => $request->boolean('is_shared') && $teacher->can('share', $item),
            'reviewed_at' => $request->boolean('reviewed') ? ($item->reviewed_at ?? now()) : null,
        ]);

        DB::transaction(fn () => $media->save($item, $request->mediaInput()));

        return redirect()->route('teacher.question-bank.show', $item)->with('status', 'Question saved.');
    }

    /**
     * Delete the item and its media files. Quiz questions copied from it keep their own copies.
     */
    #[Authorize('delete', 'item')]
    public function destroy(QuestionBankItem $item, QuestionMedia $media): RedirectResponse
    {
        DB::transaction(function () use ($item, $media): void {
            $item->delete();
            $media->delete($item->image_path, $item->video_path);
        });

        return redirect()->route('teacher.question-bank.index')->with('status', 'Question deleted from your bank.');
    }

    /**
     * Get the data of the bank item form.
     *
     * @return array<string, mixed>
     */
    private function formData(User $teacher, QuestionBankItem $item, QuestionType $type, QuestionMedia $media): array
    {
        $teacher->assignedSubjectIds();
        $subjects = $teacher->assignedSubjects
            ->filter(fn (Subject $subject): bool => $subject->is_active || ($item->exists && (int) $subject->id === (int) $item->subject_id))
            ->sortBy('name')
            ->values();

        return [
            'item' => $item,
            'type' => $type,
            'subjectOptions' => $subjects->mapWithKeys(fn (Subject $subject): array => [$subject->id => $subject->name])->all(),
            'modules' => LearningModule::query()
                ->whereIn('subject_id', $subjects->modelKeys())
                ->with('subject')
                ->orderBy('subject_id')
                ->orderBy('grade_level')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'canShare' => $item->exists ? $teacher->can('share', $item) : $teacher->hasPermission(Permission::ShareQuestions),
            'mediaLimits' => ['image' => $media->imageLimitLabel(), 'video' => $media->videoLimitLabel()],
        ];
    }
}
