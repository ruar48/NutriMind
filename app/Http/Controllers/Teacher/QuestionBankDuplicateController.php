<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\QuestionBankItem;
use App\Models\User;
use App\Services\QuestionMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;

class QuestionBankDuplicateController extends Controller
{
    /**
     * Copy a visible item (own or shared) into the teacher's bank: private, not reviewed, never used, with copies of
     * its media files.
     */
    #[Authorize('view', 'item')]
    #[Authorize('create', QuestionBankItem::class)]
    public function store(Request $request, QuestionBankItem $item, QuestionMedia $media): RedirectResponse
    {
        /** @var User $teacher */
        $teacher = $request->user();

        $copy = DB::transaction(fn (): QuestionBankItem => $teacher->questionBankItems()->create([
            ...$media->duplicateContent($item),
            'subject_id' => $item->subject_id,
            'learning_module_id' => $item->learning_module_id,
            'grade_level' => $item->grade_level,
            'topic' => $item->topic,
            'competency_code' => $item->competency_code,
            'difficulty' => $item->difficulty,
            'is_shared' => false,
            'reviewed_at' => null,
        ]));

        return redirect()->route('teacher.question-bank.edit', $copy)
            ->with('status', 'Question copied to your bank. Edit it to make it your own.');
    }
}
