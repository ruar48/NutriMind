<?php

use App\Models\AcademicTerm;
use App\Models\SchoolYear;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('makes the quarter the only current quarter', function () {
    $schoolYear = SchoolYear::factory()->active()->create(['name' => '2026–2027']);
    $first = AcademicTerm::factory()->for($schoolYear)->active()->create(['name' => '1st Quarter']);
    $second = AcademicTerm::factory()->for($schoolYear)->create(['name' => '2nd Quarter']);
    $strayTerm = AcademicTerm::factory()->for(SchoolYear::factory())->active()->create();

    $this->actingAs($this->admin)
        ->from(route('admin.school-years.show', $schoolYear))
        ->put(route('admin.terms.activation.update', $second))
        ->assertRedirect(route('admin.school-years.show', $schoolYear))
        ->assertSessionHas('status', '2nd Quarter is now the current quarter of 2026–2027.');

    expect($second->refresh()->is_active)->toBeTrue()
        ->and($first->refresh()->is_active)->toBeFalse()
        ->and($strayTerm->refresh()->is_active)->toBeFalse()
        ->and(AcademicTerm::current()->is($second))->toBeTrue();
});

it('requires the quarter\'s school year to be active', function () {
    $schoolYear = SchoolYear::factory()->create(['name' => '2027–2028']);
    $term = AcademicTerm::factory()->for($schoolYear)->create();
    $current = AcademicTerm::factory()->for(SchoolYear::factory()->active())->active()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.terms.activation.update', $term))
        ->assertRedirect(route('admin.school-years.show', $schoolYear))
        ->assertSessionHas('error', 'Activate 2027–2028 first.');

    expect($term->refresh()->is_active)->toBeFalse()
        ->and($current->refresh()->is_active)->toBeTrue();
});

it('keeps an already current quarter current', function () {
    $schoolYear = SchoolYear::factory()->active()->create();
    $term = AcademicTerm::factory()->for($schoolYear)->active()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.terms.activation.update', $term))
        ->assertSessionHas('status');

    expect($term->refresh()->is_active)->toBeTrue();
});

it('answers an unknown quarter with 404', function () {
    $this->actingAs($this->admin)->put(route('admin.terms.activation.update', 999999))->assertNotFound();
});

it('sends guests to the sign-in page', function () {
    $term = AcademicTerm::factory()->for(SchoolYear::factory()->active())->create();

    $this->put(route('admin.terms.activation.update', $term))->assertRedirectToRoute('login');

    expect($term->refresh()->is_active)->toBeFalse();
});

it('forbids every other role', function (string $role) {
    $term = AcademicTerm::factory()->for(SchoolYear::factory()->active())->create();

    $this->actingAs(User::factory()->{$role}()->create())
        ->put(route('admin.terms.activation.update', $term))
        ->assertForbidden();

    expect($term->refresh()->is_active)->toBeFalse();
})->with(['teacher', 'parent', 'student']);
