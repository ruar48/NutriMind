<?php

use App\Models\AcademicTerm;
use App\Models\SchoolYear;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-11-13 09:00:00');
    $this->admin = User::factory()->admin()->create();
    $this->schoolYear = SchoolYear::factory()->create(['name' => '2026–2027', 'starts_on' => '2026-06-15', 'ends_on' => '2027-04-16']);
});

describe('create', function () {
    it('prefills the next quarter after the last one', function () {
        AcademicTerm::factory()->for($this->schoolYear)->create(['name' => '1st Quarter', 'sequence' => 1, 'starts_on' => '2026-06-15', 'ends_on' => '2026-08-28']);
        AcademicTerm::factory()->for($this->schoolYear)->create(['name' => '2nd Quarter', 'sequence' => 2, 'starts_on' => '2026-08-31', 'ends_on' => '2026-11-27']);

        $response = $this->actingAs($this->admin)->get(route('admin.school-years.terms.create', $this->schoolYear));

        $response->assertOk()
            ->assertViewIs('admin.terms.form')
            ->assertSee('action="'.route('admin.school-years.terms.store', $this->schoolYear).'"', false)
            ->assertSee('value="3rd Quarter"', false)
            ->assertSee('value="2026-11-28"', false)
            ->assertSee('value="2027-04-16"', false)
            ->assertSeeInOrder(['Other quarters', '1st Quarter', '2nd Quarter']);
        expect($response->viewData('term')->sequence)->toBe(3);
    });

    it('starts the first quarter on the first day of the school year', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.school-years.terms.create', $this->schoolYear))
            ->assertOk()
            ->assertSee('value="1st Quarter"', false)
            ->assertSee('value="2026-06-15"', false)
            ->assertSeeText('This is the first quarter of 2026–2027.');
    });

    it('sends the administrator back when the school year already has eight quarters', function () {
        AcademicTerm::factory()->for($this->schoolYear)->count(8)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.school-years.terms.create', $this->schoolYear))
            ->assertRedirectToRoute('admin.school-years.show', $this->schoolYear)
            ->assertSessionHas('error', '2026–2027 already has 8 quarters, the most a school year can have.');
    });

    it('forbids every other role', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('admin.school-years.terms.create', $this->schoolYear))
            ->assertForbidden();
    })->with(['teacher', 'parent', 'student']);

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.school-years.terms.create', $this->schoolYear))->assertRedirectToRoute('login');
    });

    it('answers an unknown school year with 404', function () {
        $this->actingAs($this->admin)->get(route('admin.school-years.terms.create', 999999))->assertNotFound();
    });
});

