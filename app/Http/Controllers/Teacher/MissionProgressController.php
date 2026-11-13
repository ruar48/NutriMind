<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\MissionType;
use App\Enums\ProgressStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\Concerns\ReadsMonitoringFilters;
use App\Models\Mission;
use App\Services\Monitoring\LearnerRoster;
use App\Services\Monitoring\MissionProgressReport;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class MissionProgressController extends Controller
{
    use ReadsMonitoringFilters;

    /**
     * List the active missions of the teacher's subjects with each mission's completion in the teacher's classrooms.
     */
    #[Authorize('viewAnyProgress', Mission::class)]
    public function index(Request $request, LearnerRoster $roster, MissionProgressReport $report): View
    {
        $teacher = $request->user();
        $classrooms = $roster->classroomsFor($teacher);
        $classroom = $this->classroomFilter($request, $classrooms);
        $missions = $report->missions(
            $teacher,
            $classroom,
            $this->enumFilter($request, 'type', MissionType::class),
            $this->searchFilter($request),
        );

        return view('teacher.missions.index', [
            'classrooms' => $classrooms,
            'classroom' => $classroom,
            'missions' => $missions,
            'completion' => $report->completionFor(
                $missions->getCollection(),
                $classroom === null ? $classrooms->modelKeys() : [$classroom->getKey()],
            ),
            'classroomCompletion' => $report->classroomCompletion($classrooms),
        ]);
    }

    /**
     * Show every eligible learner's status on the mission, for all the teacher's matching classrooms or one of them.
     */
    #[Authorize('viewProgress', 'mission')]
    public function show(Request $request, Mission $mission, LearnerRoster $roster, MissionProgressReport $report): View
    {
        $mission->load(['subject', 'module']);

        $classrooms = $report->eligibleClassrooms($mission, $roster->classroomsFor($request->user()));
        $classroom = $this->classroomFilter($request, $classrooms);
        $classroomIds = $classroom === null ? $classrooms->modelKeys() : [$classroom->getKey()];
        $status = $this->enumFilter($request, 'status', ProgressStatus::class);

        return view('teacher.missions.show', [
            'mission' => $mission,
            'classrooms' => $classrooms,
            'classroom' => $classroom,
            'status' => $status,
            'completion' => $report->completionFor([$mission], $classroomIds)[$mission->getKey()],
            'learners' => $report->learners($mission, $classroomIds, $status),
        ]);
    }
}
