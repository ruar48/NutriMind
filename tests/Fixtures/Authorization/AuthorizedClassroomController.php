<?php

namespace Tests\Fixtures\Authorization;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * Test-only controller that uses each form of the #[Authorize] attribute together with a Form Request.
 */
class AuthorizedClassroomController extends Controller
{
    /**
     * Authorize against a route model: the {classroom} parameter is passed to ClassroomPolicy::update().
     */
    #[Authorize('update', 'classroom')]
    public function update(RenameClassroomRequest $request, Classroom $classroom): Response
    {
        return response('updated '.$classroom->id.' to '.$request->validated('name'));
    }

    /**
     * Authorize against a model class: ClassroomPolicy::create() receives no model.
     */
    #[Authorize('create', Classroom::class)]
    public function store(RenameClassroomRequest $request): Response
    {
        return response('created '.$request->validated('name'));
    }

    /**
     * Authorize against a permission gate defined by AppServiceProvider.
     */
    #[Authorize(Permission::ExportReports)]
    public function export(RenameClassroomRequest $request): Response
    {
        return response('exported '.$request->validated('name'));
    }
}
