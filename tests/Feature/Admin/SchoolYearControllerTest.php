<?php

use App\Enums\GradeLevel;
use App\Models\AcademicTerm;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo('2026-11-13 09:00:00');
    $this->admin = User::factory()->admin()->create();
});

describe('index', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.school-years.index'))->assertRedirectToRoute('login');
    });

    it('forbids every other role', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('admin.school-years.index'))
            ->assertForbidden();
    })->with(['teacher', 'parent', 'student']);

    it('lists the school years newest first with their quarter and classroom counts', function () {
        $previous = SchoolYear::factory()->create(['name' => '2025–2026', 'starts_on' => '2025-06-16', 'ends_on' => '2026-03-31']);
        $current = SchoolYear::factory()->active()->create(['name' => '2026–2027', 'starts_on' => '2026-06-15', 'ends_on' => '2027-04-16']);
        $next = SchoolYear::factory()->create(['name' => '2027–2028', 'starts_on' => '2027-06-14', 'ends_on' => '2028-04-14']);
        AcademicTerm::factory()->for($current)->count(4)->create();
        AcademicTerm::factory()->for($previous)->count(2)->create();
        Classroom::factory()->for($current)->count(3)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.school-years.index'));

        $response->assertOk()
            ->assertViewIs('admin.school-years.index')
            ->assertSeeInOrder(['2027–2028', '2026–2027', '2025–2026'])
            ->assertSee('data-year-status="upcoming"', false)
            ->assertSee('data-year-status="active"', false)
            ->assertSee('data-year-status="ended"', false);

        $rows = $response->viewData('schoolYears')->getCollection()->keyBy('id');
        expect($rows[$current->id])->terms_count->toBe(4)->classrooms_count->toBe(3)
            ->and($rows[$previous->id])->terms_count->toBe(2)->classrooms_count->toBe(0)
            ->and($rows[$next->id])->terms_count->toBe(0);
    });

    it('spotlights the active school year with its current quarter and calendar', function () {
        $schoolYear = SchoolYear::factory()->active()->create(['name' => '2026–2027', 'starts_on' => '2026-06-15', 'ends_on' => '2027-04-16']);
        AcademicTerm::factory()->for($schoolYear)->create(['name' => '1st Quarter', 'sequence' => 1, 'starts_on' => '2026-06-15', 'ends_on' => '2026-08-28']);
        AcademicTerm::factory()->for($schoolYear)->active()->create(['name' => '2nd Quarter', 'sequence' => 2, 'starts_on' => '2026-08-31', 'ends_on' => '2026-11-27']);

        $this->actingAs($this->admin)
            ->get(route('admin.school-years.index'))
            ->assertOk()
            ->assertSee('data-active-school-year="'.$schoolYear->id.'"', false)
            ->assertSeeText('2nd Quarter is current')
            ->assertSee('data-term-block="current"', false)
            ->assertSee('data-term-block="ended"', false)
            ->assertSee('data-term-today', false)
            ->assertSeeText('152 of 306 days')
            ->assertDontSee('data-no-active-school-year', false);
    });

    it('warns when no school year is active', function () {
        SchoolYear::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.school-years.index'))
            ->assertOk()
            ->assertSee('data-no-active-school-year', false)
            ->assertSeeText('No active school year');
    });

    it('filters the school years by name and status', function (array $query, array $visible) {
        SchoolYear::factory()->create(['name' => '2025–2026', 'starts_on' => '2025-06-16', 'ends_on' => '2026-03-31']);
        SchoolYear::factory()->active()->create(['name' => '2026–2027', 'starts_on' => '2026-06-15', 'ends_on' => '2027-04-16']);
        SchoolYear::factory()->create(['name' => '2027–2028', 'starts_on' => '2026-11-14', 'ends_on' => '2027-04-30']);

        $response = $this->actingAs($this->admin)->get(route('admin.school-years.index', $query));

        expect($response->viewData('schoolYears')->getCollection()->pluck('name')->all())->toBe($visible);
    })->with([
        'name search' => [['search' => '2025'], ['2025–2026']],
        'active' => [['status' => 'active'], ['2026–2027']],
        'upcoming starts tomorrow' => [['status' => 'upcoming'], ['2027–2028']],
        'ended' => [['status' => 'ended'], ['2025–2026']],
        'unknown status is ignored' => [['status' => 'later'], ['2027–2028', '2026–2027', '2025–2026']],
    ]);

    it('ignores filters that are not text', function () {
        SchoolYear::factory()->count(2)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.school-years.index', ['search' => ['2026'], 'status' => ['active']]));

        $response->assertOk();
        expect($response->viewData('schoolYears'))->toHaveCount(2);
    });

    it('counts a school year that starts today as in session, not upcoming', function () {
        SchoolYear::factory()->create(['name' => '2026–2027', 'starts_on' => '2026-11-13', 'ends_on' => '2027-06-30']);

        $response = $this->actingAs($this->admin)->get(route('admin.school-years.index', ['status' => 'upcoming']));

        expect($response->viewData('schoolYears'))->toBeEmpty();
    });

    it('shows a call to action when there are no school years', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.school-years.index'))
            ->assertOk()
            ->assertSeeText('No school years yet')
            ->assertSee(route('admin.school-years.create'), false);
    });

    it('offers to clear the filters when nothing matches', function () {
        SchoolYear::factory()->create(['name' => '2026–2027']);

        $this->actingAs($this->admin)
            ->get(route('admin.school-years.index', ['search' => '1999']))
            ->assertOk()
            ->assertSeeText('No school years match your filters')
            ->assertSeeText('Clear filters');
    });

    it('paginates fifteen school years per page', function () {
        SchoolYear::factory()->count(16)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.school-years.index', ['page' => 2]));

        expect($response->viewData('schoolYears'))->toHaveCount(1)
            ->and($response->viewData('schoolYears')->total())->toBe(16);
    });

    it('runs the same number of queries for more school years', function () {
        SchoolYear::factory()->count(2)->has(AcademicTerm::factory()->count(2), 'terms')->create();
        $this->actingAs($this->admin)->get(route('admin.school-years.index'))->assertOk();

        DB::enableQueryLog();
        $this->get(route('admin.school-years.index'))->assertOk();
        $fewer = count(DB::getQueryLog());
        SchoolYear::factory()->count(6)->has(AcademicTerm::factory()->count(2), 'terms')->create();
        DB::flushQueryLog();
        $this->get(route('admin.school-years.index'))->assertOk();

        expect(count(DB::getQueryLog()))->toBe($fewer);
    });
});

