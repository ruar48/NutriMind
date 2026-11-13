<?php

use App\Enums\Permission;
use App\Enums\UserRole;

it('places each permission in its matrix group with the roles it applies to', function (Permission $permission, string $group, array $roles) {
    expect([
        'group' => $permission->group(),
        'roles' => $permission->roles(),
        'default roles' => $permission->defaultRoles(),
    ])->toBe([
        'group' => $group,
        'roles' => $roles,
        'default roles' => $roles,
    ]);
})->with([
    'manage classrooms' => [Permission::ManageClassrooms, 'Classrooms & learners', [UserRole::Teacher]],
    'manage students' => [Permission::ManageStudents, 'Classrooms & learners', [UserRole::Teacher]],
    'import students' => [Permission::ImportStudents, 'Classrooms & learners', [UserRole::Teacher]],
    'create quizzes' => [Permission::CreateQuizzes, 'Assessments', [UserRole::Teacher]],
    'manage question bank' => [Permission::ManageQuestionBank, 'Assessments', [UserRole::Teacher]],
    'share questions' => [Permission::ShareQuestions, 'Assessments', [UserRole::Teacher]],
    'manage lesson plans' => [Permission::ManageLessonPlans, 'Teaching', [UserRole::Teacher]],
    'give feedback' => [Permission::GiveFeedback, 'Teaching', [UserRole::Teacher]],
    'view analytics' => [Permission::ViewAnalytics, 'Insights', [UserRole::Teacher]],
    'export reports' => [Permission::ExportReports, 'Insights', [UserRole::Teacher]],
    'view child progress' => [Permission::ViewChildProgress, 'Parent portal', [UserRole::Parent]],
    'view quiz details' => [Permission::ViewQuizDetails, 'Parent portal', [UserRole::Parent]],
    'view teacher feedback' => [Permission::ViewTeacherFeedback, 'Parent portal', [UserRole::Parent]],
    'access forum' => [Permission::AccessForum, 'Parent forum', [UserRole::Teacher, UserRole::Parent]],
    'post in forum' => [Permission::PostInForum, 'Parent forum', [UserRole::Teacher, UserRole::Parent]],
    'play game' => [Permission::PlayGame, 'Learning game', [UserRole::Student]],
    'use offline mode' => [Permission::UseOfflineMode, 'Learning game', [UserRole::Student]],
    'choose learning mode' => [Permission::ChooseLearningMode, 'Learning game', [UserRole::Student]],
    'view leaderboard' => [Permission::ViewLeaderboard, 'Learning game', [UserRole::Student]],
]);

it('grants by default only roles the permission can be granted to', function (Permission $permission) {
    $grantableRoles = $permission->roles();

    $defaultRolesOutsideGrantable = array_filter(
        $permission->defaultRoles(),
        fn (UserRole $role): bool => ! in_array($role, $grantableRoles, true),
    );

    expect($defaultRolesOutsideGrantable)->toBeEmpty();
})->with(Permission::cases());

it('never lists administrators, who always hold every permission', function (Permission $permission) {
    expect($permission->roles())->not->toContain(UserRole::Admin);
})->with(Permission::cases());

it('leaves only the learning game permissions to the game to enforce', function (Permission $permission, bool $enforcedByGame) {
    expect($permission->enforcedByGame())->toBe($enforcedByGame);
})->with([
    'manage classrooms' => [Permission::ManageClassrooms, false],
    'manage students' => [Permission::ManageStudents, false],
    'import students' => [Permission::ImportStudents, false],
    'create quizzes' => [Permission::CreateQuizzes, false],
    'manage question bank' => [Permission::ManageQuestionBank, false],
    'share questions' => [Permission::ShareQuestions, false],
    'manage lesson plans' => [Permission::ManageLessonPlans, false],
    'give feedback' => [Permission::GiveFeedback, false],
    'view analytics' => [Permission::ViewAnalytics, false],
    'export reports' => [Permission::ExportReports, false],
    'view child progress' => [Permission::ViewChildProgress, false],
    'view quiz details' => [Permission::ViewQuizDetails, false],
    'view teacher feedback' => [Permission::ViewTeacherFeedback, false],
    'access forum' => [Permission::AccessForum, false],
    'post in forum' => [Permission::PostInForum, false],
    'play game' => [Permission::PlayGame, true],
    'use offline mode' => [Permission::UseOfflineMode, true],
    'choose learning mode' => [Permission::ChooseLearningMode, true],
    'view leaderboard' => [Permission::ViewLeaderboard, true],
]);

it('gives every permission a distinct label', function () {
    $labels = array_map(fn (Permission $permission): string => $permission->label(), Permission::cases());

    expect($labels)->toHaveCount(count(array_unique($labels)));
});