describe('store', function () {
    it('adds a quarter to the school year', function () {
        $response = $this->actingAs($this->admin)->post(route('admin.school-years.terms.store', $this->schoolYear), [
            'name' => '1st Quarter',
            'sequence' => '1',
            'starts_on' => '2026-06-15',
            'ends_on' => '2026-08-28',
            'is_active' => '1',
        ]);

        $response->assertRedirectToRoute('admin.school-years.show', $this->schoolYear)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', '1st Quarter was added to 2026–2027.');
        $term = $this->schoolYear->terms()->sole();
        expect($term)->name->toBe('1st Quarter')->is_active->toBeFalse()
            ->and($term->sequence)->toBe(1)
            ->and($term->starts_on->toDateString())->toBe('2026-06-15')
            ->and($term->ends_on->toDateString())->toBe('2026-08-28');
    });

    it('requires every field', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.school-years.terms.store', $this->schoolYear), [])
            ->assertSessionHasErrors([
                'name' => 'The quarter name field is required.',
                'sequence' => 'The quarter number field is required.',
                'starts_on' => 'The start date field is required.',
                'ends_on' => 'The end date field is required.',
            ]);
    });

    it('rejects invalid quarter details', function (array $payload, string $field, string $message) {
        AcademicTerm::factory()->for($this->schoolYear)->create(['name' => '1st Quarter', 'sequence' => 1, 'starts_on' => '2026-06-15', 'ends_on' => '2026-08-28']);

        $this->actingAs($this->admin)
            ->post(route('admin.school-years.terms.store', $this->schoolYear), [
                'name' => '2nd Quarter',
                'sequence' => '2',
                'starts_on' => '2026-08-31',
                'ends_on' => '2026-11-27',
                ...$payload,
            ])
            ->assertSessionHasErrors([$field => $message]);

        expect($this->schoolYear->terms()->count())->toBe(1);
    })->with([
        'long name' => [['name' => str_repeat('Q', 51)], 'name', 'The quarter name field must not be greater than 50 characters.'],
        'name taken in the year' => [['name' => '1st Quarter'], 'name', 'Another quarter of 2026–2027 already has this name.'],
        'number taken in the year' => [['sequence' => '1'], 'sequence', 'Another quarter of 2026–2027 is already number 1.'],
        'number below 1' => [['sequence' => '0'], 'sequence', 'The quarter number field must be between 1 and 8.'],
        'number above 8' => [['sequence' => '9'], 'sequence', 'The quarter number field must be between 1 and 8.'],
        'number not whole' => [['sequence' => '2.5'], 'sequence', 'The quarter number field must be an integer.'],
        'invalid start' => [['starts_on' => 'soon'], 'starts_on', 'The start date field must be a valid date.'],
        'end before start' => [['ends_on' => '2026-08-30'], 'ends_on', 'The end date must be after the start date.'],
        'starts before the school year' => [['starts_on' => '2026-06-01'], 'starts_on', 'The start date must be on or after Jun 15, 2026, when 2026–2027 begins.'],
        'ends after the school year' => [['ends_on' => '2027-04-17'], 'ends_on', 'The end date must be on or before Apr 16, 2027, when 2026–2027 ends.'],
        'overlaps a sibling' => [['starts_on' => '2026-08-28'], 'starts_on', 'These dates overlap 1st Quarter (Jun 15, 2026 – Aug 28, 2026).'],
        'surrounds a sibling' => [['starts_on' => '2026-06-15', 'ends_on' => '2026-12-31'], 'starts_on', 'These dates overlap 1st Quarter (Jun 15, 2026 – Aug 28, 2026).'],
    ]);

    it('allows a name and number another school year already uses', function () {
        $other = SchoolYear::factory()->create(['name' => '2025–2026', 'starts_on' => '2025-06-16', 'ends_on' => '2026-03-31']);
        AcademicTerm::factory()->for($other)->create(['name' => '1st Quarter', 'sequence' => 1]);

        $this->actingAs($this->admin)
            ->post(route('admin.school-years.terms.store', $this->schoolYear), [
                'name' => '1st Quarter',
                'sequence' => '1',
                'starts_on' => '2026-06-15',
                'ends_on' => '2026-08-28',
            ])
            ->assertSessionHasNoErrors();

        expect($this->schoolYear->terms()->count())->toBe(1);
    });

    it('allows a quarter that starts the day after the previous one ends', function () {
        AcademicTerm::factory()->for($this->schoolYear)->create(['sequence' => 1, 'starts_on' => '2026-06-15', 'ends_on' => '2026-08-28']);

        $this->actingAs($this->admin)
            ->post(route('admin.school-years.terms.store', $this->schoolYear), [
                'name' => '2nd Quarter',
                'sequence' => '2',
                'starts_on' => '2026-08-29',
                'ends_on' => '2026-11-27',
            ])
            ->assertSessionHasNoErrors();
    });

    it('forbids every other role before validating', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->post(route('admin.school-years.terms.store', $this->schoolYear), [])
            ->assertForbidden()
            ->assertSessionHasNoErrors();
    })->with(['teacher', 'parent']);
});

describe('edit', function () {
    it('shows the quarter with its school year and siblings', function () {
        $term = AcademicTerm::factory()->for($this->schoolYear)->create(['name' => '2nd Quarter', 'sequence' => 2, 'starts_on' => '2026-08-31', 'ends_on' => '2026-11-27']);
        AcademicTerm::factory()->for($this->schoolYear)->create(['name' => '1st Quarter', 'sequence' => 1, 'starts_on' => '2026-06-15', 'ends_on' => '2026-08-28']);

        $response = $this->actingAs($this->admin)->get(route('admin.terms.edit', $term));

        $response->assertOk()
            ->assertSee('action="'.route('admin.terms.update', $term).'"', false)
            ->assertSee('value="2nd Quarter"', false)
            ->assertSee('value="2026-08-31"', false)
            ->assertSee(route('admin.school-years.show', $this->schoolYear), false);
        expect($response->viewData('otherTerms')->pluck('name')->all())->toBe(['1st Quarter']);
    });

    it('answers an unknown quarter with 404', function () {
        $this->actingAs($this->admin)->get(route('admin.terms.edit', 999999))->assertNotFound();
    });
});

