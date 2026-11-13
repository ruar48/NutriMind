<?php

use App\Enums\GradeLevel;
use App\Enums\ProgressStatus;
use App\Models\Classroom;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Subject;
use App\Models\User;
use App\Services\Monitoring\MissionCompletion;
use App\Services\Monitoring\MissionProgressReport;
use Illuminate\Database\Eloquent\Collection;

it('counts a learner enrolled in two matching classrooms once', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $first = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
    $second = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
    $student = User::factory()->student(GradeLevel::Five)->enrolledIn($first, $second)->create();
    User::factory()->student(GradeLevel::Five)->enrolledIn($second)->create();
    $mission = Mission::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);
    MissionProgress::factory()->completed()->for($mission)->for($student, 'student')->create();

    $completion = app(MissionProgressReport::class)->completionFor([$mission], [$first->id, $second->id])[$mission->id];

    expect([$completion->eligible, $completion->completed, $completion->inProgress, $completion->notStarted()])->toBe([2, 1, 0, 1])
        ->and($completion->rate())->toBe(50.0);
});

it('treats a not-started progress row as not started', function () {
    $subject = Subject::factory()->create();
    $classroom = Classroom::factory()->for($subject)->create(['grade_level' => GradeLevel::Six]);
    $student = User::factory()->student(GradeLevel::Six)->enrolledIn($classroom)->create();
    $mission = Mission::factory()->for($subject)->create(['grade_level' => null]);
    MissionProgress::factory()->for($mission)->for($student, 'student')->create(['status' => ProgressStatus::NotStarted, 'progress_percent' => 0]);
    $report = app(MissionProgressReport::class);

    $completion = $report->completionFor([$mission], [$classroom->id])[$mission->id];
    $notStarted = $report->learners($mission, [$classroom->id], ProgressStatus::NotStarted);

    expect([$completion->eligible, $completion->completed, $completion->inProgress])->toBe([1, 0, 0])
        ->and($notStarted->pluck('id')->all())->toBe([$student->id]);
});

it('ignores learners of classrooms whose subject or grade does not match the mission', function () {
    $subject = Subject::factory()->create();
    $grade6 = Classroom::factory()->for($subject)->create(['grade_level' => GradeLevel::Six]);
    $otherSubject = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $mission = Mission::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);
    foreach ([$grade6, $otherSubject] as $classroom) {
        $student = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->create();
        MissionProgress::factory()->completed()->for($mission)->for($student, 'student')->create();
    }

    $completion = app(MissionProgressReport::class)->completionFor([$mission], [$grade6->id, $otherSubject->id])[$mission->id];

    expect([$completion->eligible, $completion->completed])->toBe([0, 0])
        ->and($completion->rate())->toBeNull();
});

it('decides which classrooms can play a mission', function (?GradeLevel $missionGrade, GradeLevel $classroomGrade, bool $sameSubject, bool $expected) {
    $subject = Subject::factory()->create();
    $mission = Mission::factory()->for($subject)->create(['grade_level' => $missionGrade]);
    $classroom = Classroom::factory()->for($sameSubject ? $subject : Subject::factory()->create())->create(['grade_level' => $classroomGrade]);

    expect(app(MissionProgressReport::class)->isPlayableIn($mission, $classroom))->toBe($expected);
})->with([
    'same grade' => [GradeLevel::Five, GradeLevel::Five, true, true],
    'other grade' => [GradeLevel::Five, GradeLevel::Six, true, false],
    'open to both grades' => [null, GradeLevel::Six, true, true],
    'other subject' => [null, GradeLevel::Five, false, false],
]);

it('reports zero eligible pairs for a classroom without playable missions', function () {
    $subject = Subject::factory()->create();
    $classroom = Classroom::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);
    User::factory()->student(GradeLevel::Five)->enrolledIn($classroom)->create();
    Mission::factory()->for($subject)->create(['grade_level' => GradeLevel::Six]);
    Mission::factory()->for($subject)->inactive()->create(['grade_level' => GradeLevel::Five]);

    $rows = app(MissionProgressReport::class)->classroomCompletion(new Collection([Classroom::query()->withCount('students')->find($classroom->id)]));

    expect($rows[0]['missions'])->toBe(0)
        ->and($rows[0]['completion']->eligible)->toBe(0)
        ->and($rows[0]['completion']->rate())->toBeNull();
});

it('caps the completion rate and the not-started count', function () {
    $completion = new MissionCompletion(eligible: 2, completed: 3, inProgress: 1);

    expect($completion->rate())->toBe(100.0)
        ->and($completion->notStarted())->toBe(0);
});
