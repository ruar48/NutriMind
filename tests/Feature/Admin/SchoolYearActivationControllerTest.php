<?php

use App\Models\AcademicTerm;
use App\Models\SchoolYear;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-11-13 09:00:00');
    $this->admin = User::factory()->admin()->create();
});

it('makes the school year the only active one and opens the quarter that covers today', function () {
    $previous = SchoolYear::factory()->active()->create(['name' => '2025–2026', 'starts_on' => '2025-06-16', 'ends_on' => '2026-03-31']);
    $stray = SchoolYear::factory()->active()->create(['name' => '2027–2028', 'starts_on' => '2027-06-14', 'ends_on' => '2028-04-14']);
    $previousTerm = AcademicTerm::factory()->for($previous)->active()->create();
    $schoolYear = SchoolYear::factory()->create(['name' => '2026–2027', 'starts_on' => '2026-06-15', 'ends_on' => '2027-04-16']);
    $first = AcademicTerm::factory()->for($schoolYear)->create(['name' => '1st Quarter', 'sequence' => 1, 'starts_on' => '2026-06-15', 'ends_on' => '2026-08-28']);
    $second = AcademicTerm::factory()->for($schoolYear)->create(['name' => '2nd Quarter', 'sequence' => 2, 'starts_on' => '2026-08-31', 'ends_on' => '2026-11-27']);

    $this->actingAs($this->admin)
        ->from(route('admin.school-years.index'))
        ->put(route('admin.school-years.activation.update', $schoolYear))
        ->assertRedirect(route('admin.school-years.index'))
        ->assertSessionHas('status', '2026–2027 is now the active school year, and 2nd Quarter is the current quarter.');

    expect($schoolYear->refresh()->is_active)->toBeTrue()
        ->and($previous->refresh()->is_active)->toBeFalse()
        ->and($stray->refresh()->is_active)->toBeFalse()
        ->and($previousTerm->refresh()->is_active)->toBeFalse()
        ->and($first->refresh()->is_active)->toBeFalse()
        ->and($second->refresh()->is_active)->toBeTrue()
        ->and(SchoolYear::current()->is($schoolYear))->toBeTrue()
        ->and(AcademicTerm::current()->is($second))->toBeTrue();
});

it('keeps the current quarter the school year already has', function () {
    $schoolYear = SchoolYear::factory()->create(['starts_on' => '2026-06-15', 'ends_on' => '2027-04-16']);
    $first = AcademicTerm::factory()->for($schoolYear)->active()->create(['sequence' => 1, 'starts_on' => '2026-06-15', 'ends_on' => '2026-08-28']);
    $second = AcademicTerm::factory()->for($schoolYear)->create(['sequence' => 2, 'starts_on' => '2026-08-31', 'ends_on' => '2026-11-27']);

    $this->actingAs($this->admin)
        ->put(route('admin.school-years.activation.update', $schoolYear))
        ->assertSessionHas('status', "{$schoolYear->name} is now the active school year, and {$first->name} is the current quarter.");

    expect($first->refresh()->is_active)->toBeTrue()
        ->and($second->refresh()->is_active)->toBeFalse();
});

it('explains when no quarter covers today', function () {
    $schoolYear = SchoolYear::factory()->create(['name' => '2027–2028', 'starts_on' => '2027-06-14', 'ends_on' => '2028-04-14']);
    $term = AcademicTerm::factory()->for($schoolYear)->create();

    $this->actingAs($this->admin)
        ->put(route('admin.school-years.activation.update', $schoolYear))
        ->assertRedirect(route('admin.school-years.show', $schoolYear))
        ->assertSessionHas('status', '2027–2028 is now the active school year. No quarter covers today, so choose the current quarter.');

    expect($schoolYear->refresh()->is_active)->toBeTrue()
        ->and($term->refresh()->is_active)->toBeFalse();
});

it('answers an unknown school year with 404', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.school-years.activation.update', 999999))
        ->assertNotFound();
});

it('sends guests to the sign-in page', function () {
    $schoolYear = SchoolYear::factory()->create();

    $this->put(route('admin.school-years.activation.update', $schoolYear))->assertRedirectToRoute('login');

    expect($schoolYear->refresh()->is_active)->toBeFalse();
});

it('forbids every other role', function (string $role) {
    $schoolYear = SchoolYear::factory()->create();

    $this->actingAs(User::factory()->{$role}()->create())
        ->put(route('admin.school-years.activation.update', $schoolYear))
        ->assertForbidden();

    expect($schoolYear->refresh()->is_active)->toBeFalse();
})->with(['teacher', 'parent', 'student']);
