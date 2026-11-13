<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class ClassroomArchiveController extends Controller
{
    /**
     * Archive the classroom: it keeps its learners and results, read-only.
     */
    #[Authorize('update', 'classroom')]
    public function store(Classroom $classroom): RedirectResponse
    {
        if ($classroom->isArchived()) {
            return to_route('teacher.classrooms.show', $classroom)->with('error', $classroom->name.' is already archived.');
        }

        $classroom->archived_at = now();
        $classroom->save();

        return to_route('teacher.classrooms.show', $classroom)
            ->with('status', $classroom->name.' was archived. Its learners and results stay available, read-only.');
    }

    /**
     * Restore an archived classroom so its learners can be managed again.
     */
    #[Authorize('update', 'classroom')]
    public function destroy(Classroom $classroom): RedirectResponse
    {
        if (! $classroom->isArchived()) {
            return to_route('teacher.classrooms.show', $classroom)->with('error', $classroom->name.' is not archived.');
        }

        $classroom->archived_at = null;
        $classroom->save();

        return to_route('teacher.classrooms.show', $classroom)->with('status', $classroom->name.' was restored.');
    }
}
