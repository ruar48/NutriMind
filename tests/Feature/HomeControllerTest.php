<?php

use App\Models\User;
use App\Services\Settings;

it('shows the landing page with the school name from the settings', function () {
    app(Settings::class)->set('school_name', 'Rizal Elementary School');

    $this->get(route('home'))
        ->assertOk()
        ->assertViewIs('welcome')
        ->assertViewHas('schoolName', 'Rizal Elementary School')
        ->assertSeeText('Rizal Elementary School');
});

it('shows the landing page to signed-in users too', function () {
    $this->actingAs(User::factory()->teacher()->create());

    $this->get(route('home'))->assertOk()->assertViewIs('welcome');
});
