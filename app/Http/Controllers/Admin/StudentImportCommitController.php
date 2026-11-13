<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\StudentImport\ImportNotCommitted;
use App\Services\StudentImport\ImportNotPending;
use App\Services\StudentImport\ImportUnavailable;
use App\Services\StudentImport\PendingImports;
use App\Services\StudentImport\StudentImports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class StudentImportCommitController extends Controller
{
    /**
     * Import the valid rows of the previewed file once, then show the result and the new sign-in details.
     */
    #[Authorize(Permission::ImportStudents)]
    public function store(Request $request, string $import, PendingImports $pending, StudentImports $imports): RedirectResponse
    {
        $pendingImport = $pending->find($import, $request->user()) ?? abort(404);

        try {
            $classroom = $imports->chosenClassroom($pendingImport);
            $result = $imports->commit($pendingImport, $classroom, $request->user());
        } catch (ImportNotPending) {
            abort(404);
        } catch (ImportUnavailable $exception) {
            return to_route('admin.students.import.create')->with('error', $exception->getMessage());
        } catch (ImportNotCommitted $exception) {
            return to_route('admin.students.import.show', $import)->with('error', $exception->getMessage());
        }

        return to_route('admin.students.import.create')
            ->with('status', $result->statusMessage($classroom))
            ->with('credentials', $result->alertCredentials())
            ->with('importResult', $result->summary($classroom));
    }
}
