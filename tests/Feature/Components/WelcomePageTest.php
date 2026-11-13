<?php

use App\Enums\UserRole;

it('falls back to the configured school name when none is passed', function () {
    config(['nutrimind.settings.school_name' => 'Mabini Central School']);

    $this->view('welcome')
        ->assertSeeText('Mabini Central School')
        ->assertSeeText('Nutrition learning that feels like');
});

it('shows the school name it is given', function () {
    $this->view('welcome', ['schoolName' => 'Rizal Elementary School'])
        ->assertSeeText('Rizal Elementary School');
});

it('links a card to the sign-in page of every web portal', function () {
    $view = $this->view('welcome');

    foreach (UserRole::webPortals() as $role) {
        $view->assertSeeText($role->portalLabel())
            ->assertSee('href="'.url('/login/'.$role->value).'"', false);
    }

    $view->assertDontSeeText(UserRole::Student->portalLabel());
});

it('highlights the game features', function () {
    $this->view('welcome')
        ->assertSeeTextInOrder(['Game worlds', 'Missions', 'Quizzes', 'Analytics', 'Parent forum']);
});

it('serves the landing page at the site root', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('Choose your portal');
});
