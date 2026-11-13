<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Settings;

it('lists the sign-in page of every web portal', function () {
    $response = $this->get(route('login'));

    $response->assertOk()->assertViewIs('auth.portals');
    foreach (UserRole::webPortals() as $role) {
        $response->assertSee('href="'.route('login.portal', $role->value).'"', false);
    }
});

it('shows the school name from the settings', function () {
    app(Settings::class)->set('school_name', 'Mabini Elementary School');

    $this->get(route('login'))
        ->assertOk()
        ->assertSeeText('Mabini Elementary School');
});

it('sends signed-in users to their dashboard instead', function (UserRole $role, string $dashboardRoute) {
    $this->actingAs(User::factory()->{$role->value}()->create());

    $this->get(route('login'))->assertRedirectToRoute($dashboardRoute);
})->with([
    'administrator' => [UserRole::Admin, 'admin.dashboard'],
    'teacher' => [UserRole::Teacher, 'teacher.dashboard'],
    'parent' => [UserRole::Parent, 'parent.dashboard'],
]);
