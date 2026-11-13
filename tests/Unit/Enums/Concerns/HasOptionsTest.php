<?php

use App\Enums\ActivityType;
use App\Enums\Difficulty;
use App\Enums\FeedbackType;
use App\Enums\ForumContentStatus;
use App\Enums\ForumReportReason;
use App\Enums\GradeLevel;
use App\Enums\GuardianRelationship;
use App\Enums\LearningMode;
use App\Enums\LessonPlanStatus;
use App\Enums\MissionType;
use App\Enums\PerformanceBand;
use App\Enums\Permission;
use App\Enums\ProgressStatus;
use App\Enums\QuestionType;
use App\Enums\QuizStatus;
use App\Enums\ReportType;
use App\Enums\RewardType;
use App\Enums\Sex;
use App\Enums\UserRole;

it('builds select options for an integer-backed enum', function () {
    expect(GradeLevel::options())->toBe([5 => 'Grade 5', 6 => 'Grade 6']);
});

it('builds select options for a string-backed enum', function () {
    expect(QuizStatus::options())->toBe(['draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed']);
});

it('keys the options by case value in declaration order with each case label', function (string $enum) {
    $options = $enum::options();

    expect(array_keys($options))->toBe(array_map(fn (BackedEnum $case): int|string => $case->value, $enum::cases()))
        ->and(array_values($options))->toBe(array_map(fn (BackedEnum $case): string => $case->label(), $enum::cases()));
})->with([
    'UserRole' => UserRole::class,
    'GradeLevel' => GradeLevel::class,
    'Sex' => Sex::class,
    'GuardianRelationship' => GuardianRelationship::class,
    'LearningMode' => LearningMode::class,
    'ActivityType' => ActivityType::class,
    'MissionType' => MissionType::class,
    'Difficulty' => Difficulty::class,
    'ProgressStatus' => ProgressStatus::class,
    'QuizStatus' => QuizStatus::class,
    'QuestionType' => QuestionType::class,
    'FeedbackType' => FeedbackType::class,
    'LessonPlanStatus' => LessonPlanStatus::class,
    'ForumContentStatus' => ForumContentStatus::class,
    'ForumReportReason' => ForumReportReason::class,
    'RewardType' => RewardType::class,
    'PerformanceBand' => PerformanceBand::class,
    'ReportType' => ReportType::class,
    'Permission' => Permission::class,
]);
