<?php

use App\Enums\UserRole;

it('describes each role with its label, color, icon, portal and dashboard route', function (UserRole $role, array $descriptors) {
    expect([
        'label' => $role->label(),
        'color' => $role->color(),
        'icon' => $role->icon(),
        'portal' => $role->portalLabel(),
        'dashboard' => $role->dashboardRoute(),
    ])->toBe($descriptors);
})->with([
    'admin' => [UserRole::Admin, ['label' => 'Administrator', 'color' => 'grape', 'icon' => 'shield-check', 'portal' => 'Administrator Portal', 'dashboard' => 'admin.dashboard']],
    'teacher' => [UserRole::Teacher, ['label' => 'Teacher', 'color' => 'leaf', 'icon' => 'graduation-cap', 'portal' => 'Teacher Portal', 'dashboard' => 'teacher.dashboard']],
    'parent' => [UserRole::Parent, ['label' => 'Parent / Guardian', 'color' => 'carrot', 'icon' => 'house-heart', 'portal' => 'Parent Portal', 'dashboard' => 'parent.dashboard']],
    'student' => [UserRole::Student, ['label' => 'Learner', 'color' => 'sky', 'icon' => 'gamepad-2', 'portal' => 'NutriMind Game', 'dashboard' => 'home']],
]);

it('lets every role except learners sign in to the web portals', function (UserRole $role, bool $canAccessWeb) {
    expect($role->canAccessWeb())->toBe($canAccessWeb);
})->with([
    'admin' => [UserRole::Admin, true],
    'teacher' => [UserRole::Teacher, true],
    'parent' => [UserRole::Parent, true],
    'student' => [UserRole::Student, false],
]);

it('lists the administrator, teacher and parent portals in display order', function () {
    expect(UserRole::webPortals())->toBe([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);
});
