<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GuardianRelationship;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGuardianLinkRequest;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Link learners to a parent or guardian account (with the relationship), or unlink them.
 */
class GuardianLinkController extends Controller
{
    /**
     * Link the learner to the parent.
     */
    public function store(StoreGuardianLinkRequest $request, User $guardian): RedirectResponse
    {
        $student = User::query()->findOrFail($request->integer('student_id'));
        $relationship = GuardianRelationship::from($request->validated('relationship'));

        try {
            $guardian->linkedStudents()->attach($student->getKey(), ['relationship' => $relationship->value]);
        } catch (UniqueConstraintViolationException) {
            return back(fallback: route('admin.parents.show', $guardian))
                ->withErrors(['student_id' => StoreGuardianLinkRequest::ALREADY_LINKED_MESSAGE], 'linkLearner');
        }

        return to_route('admin.parents.show', $guardian)
            ->with('status', $student->name.' is now linked to '.$guardian->name.' ('.$relationship->label().').');
    }

    /**
     * Unlink the learner from the parent; a learner who is not linked is reported as missing. With
     * return_to=student the administrator goes back to the learner's profile instead of the parent's.
     */
    public function destroy(Request $request, User $guardian, User $student): RedirectResponse
    {
        abort_unless($guardian->linkedStudents()->whereKey($student->getKey())->exists(), Response::HTTP_NOT_FOUND);

        $guardian->linkedStudents()->detach($student->getKey());

        $redirect = $request->input('return_to') === 'student'
            ? to_route('admin.students.show', $student)
            : to_route('admin.parents.show', $guardian);

        return $redirect->with('status', $student->name.' was unlinked from '.$guardian->name.'.');
    }
}
