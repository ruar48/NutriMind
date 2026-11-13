<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreEnrollmentRequest;
use App\Models\Classroom;
use App\Models\User;
use App\Services\StudentAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    /**
     * Enroll existing learners of the classroom's grade in the classroom.
     */
    #[Authorize('manageStudents', 'classroom')]
    public function store(StoreEnrollmentRequest $request, Classroom $classroom, StudentAccounts $accounts): RedirectResponse
    {
        $students = User::query()
            ->whereKey($request->validated('student_ids'))
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        DB::transaction(function () use ($students, $classroom, $accounts): void {
            foreach ($students as $student) {
                $accounts->enroll($student, $classroom);
            }
        });

        $message = $students->count() === 1
            ? $students->first()->name.' was enrolled in '.$classroom->name.'.'
            : $students->count().' learners were enrolled in '.$classroom->name.'.';

        return to_route('teacher.classrooms.show', $classroom)->with('status', $message);
    }
}
