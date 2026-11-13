<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use App\Services\StudentAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class StudentPasswordController extends Controller
{
    /**
     * Give an enrolled learner a new generated password and show their sign-in details once.
     */
    #[Authorize('manageStudents', 'classroom')]
    public function update(Classroom $classroom, User $student, StudentAccounts $accounts): RedirectResponse
    {
        abort_unless($classroom->students()->whereKey($student->id)->exists(), 404);

        $password = $accounts->resetPassword($student);

        return to_route('teacher.classrooms.show', $classroom)
            ->with('status', $student->name.'’s password was reset. Share the new sign-in details now — they are shown only once.')
            ->with('credentials', [[
                'name' => $student->name,
                'username' => $student->username,
                'password' => $password,
            ]]);
    }
}
