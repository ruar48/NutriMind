<?php

use App\Enums\ReportType;

it('offers every report except user accounts to teachers', function (ReportType $report, bool $availableToTeachers) {
    expect($report->availableToTeachers())->toBe($availableToTeachers);
})->with([
    'learner performance' => [ReportType::StudentPerformance, true],
    'quiz results' => [ReportType::QuizResults, true],
    'mission progress' => [ReportType::MissionProgress, true],
    'classroom summary' => [ReportType::ClassroomSummary, true],
    'user accounts' => [ReportType::UserAccounts, false],
]);
