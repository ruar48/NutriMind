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

dataset('displayed enums', [
    'UserRole' => UserRole::class,
    'GradeLevel' => GradeLevel::class,
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
    'RewardType' => RewardType::class,
    'PerformanceBand' => PerformanceBand::class,
    'ReportType' => ReportType::class,
]);

it('stores each case under the value the contract defines', function (string $enum, array $values) {
    expect(array_map(fn (BackedEnum $case): int|string => $case->value, $enum::cases()))->toBe($values);
})->with([
    'UserRole' => [UserRole::class, ['admin', 'teacher', 'parent', 'student']],
    'GradeLevel' => [GradeLevel::class, [5, 6]],
    'Sex' => [Sex::class, ['male', 'female']],
    'GuardianRelationship' => [GuardianRelationship::class, ['mother', 'father', 'guardian', 'grandparent', 'sibling', 'relative', 'other']],
    'LearningMode' => [LearningMode::class, ['automatic', 'manual', 'learner_choice']],
    'ActivityType' => [ActivityType::class, ['lesson', 'video', 'mini_game', 'reading', 'practice']],
    'MissionType' => [MissionType::class, ['main', 'daily', 'weekly', 'challenge']],
    'Difficulty' => [Difficulty::class, ['easy', 'medium', 'hard']],
    'ProgressStatus' => [ProgressStatus::class, ['not_started', 'in_progress', 'completed']],
    'QuizStatus' => [QuizStatus::class, ['draft', 'published', 'closed']],
    'QuestionType' => [QuestionType::class, ['multiple_choice', 'true_false', 'identification']],
    'FeedbackType' => [FeedbackType::class, ['praise', 'encouragement', 'guidance', 'concern']],
    'LessonPlanStatus' => [LessonPlanStatus::class, ['planned', 'ongoing', 'completed']],
    'ForumContentStatus' => [ForumContentStatus::class, ['published', 'pending', 'hidden']],
    'ForumReportReason' => [ForumReportReason::class, ['spam', 'inappropriate', 'harassment', 'off_topic', 'misinformation', 'other']],
    'RewardType' => [RewardType::class, ['badge', 'trophy', 'avatar_item']],
    'PerformanceBand' => [PerformanceBand::class, ['outstanding', 'very_satisfactory', 'satisfactory', 'fairly_satisfactory', 'did_not_meet_expectations', 'no_data']],
    'ReportType' => [ReportType::class, ['student-performance', 'quiz-results', 'mission-progress', 'classroom-summary', 'user-accounts']],
    'Permission' => [Permission::class, [
        'manage_classrooms', 'manage_students', 'import_students', 'create_quizzes', 'manage_question_bank',
        'share_questions', 'manage_lesson_plans', 'give_feedback', 'view_analytics', 'export_reports',
        'view_child_progress', 'view_quiz_details', 'view_teacher_feedback', 'access_forum', 'post_in_forum',
        'play_game', 'use_offline_mode', 'choose_learning_mode', 'view_leaderboard',
    ]],
]);

it('labels the cases with the wording the contract defines', function (string $enum, array $labels) {
    expect(array_map(fn (BackedEnum $case): string => $case->label(), $enum::cases()))->toBe($labels);
})->with([
    'GradeLevel' => [GradeLevel::class, ['Grade 5', 'Grade 6']],
    'LearningMode' => [LearningMode::class, ['Automatic path', 'Manual selection', "Learner's choice"]],
    'MissionType' => [MissionType::class, ['Story mission', 'Daily', 'Weekly', 'Challenge']],
]);

it('colors the cases with the palette keys the contract assigns', function (string $enum, array $colors) {
    expect(array_map(fn (BackedEnum $case): string => $case->color(), $enum::cases()))->toBe($colors);
})->with([
    'GradeLevel' => [GradeLevel::class, ['sky', 'grape']],
    'LearningMode' => [LearningMode::class, ['leaf', 'sky', 'grape']],
    'Difficulty' => [Difficulty::class, ['leaf', 'sun', 'berry']],
    'ProgressStatus' => [ProgressStatus::class, ['slate', 'sky', 'leaf']],
    'QuizStatus' => [QuizStatus::class, ['sun', 'leaf', 'slate']],
    'FeedbackType' => [FeedbackType::class, ['leaf', 'sky', 'grape', 'carrot']],
    'LessonPlanStatus' => [LessonPlanStatus::class, ['slate', 'sky', 'leaf']],
    'ForumContentStatus' => [ForumContentStatus::class, ['leaf', 'sun', 'slate']],
    'RewardType' => [RewardType::class, ['sun', 'carrot', 'grape']],
]);

it('colors every displayed case with a palette key', function (string $enum) {
    $colors = array_map(fn (BackedEnum $case): string => $case->color(), $enum::cases());

    expect($colors)->each->toBeIn(['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate']);
})->with('displayed enums');

it('gives every displayed case an icon from the design system icon set', function (string $enum) {
    $icons = array_map(fn (BackedEnum $case): string => $case->icon(), $enum::cases());

    expect($icons)->each->toBeIn(require __DIR__.'/../../Fixtures/icon-names.php');
})->with('displayed enums');

it('explains every case of the enums that carry a description', function (string $enum) {
    $descriptions = array_map(fn (BackedEnum $case): string => $case->description(), $enum::cases());

    expect($descriptions)->each->not->toBeEmpty();
})->with([
    'LearningMode' => LearningMode::class,
    'QuestionType' => QuestionType::class,
    'ReportType' => ReportType::class,
    'Permission' => Permission::class,
]);