describe('create', function () {
    it('shows the form with four quarters selected and activation off', function () {
        SchoolYear::factory()->active()->create(['name' => '2026–2027']);

        $response = $this->actingAs($this->admin)->get(route('admin.school-years.create'));

        $response->assertOk()
            ->assertViewIs('admin.school-years.form')
            ->assertSee('action="'.route('admin.school-years.store').'"', false)
            ->assertSeeText('2026–2027 stops being active.');
        expect($response->getContent())
            ->toMatch('#<input[^>]*name="create_quarters"[^>]*value="1"[^>]*\schecked(?=[\s/>])#')
            ->not->toMatch('#<input[^>]*name="make_active"[^>]*value="1"[^>]*\schecked(?=[\s/>])#');
    });

    it('forbids every other role', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('admin.school-years.create'))
            ->assertForbidden();
    })->with(['teacher', 'parent']);
});

describe('store', function () {
    it('creates a school year split into four equal quarters', function () {
        $response = $this->actingAs($this->admin)->post(route('admin.school-years.store'), [
            'name' => '2027–2028',
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31',
            'create_quarters' => '1',
            'make_active' => '0',
        ]);

        $schoolYear = SchoolYear::query()->sole();
        $response->assertRedirectToRoute('admin.school-years.show', $schoolYear)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', '2027–2028 was created with 4 quarters.');
        expect($schoolYear)->name->toBe('2027–2028')->is_active->toBeFalse()
            ->and($schoolYear->starts_on->toDateString())->toBe('2027-01-01')
            ->and($schoolYear->ends_on->toDateString())->toBe('2027-12-31')
            ->and($schoolYear->terms->map(fn (AcademicTerm $term): array => [
                $term->sequence, $term->name, $term->starts_on->toDateString(), $term->ends_on->toDateString(), $term->is_active,
            ])->all())->toBe([
                [1, '1st Quarter', '2027-01-01', '2027-04-02', false],
                [2, '2nd Quarter', '2027-04-03', '2027-07-02', false],
                [3, '3rd Quarter', '2027-07-03', '2027-10-01', false],
                [4, '4th Quarter', '2027-10-02', '2027-12-31', false],
            ]);
    });

    it('creates a school year without quarters', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.school-years.store'), [
                'name' => '2027–2028',
                'starts_on' => '2027-06-14',
                'ends_on' => '2028-04-14',
                'create_quarters' => '0',
            ])
            ->assertSessionHas('status', '2027–2028 was created. Add its quarters next.');

        expect(SchoolYear::query()->sole()->terms)->toBeEmpty();
    });

    it('writes a typed year span with an en dash', function (string $typed) {
        $this->actingAs($this->admin)->post(route('admin.school-years.store'), [
            'name' => $typed,
            'starts_on' => '2027-06-14',
            'ends_on' => '2028-04-14',
        ]);

        expect(SchoolYear::query()->sole()->name)->toBe('2027–2028');
    })->with(['hyphen' => '2027-2028', 'spaced hyphen' => ' 2027 - 2028 ', 'em dash' => '2027—2028']);

    it('makes the new school year active and opens the quarter that covers today', function () {
        $this->travelTo('2027-05-10 08:00:00');
        $previous = SchoolYear::factory()->active()->create(['starts_on' => '2026-06-15', 'ends_on' => '2027-04-16']);
        $previousTerm = AcademicTerm::factory()->for($previous)->active()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.school-years.store'), [
                'name' => '2027 Calendar',
                'starts_on' => '2027-04-17',
                'ends_on' => '2027-12-31',
                'create_quarters' => '1',
                'make_active' => '1',
            ])
            ->assertSessionHas('status', '2027 Calendar was created with 4 quarters. It is now the active school year, and 1st Quarter is the current quarter.');

        $schoolYear = SchoolYear::query()->where('name', '2027 Calendar')->sole();
        expect($schoolYear->is_active)->toBeTrue()
            ->and($previous->refresh()->is_active)->toBeFalse()
            ->and($previousTerm->refresh()->is_active)->toBeFalse()
            ->and($schoolYear->terms->where('is_active', true)->pluck('name')->all())->toBe(['1st Quarter']);
    });

    it('explains that no quarter is current when the new active year has not started', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.school-years.store'), [
                'name' => '2027–2028',
                'starts_on' => '2027-06-14',
                'ends_on' => '2028-04-14',
                'create_quarters' => '1',
                'make_active' => '1',
            ])
            ->assertSessionHas('status', '2027–2028 was created with 4 quarters. It is now the active school year. No quarter covers today, so choose the current quarter.');

        expect(AcademicTerm::query()->where('is_active', true)->exists())->toBeFalse();
    });

    it('requires a name and both dates', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.school-years.store'), [])
            ->assertSessionHasErrors([
                'name' => 'The school year name field is required.',
                'starts_on' => 'The start date field is required.',
                'ends_on' => 'The end date field is required.',
            ]);

        expect(SchoolYear::query()->exists())->toBeFalse();
    });

    it('rejects invalid school year details', function (array $payload, string $field, string $message) {
        SchoolYear::factory()->create(['name' => '2020–2021', 'starts_on' => '2020-06-15', 'ends_on' => '2021-03-31']);

        $this->actingAs($this->admin)
            ->post(route('admin.school-years.store'), [
                'name' => '2030–2031',
                'starts_on' => '2030-06-17',
                'ends_on' => '2031-04-11',
                ...$payload,
            ])
            ->assertSessionHasErrors([$field => $message]);

        expect(SchoolYear::query()->count())->toBe(1);
    })->with([
        'long name' => [['name' => str_repeat('a', 51)], 'name', 'The school year name field must not be greater than 50 characters.'],
        'taken name' => [['name' => '2020–2021'], 'name', 'Another school year already uses this name.'],
        'taken name typed with a hyphen' => [['name' => '2020-2021'], 'name', 'Another school year already uses this name.'],
        'invalid start' => [['starts_on' => '2030-02-30'], 'starts_on', 'The start date field must be a valid date.'],
        'invalid end' => [['ends_on' => 'someday'], 'ends_on', 'The end date field must be a valid date.'],
        'end before start' => [['ends_on' => '2030-06-01'], 'ends_on', 'The end date must be after the start date.'],
        'end on the start day' => [['ends_on' => '2030-06-17'], 'ends_on', 'The end date must be after the start date.'],
        'non-boolean activation' => [['make_active' => 'yes please'], 'make_active', 'The make active field must be true or false.'],
        'non-boolean quarters' => [['create_quarters' => 'maybe'], 'create_quarters', 'The create quarters field must be true or false.'],
    ]);

    it('refuses dates that overlap another school year', function (string $startsOn, string $endsOn, string $field) {
        SchoolYear::factory()->create(['name' => '2025–2026', 'starts_on' => '2025-06-16', 'ends_on' => '2026-03-31']);

        $this->actingAs($this->admin)
            ->post(route('admin.school-years.store'), ['name' => 'New year', 'starts_on' => $startsOn, 'ends_on' => $endsOn])
            ->assertSessionHasErrors([$field => 'This school year overlaps 2025–2026.']);

        expect(SchoolYear::query()->count())->toBe(1);
    })->with([
        'starts inside it' => ['2026-03-31', '2027-03-31', 'starts_on'],
        'ends inside it' => ['2024-06-17', '2025-06-16', 'ends_on'],
        'surrounds it' => ['2025-01-01', '2026-12-31', 'ends_on'],
        'inside it' => ['2025-09-01', '2025-12-01', 'starts_on'],
    ]);

    it('allows a school year that starts the day after another ends', function () {
        SchoolYear::factory()->create(['name' => '2025–2026', 'starts_on' => '2025-06-16', 'ends_on' => '2026-03-31']);

        $this->actingAs($this->admin)
            ->post(route('admin.school-years.store'), ['name' => '2026–2027', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31'])
            ->assertSessionHasNoErrors();

        expect(SchoolYear::query()->count())->toBe(2);
    });

    it('needs at least four days to create quarters', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.school-years.store'), ['name' => 'Short', 'starts_on' => '2030-06-01', 'ends_on' => '2030-06-03', 'create_quarters' => '1'])
            ->assertSessionHasErrors(['ends_on' => 'A school year must be at least 4 days long to split it into quarters.']);
    });

    it('never takes the active flag from the payload', function () {
        $this->actingAs($this->admin)->post(route('admin.school-years.store'), [
            'name' => '2030–2031',
            'starts_on' => '2030-06-17',
            'ends_on' => '2031-04-11',
            'is_active' => '1',
        ]);

        expect(SchoolYear::query()->sole()->is_active)->toBeFalse();
    });

    it('forbids every other role before validating', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->post(route('admin.school-years.store'), [])
            ->assertForbidden()
            ->assertSessionHasNoErrors();
    })->with(['teacher', 'parent']);
});

