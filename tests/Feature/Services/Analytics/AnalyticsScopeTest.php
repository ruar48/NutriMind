<?php

use App\Enums\GradeLevel;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use App\Services\Analytics\AnalyticsPeriod;
use App\Services\Analytics\AnalyticsScope;

it('covers the teacher’s non-archived accessible classrooms and assigned subjects', function () {
    $health = Subject::factory()->create();
    $science = Subject::factory()->create();
    $teacher = User::factory()->teaching($health, $science)->create();
    $healthClass = Classroom::factory()->for($teacher, 'teacher')->for($health)->create();
    $scienceClass = Classroom::factory()->for($teacher, 'teacher')->for($science)->create();
    Classroom::factory()->for($teacher, 'teacher')->for($health)->archived()->create();
    Classroom::factory()->for($teacher, 'teacher')->revokedSubject()->create();
    Classroom::factory()->for($health)->create();
    $teacher->refresh();

    $scope = AnalyticsScope::forTeacher($teacher, AnalyticsPeriod::lastDays(30));

    expect($scope->classroomIds)->toBe([$healthClass->id, $scienceClass->id])
        ->and($scope->subjectIds)->toEqualCanonicalizing([$health->id, $science->id])
        ->and($scope->isSchoolWide())->toBeFalse();
});

it('keeps a chosen classroom of the teacher, archived ones included', function () {
    $classroom = Classroom::factory()->archived()->create();

    $scope = AnalyticsScope::forTeacher($classroom->teacher, AnalyticsPeriod::lastDays(30), $classroom->id);

    expect($scope->classroomIds)->toBe([$classroom->id]);
});

it('covers nothing for another teacher’s classroom or a subject that is not assigned', function () {
    $classroom = Classroom::factory()->create();
    $teacher = User::factory()->teaching($classroom->subject)->create();
    Classroom::factory()->for($teacher, 'teacher')->for($classroom->subject)->create();

    $forgedClassroom = AnalyticsScope::forTeacher($teacher, AnalyticsPeriod::lastDays(30), $classroom->id);
    $forgedSubject = AnalyticsScope::forTeacher($teacher, AnalyticsPeriod::lastDays(30), subjectId: Subject::factory()->create()->id);

    expect($forgedClassroom->classroomIds)->toBe([])
        ->and($forgedSubject->classroomIds)->toBe([])
        ->and($forgedSubject->subjectIds)->toBe([]);
});

it('limits the teacher’s classrooms to the chosen subject', function () {
    $health = Subject::factory()->create();
    $science = Subject::factory()->create();
    $teacher = User::factory()->teaching($health, $science)->create();
    Classroom::factory()->for($teacher, 'teacher')->for($health)->create();
    $scienceClass = Classroom::factory()->for($teacher, 'teacher')->for($science)->create();
    $teacher->refresh();

    $scope = AnalyticsScope::forTeacher($teacher, AnalyticsPeriod::lastDays(30), subjectId: $science->id);

    expect($scope->classroomIds)->toBe([$scienceClass->id])->and($scope->subjectIds)->toBe([$science->id]);
});

it('covers the whole school, optionally one subject and grade', function () {
    $scope = AnalyticsScope::school(AnalyticsPeriod::lastDays(7), 4, GradeLevel::Six);

    expect($scope)
        ->classroomIds->toBeNull()
        ->subjectIds->toBe([4])
        ->grade->toBe(GradeLevel::Six)
        ->and($scope->isSchoolWide())->toBeTrue()
        ->and(AnalyticsScope::school(AnalyticsPeriod::lastDays(7))->subjectIds)->toBeNull();
});

it('keeps the population when the period changes', function () {
    $scope = AnalyticsScope::school(AnalyticsPeriod::lastDays(30), 2, GradeLevel::Five);
    $previous = $scope->previous();

    expect($previous->populationKey())->toBe($scope->populationKey())
        ->and($previous->cacheKey())->not->toBe($scope->cacheKey())
        ->and($previous->period->to->toDateString())->toBe($scope->period->from->subDay()->toDateString());
});
