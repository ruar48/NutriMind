<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreStudentImportRequest;
use App\Models\Classroom;
use App\Services\StudentImport\ImportRowStatus;
use App\Services\StudentImport\ImportUnavailable;
use App\Services\StudentImport\PendingImports;
use App\Services\StudentImport\StudentImports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class StudentImportController extends Controller
{
    /**
     * Show the learner CSV upload form of the classroom (and the result of the import just committed).
     */
    #[Authorize('importStudents', 'classroom')]
    public function create(Classroom $classroom): View
    {
        $classroom->load('subject')->loadCount('students');

        return view('teacher.imports.create', [
            'classroom' => $classroom,
        ]);
    }

    /**
     * Store the uploaded file and open its preview.
     */
    #[Authorize('importStudents', 'classroom')]
    public function store(StoreStudentImportRequest $request, Classroom $classroom, PendingImports $pending): RedirectResponse
    {
        $import = $pending->store($request->file('file'), $request->user(), $classroom);

        return to_route('teacher.classrooms.import.show', [$classroom, $import->id]);
    }

    /**
     * Preview the uploaded file: every row validated against the classroom, with what importing it will do.
     */
    #[Authorize('importStudents', 'classroom')]
    public function show(Request $request, Classroom $classroom, string $import, StudentImports $imports): View|RedirectResponse
    {
        $pendingImport = $imports->findForClassroom($import, $request->user(), $classroom) ?? abort(404);

        try {
            $preview = $imports->preview($pendingImport, $classroom);
        } catch (ImportUnavailable $exception) {
            return to_route('teacher.classrooms.import.create', $classroom)->with('error', $exception->getMessage());
        }

        $status = $request->enum('status', ImportRowStatus::class);
        $classroom->load('subject');

        return view('teacher.imports.show', [
            'import' => $pendingImport,
            'classroom' => $classroom,
            'preview' => $preview,
            'status' => $status,
            'rows' => $imports->paginateRows($preview, $status),
        ]);
    }
}
