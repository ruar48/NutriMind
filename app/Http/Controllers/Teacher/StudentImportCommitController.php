<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Services\StudentImport\ImportNotCommitted;
use App\Services\StudentImport\ImportNotPending;
use App\Services\StudentImport\ImportUnavailable;
use App\Services\StudentImport\StudentImports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class StudentImportCommitController extends Controller
{
    /**
     * Import the valid rows of the previewed file into the classroom once, then show the result and the new sign-in details.
     */
    #[Authorize('importStudents', 'classroom')]
    public function store(Request $request, Classroom $classroom, string $import, StudentImports $imports): RedirectResponse
    {
        $pendingImport = $imports->findForClassroom($import, $request->user(), $classroom) ?? abort(404);

        try {
            $result = $imports->commit($pendingImport, $classroom, $request->user());
        } catch (ImportNotPending) {
            abort(404);
        } catch (ImportUnavailable $exception) {
            return to_route('teacher.classrooms.import.create', $classroom)->with('error', $exception->getMessage());
        } catch (ImportNotCommitted $exception) {
            return to_route('teacher.classrooms.import.show', [$classroom, $import])->with('error', $exception->getMessage());
        }

        return to_route('teacher.classrooms.import.create', $classroom)
            ->with('status', $result->statusMessage($classroom))
            ->with('credentials', $result->alertCredentials())
            ->with('importResult', $result->summary($classroom));
    }
}