describe('update', function () {
    it('updates the quarter, keeping its own name, number and dates valid', function () {
        $term = AcademicTerm::factory()->for($this->schoolYear)->create(['name' => '2nd Quarter', 'sequence' => 2, 'starts_on' => '2026-08-31', 'ends_on' => '2026-11-27']);

        $this->actingAs($this->admin)
            ->put(route('admin.terms.update', $term), ['name' => '2nd Quarter', 'sequence' => '2', 'starts_on' => '2026-09-01', 'ends_on' => '2026-11-27'])
            ->assertRedirectToRoute('admin.school-years.show', $this->schoolYear)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', '2nd Quarter was updated.');

        expect($term->refresh()->starts_on->toDateString())->toBe('2026-09-01');
    });

    it('refuses a name, number or dates of a sibling quarter', function (array $payload, string $field, string $message) {
        AcademicTerm::factory()->for($this->schoolYear)->create(['name' => '1st Quarter', 'sequence' => 1, 'starts_on' => '2026-06-15', 'ends_on' => '2026-08-28']);
        $term = AcademicTerm::factory()->for($this->schoolYear)->create(['name' => '2nd Quarter', 'sequence' => 2, 'starts_on' => '2026-08-31', 'ends_on' => '2026-11-27']);

        $this->actingAs($this->admin)
            ->put(route('admin.terms.update', $term), ['name' => '2nd Quarter', 'sequence' => '2', 'starts_on' => '2026-08-31', 'ends_on' => '2026-11-27', ...$payload])
            ->assertSessionHasErrors([$field => $message]);

        expect($term->refresh()->name)->toBe('2nd Quarter');
    })->with([
        'sibling name' => [['name' => '1st Quarter'], 'name', 'Another quarter of 2026–2027 already has this name.'],
        'sibling number' => [['sequence' => '1'], 'sequence', 'Another quarter of 2026–2027 is already number 1.'],
        'sibling dates' => [['starts_on' => '2026-08-01'], 'starts_on', 'These dates overlap 1st Quarter (Jun 15, 2026 – Aug 28, 2026).'],
        'outside the school year' => [['ends_on' => '2027-05-01'], 'ends_on', 'The end date must be on or before Apr 16, 2027, when 2026–2027 ends.'],
    ]);

    it('forbids every other role', function (string $role) {
        $term = AcademicTerm::factory()->for($this->schoolYear)->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->put(route('admin.terms.update', $term), ['name' => 'Renamed'])
            ->assertForbidden();

        expect($term->refresh()->name)->not->toBe('Renamed');
    })->with(['teacher', 'parent']);
});

describe('destroy', function () {
    it('deletes a quarter that is not current', function () {
        $term = AcademicTerm::factory()->for($this->schoolYear)->create(['name' => '4th Quarter']);

        $this->actingAs($this->admin)
            ->delete(route('admin.terms.destroy', $term))
            ->assertRedirectToRoute('admin.school-years.show', $this->schoolYear)
            ->assertSessionHas('status', '4th Quarter was deleted.');

        $this->assertModelMissing($term);
    });

    it('refuses to delete the current quarter', function () {
        $term = AcademicTerm::factory()->for($this->schoolYear)->active()->create(['name' => '2nd Quarter']);

        $this->actingAs($this->admin)
            ->delete(route('admin.terms.destroy', $term))
            ->assertRedirectToRoute('admin.school-years.show', $this->schoolYear)
            ->assertSessionHas('error', '2nd Quarter is the current quarter. Set another quarter as current first.');

        $this->assertModelExists($term);
    });

    it('sends guests to the sign-in page', function () {
        $term = AcademicTerm::factory()->for($this->schoolYear)->create();

        $this->delete(route('admin.terms.destroy', $term))->assertRedirectToRoute('login');

        $this->assertModelExists($term);
    });

    it('forbids every other role', function (string $role) {
        $term = AcademicTerm::factory()->for($this->schoolYear)->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->delete(route('admin.terms.destroy', $term))
            ->assertForbidden();

        $this->assertModelExists($term);
    })->with(['teacher', 'parent']);
});