describe('show', function () {
    it('shows the quarters timeline, classrooms by grade and enrolled learners', function () {
        $schoolYear = SchoolYear::factory()->active()->create(['name' => '2026–2027', 'starts_on' => '2026-06-15', 'ends_on' => '2027-04-16']);
        AcademicTerm::factory()->for($schoolYear)->create(['name' => '2nd Quarter', 'sequence' => 2, 'starts_on' => '2026-08-31', 'ends_on' => '2026-11-27', 'is_active' => true]);
        AcademicTerm::factory()->for($schoolYear)->create(['name' => '1st Quarter', 'sequence' => 1, 'starts_on' => '2026-06-15', 'ends_on' => '2026-08-28']);
        AcademicTerm::factory()->for($schoolYear)->create(['name' => '3rd Quarter', 'sequence' => 3, 'starts_on' => '2026-11-30', 'ends_on' => '2027-02-12']);
        $gradeFive = Classroom::factory()->for($schoolYear)->create(['grade_level' => GradeLevel::Five]);
        Classroom::factory()->for($schoolYear)->archived()->create(['grade_level' => GradeLevel::Five]);
        $gradeSix = Classroom::factory()->for($schoolYear)->create(['grade_level' => GradeLevel::Six]);
        $learner = User::factory()->student(GradeLevel::Five)->enrolledIn($gradeFive)->create();
        User::factory()->student(GradeLevel::Six)->enrolledIn($gradeSix)->create();
        $gradeSix->students()->attach($learner);
        Classroom::factory()->for(SchoolYear::factory()->create(['name' => 'Other year']))->create();

        $response = $this->actingAs($this->admin)->get(route('admin.school-years.show', $schoolYear));

        $response->assertOk()
            ->assertViewIs('admin.school-years.show')
            ->assertSeeInOrder(['1st Quarter', '2nd Quarter', '3rd Quarter'])
            ->assertSee('data-term-status="ended"', false)
            ->assertSee('data-term-status="current"', false)
            ->assertSee('data-term-status="upcoming"', false)
            ->assertSee(route('admin.school-years.terms.create', $schoolYear), false)
            ->assertSee(route('admin.terms.activation.update', AcademicTerm::query()->where('name', '3rd Quarter')->sole()), false);
        expect($response->viewData('learnersCount'))->toBe(2)
            ->and($response->viewData('classroomsByGrade')->map(fn (array $row): array => [$row['grade'], $row['total'], $row['open']])->all())
            ->toBe([[GradeLevel::Five, 2, 1], [GradeLevel::Six, 1, 1]]);
    });

    it('offers to add or split quarters when there are none', function () {
        $schoolYear = SchoolYear::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.school-years.show', $schoolYear))
            ->assertOk()
            ->assertSeeText('No quarters yet')
            ->assertSee(route('admin.school-years.edit', [$schoolYear, 'quarters' => 1]), false)
            ->assertSeeText('No classrooms yet');
    });

    it('hides quarter activation while the school year is not active', function () {
        $schoolYear = SchoolYear::factory()->create();
        $term = AcademicTerm::factory()->for($schoolYear)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.school-years.show', $schoolYear))
            ->assertOk()
            ->assertDontSee(route('admin.terms.activation.update', $term), false)
            ->assertSee(route('admin.school-years.activation.update', $schoolYear), false)
            ->assertSeeText('Activate this school year to choose its current quarter');
    });

    it('disables adding a ninth quarter', function () {
        $schoolYear = SchoolYear::factory()->create();
        AcademicTerm::factory()->for($schoolYear)->count(8)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.school-years.show', $schoolYear));

        $response->assertOk()->assertDontSee(route('admin.school-years.terms.create', $schoolYear), false);
        expect($response->viewData('canAddTerm'))->toBeFalse();
    });

    it('answers an unknown school year with 404', function () {
        $this->actingAs($this->admin)->get(route('admin.school-years.show', 999999))->assertNotFound();
    });
});

