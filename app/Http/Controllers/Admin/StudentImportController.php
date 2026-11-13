<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentImportRequest;
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
     * Show the learner CSV upload form (and the result of the import just committed).
     */
    #[Authorize(Permission::ImportStudents)]
    public function create(Request $request, StudentImports $imports): View
    {
        return view('admin.students.import.create', [
            'classroomOptions' => $imports->classroomOptions(),
            'selectedClassroomId' => $request->integer('classroom') ?: null,
        ]);
    }

    /**
     * Store the uploaded file and open its preview.
     */
    #[Authorize(Permission::ImportStudents)]
    public function store(StoreStudentImportRequest $request, PendingImports $pending): RedirectResponse
    {
        $classroom = $request->filled('classroom_id')
            ? Classroom::query()->active()->findOrFail($request->integer('classroom_id'))
            : null;

        $import = $pending->store($request->file('file'), $request->user(), $classroom);

        return to_route('admin.students.import.show', $import->id);
    }

    /**
     * Preview the uploaded file: every row validated, with what importing it will do.
     */
    #[Authorize(Permission::ImportStudents)]
    public function show(Request $request, string $import, PendingImports $pending, StudentImports $imports): View|RedirectResponse
    {
        $pendingImport = $pending->find($import, $request->user()) ?? abort(404);

        try {
            $classroom = $imports->chosenClassroom($pendingImport);
            $preview = $imports->preview($pendingImport, $classroom);
        } catch (ImportUnavailable $exception) {
            return to_route('admin.students.import.create')->with('error', $exception->getMessage());
        }

        $status = $request->enum('status', ImportRowStatus::class);

        return view('admin.students.import.show', [
            'import' => $pendingImport,
            'classroom' => $classroom,
            'preview' => $preview,
            'status' => $status,
            'rows' => $imports->paginateRows($preview, $status),
        ]);
    }
}
