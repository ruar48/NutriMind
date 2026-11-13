<?php

use App\Enums\UserRole;
use App\Models\User;

it('sends each role to the dashboard of its portal', function (UserRole $role, string $dashboardRoute) {
    $this->actingAs(User::factory()->{$role->value}()->create());

    $this->get(route('dashboard'))->assertRedirectToRoute($dashboardRoute);
})->with([
    'administrator' => [UserRole::Admin, 'admin.dashboard'],
    'teacher' => [UserRole::Teacher, 'teacher.dashboard'],
    'parent' => [UserRole::Parent, 'parent.dashboard'],
]);

it('sends guests to the sign-in page', function () {
    $this->get(route('dashboard'))->assertRedirectToRoute('login');
});