describe('edit', function () {
    it('shows the current values and offers quarters only while there are none', function () {
        $schoolYear = SchoolYear::factory()->create(['name' => '2027–2028', 'starts_on' => '2027-06-14', 'ends_on' => '2028-04-14']);

        $response = $this->actingAs($this->admin)->get(route('admin.school-years.edit', [$schoolYear, 'quarters' => 1]));

        $response->assertOk()
            ->assertSee('value="2027–2028"', false)
            ->assertSee('value="2027-06-14"', false)
            ->assertSee('value="2028-04-14"', false)
            ->assertSee('name="make_active"', false);
        expect($response->getContent())->toMatch('#<input[^>]*name="create_quarters"[^>]*value="1"[^>]*\schecked(?=[\s/>])#');

        AcademicTerm::factory()->for($schoolYear)->create();

        $this->get(route('admin.school-years.edit', $schoolYear))
            ->assertOk()
            ->assertDontSee('name="create_quarters"', false)
            ->assertSeeText('This school year has 1 quarter');
    });

    it('does not offer activation for the active school year', function () {
        $schoolYear = SchoolYear::factory()->active()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.school-years.edit', $schoolYear))
            ->assertOk()
            ->assertDontSee('name="make_active"', false)
            ->assertSeeText('This is the active school year');
    });
});

