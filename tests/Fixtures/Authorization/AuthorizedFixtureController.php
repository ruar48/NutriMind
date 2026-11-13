<?php

namespace Tests\Fixtures\Authorization;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Test-only controller that uses each form of the #[Authorize] attribute together with a Form Request.
 */
class AuthorizedFixtureController extends Controller
{
    /**
     * Authorize against an implicitly bound route model: {classroom} is passed to ClassroomPolicy::update().
     */
    #[Authorize('update', 'classroom')]
    public function update(NamePayloadRequest $request, Classroom $classroom): Response
    {
        return response('updated '.$classroom->id.' to '.$request->validated('name'));
    }

    /**
     * Authorize against a model class: ClassroomPolicy::create() receives no model.
     */
    #[Authorize('create', Classroom::class)]
    public function store(NamePayloadRequest $request): Response
    {
        return response('created '.$request->validated('name'));
    }

    /**
     * Authorize against a permission gate defined by AppServiceProvider.
     */
    #[Authorize(Permission::ExportReports)]
    public function export(NamePayloadRequest $request): Response
    {
        return response('exported '.$request->validated('name'));
    }

    /**
     * Authorize against an explicitly bound, role-restricted {student}: the learner is passed to UserPolicy.
     */
    #[Authorize('giveFeedbackTo', 'student')]
    public function feedback(NamePayloadRequest $request, User $student): Response
    {
        return response('feedback for '.$student->id.': '.$request->validated('name'));
    }
}