describe('update', function () {
    it('updates the name and dates', function () {
        $schoolYear = SchoolYear::factory()->create(['name' => '2027–2028', 'starts_on' => '2027-06-14', 'ends_on' => '2028-04-14']);

        $this->actingAs($this->admin)
            ->put(route('admin.school-years.update', $schoolYear), ['name' => 'SY 2027–2028', 'starts_on' => '2027-06-07', 'ends_on' => '2028-04-21'])
            ->assertRedirectToRoute('admin.school-years.show', $schoolYear)
            ->assertSessionHas('status', 'SY 2027–2028 was updated.');

        expect($schoolYear->refresh())->name->toBe('SY 2027–2028')
            ->and($schoolYear->starts_on->toDateString())->toBe('2027-06-07')
            ->and($schoolYear->ends_on->toDateString())->toBe('2028-04-21');
    });

    it('keeps its own name and dates valid', function () {
        $schoolYear = SchoolYear::factory()->create(['name' => '2027–2028', 'starts_on' => '2027-06-14', 'ends_on' => '2028-04-14']);

        $this->actingAs($this->admin)
            ->put(route('admin.school-years.update', $schoolYear), ['name' => '2027–2028', 'starts_on' => '2027-06-14', 'ends_on' => '2028-04-14'])
            ->assertSessionHasNoErrors();
    });

    it('lists the quarters that would fall outside the new dates', function () {
        $schoolYear = SchoolYear::factory()->create(['starts_on' => '2027-06-14', 'ends_on' => '2028-04-14']);
        AcademicTerm::factory()->for($schoolYear)->create(['name' => '1st Quarter', 'sequence' => 1, 'starts_on' => '2027-06-14', 'ends_on' => '2027-08-27']);
        AcademicTerm::factory()->for($schoolYear)->create(['name' => '2nd Quarter', 'sequence' => 2, 'starts_on' => '2027-08-30', 'ends_on' => '2027-11-26']);
        AcademicTerm::factory()->for($schoolYear)->create(['name' => '4th Quarter', 'sequence' => 4, 'starts_on' => '2028-02-14', 'ends_on' => '2028-04-14']);

        $this->actingAs($this->admin)
            ->put(route('admin.school-years.update', $schoolYear), ['name' => $schoolYear->name, 'starts_on' => '2027-09-01', 'ends_on' => '2028-03-31'])
            ->assertSessionHasErrors([
                'starts_on' => 'These quarters start before the new start date: 1st Quarter, 2nd Quarter. Edit or delete them first.',
                'ends_on' => 'These quarters end after the new end date: 4th Quarter. Edit or delete them first.',
            ]);

        expect($schoolYear->refresh()->starts_on->toDateString())->toBe('2027-06-14');
    });

    it('refuses dates that overlap another school year', function () {
        SchoolYear::factory()->create(['name' => '2025–2026', 'starts_on' => '2025-06-16', 'ends_on' => '2026-03-31']);
        $schoolYear = SchoolYear::factory()->create(['name' => '2026–2027', 'starts_on' => '2026-06-15', 'ends_on' => '2027-04-16']);

        $this->actingAs($this->admin)
            ->put(route('admin.school-years.update', $schoolYear), ['name' => $schoolYear->name, 'starts_on' => '2026-03-01', 'ends_on' => '2027-04-16'])
            ->assertSessionHasErrors(['starts_on' => 'This school year overlaps 2025–2026.']);
    });

    it('refuses a name another school year uses', function () {
        SchoolYear::factory()->create(['name' => '2025–2026', 'starts_on' => '2025-06-16', 'ends_on' => '2026-03-31']);
        $schoolYear = SchoolYear::factory()->create(['name' => '2040–2041', 'starts_on' => '2040-06-18', 'ends_on' => '2041-04-12']);

        $this->actingAs($this->admin)
            ->put(route('admin.school-years.update', $schoolYear), ['name' => '2025–2026', 'starts_on' => '2040-06-15', 'ends_on' => '2041-04-16'])
            ->assertSessionHasErrors(['name' => 'Another school year already uses this name.']);
    });

    it('creates the four quarters of a school year that has none', function () {
        $schoolYear = SchoolYear::factory()->create(['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);

        $this->actingAs($this->admin)
            ->put(route('admin.school-years.update', $schoolYear), ['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'create_quarters' => '1'])
            ->assertSessionHas('status', '2027 was updated. Its 4 quarters were created.');

        expect($schoolYear->terms()->pluck('name')->all())->toBe(['1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter']);
    });

    it('refuses to create quarters again', function () {
        $schoolYear = SchoolYear::factory()->create();
        AcademicTerm::factory()->for($schoolYear)->create();

        $this->actingAs($this->admin)
            ->put(route('admin.school-years.update', $schoolYear), [
                'name' => $schoolYear->name,
                'starts_on' => $schoolYear->starts_on->toDateString(),
                'ends_on' => $schoolYear->ends_on->toDateString(),
                'create_quarters' => '1',
            ])
            ->assertSessionHasErrors(['create_quarters' => 'This school year already has quarters. Add or edit them on the school year page.']);

        expect($schoolYear->terms()->count())->toBe(1);
    });

    it('activates the school year when asked', function () {
        $active = SchoolYear::factory()->active()->create(['name' => '2024–2025', 'starts_on' => '2024-06-17', 'ends_on' => '2025-03-31']);
        $schoolYear = SchoolYear::factory()->create(['name' => 'Bridge year', 'starts_on' => '2026-11-01', 'ends_on' => '2027-06-30']);
        $term = AcademicTerm::factory()->for($schoolYear)->create(['starts_on' => '2026-11-01', 'ends_on' => '2027-01-31']);

        $this->actingAs($this->admin)
            ->put(route('admin.school-years.update', $schoolYear), [
                'name' => $schoolYear->name,
                'starts_on' => '2026-11-01',
                'ends_on' => '2027-06-30',
                'make_active' => '1',
            ])
            ->assertSessionHas('status', "{$schoolYear->name} was updated. It is now the active school year, and {$term->name} is the current quarter.");

        expect($schoolYear->refresh()->is_active)->toBeTrue()
            ->and($active->refresh()->is_active)->toBeFalse()
            ->and($term->refresh()->is_active)->toBeTrue();
    });

    it('answers an unknown school year with 404', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.school-years.update', 999999), ['name' => 'x', 'starts_on' => '2030-01-01', 'ends_on' => '2030-12-31'])
            ->assertNotFound();
    });
});

describe('destroy', function () {
    it('deletes a school year and its quarters', function () {
        $schoolYear = SchoolYear::factory()->create(['name' => '2019–2020']);
        $terms = AcademicTerm::factory()->for($schoolYear)->count(4)->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.school-years.destroy', $schoolYear))
            ->assertRedirectToRoute('admin.school-years.index')
            ->assertSessionHas('status', '2019–2020 was deleted.');

        $this->assertModelMissing($schoolYear);
        $terms->each(fn (AcademicTerm $term) => $this->assertModelMissing($term));
    });

    it('refuses to delete the active school year', function () {
        $schoolYear = SchoolYear::factory()->active()->create();

        $this->actingAs($this->admin)
            ->from(route('admin.school-years.index'))
            ->delete(route('admin.school-years.destroy', $schoolYear))
            ->assertRedirect(route('admin.school-years.index'))
            ->assertSessionHas('error', 'Activate another school year first.');

        $this->assertModelExists($schoolYear);
    });

    it('refuses to delete a school year that has classrooms', function () {
        $schoolYear = SchoolYear::factory()->create(['name' => '2024–2025']);
        Classroom::factory()->for($schoolYear)->archived()->create();

        $this->actingAs($this->admin)
            ->from(route('admin.school-years.show', $schoolYear))
            ->delete(route('admin.school-years.destroy', $schoolYear))
            ->assertRedirect(route('admin.school-years.show', $schoolYear))
            ->assertSessionHas('error', '2024–2025 has classrooms. Archive or move them first.');

        $this->assertModelExists($schoolYear);
    });

    it('forbids every other role', function (string $role) {
        $schoolYear = SchoolYear::factory()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->delete(route('admin.school-years.destroy', $schoolYear))
            ->assertForbidden();

        $this->assertModelExists($schoolYear);
    })->with(['teacher', 'parent']);
});
