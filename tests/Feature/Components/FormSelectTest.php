<?php

use App\Enums\QuizStatus;

/**
 * Flash old input the way a failed form submission does and attach the session to the current request.
 *
 * @param  array<string, mixed>  $input
 */
function flashOldInputForFormSelect(array $input): void
{
    $session = app('session')->driver();
    $session->start();
    $session->flashInput($input);
    request()->setLaravelSession($session);
}

it('renders the options and selects the value', function () {
    $view = $this->blade(
        '<x-form.select name="grade_level" label="Grade" :options="$options" :value="6" />',
        ['options' => [5 => 'Grade 5', 6 => 'Grade 6']],
    );

    $view->assertSee('<option value="5" >Grade 5</option>', false)
        ->assertSee('<option value="6" selected>Grade 6</option>', false)
        ->assertSee('for="grade_level"', false);
});

it('selects the placeholder when there is no value', function () {
    $view = $this->blade(
        '<x-form.select name="status" placeholder="All statuses" :options="$options" />',
        ['options' => QuizStatus::options()],
    );

    $view->assertSee('<option value="" selected>All statuses</option>', false)
        ->assertDontSee('value="draft" selected', false);
});

it('selects the case of a backed enum value', function () {
    $this->blade(
        '<x-form.select name="status" :options="$options" :value="$status" />',
        ['options' => QuizStatus::options(), 'status' => QuizStatus::Closed],
    )->assertSee('<option value="closed" selected>Closed</option>', false);
});

it('prefers old input over the value', function () {
    flashOldInputForFormSelect(['status' => 'published']);

    $this->blade(
        '<x-form.select name="status" :options="$options" value="draft" />',
        ['options' => QuizStatus::options()],
    )
        ->assertSee('<option value="published" selected>Published</option>', false)
        ->assertDontSee('<option value="draft" selected>', false);
});

it('groups options into optgroups', function () {
    $view = $this->blade(
        '<x-form.select name="module_id" :options="$options" />',
        ['options' => ['Grade 5' => [1 => 'Go, Grow and Glow'], 'Grade 6' => [2 => 'Food Safety']]],
    );

    $view->assertSee('<optgroup label="Grade 5">', false)
        ->assertSee('<option value="2" >Food Safety</option>', false);
});

it('selects every value of a multiple select and shows item errors', function () {
    $this->withViewErrors(['classrooms.1' => 'The selected classroom is invalid.']);

    $view = $this->blade(
        '<x-form.select name="classrooms[]" label="Classrooms" multiple :options="$options" :value="[1, 3]" />',
        ['options' => [1 => 'Grade 5 – Rizal', 2 => 'Grade 5 – Mabini', 3 => 'Grade 6 – Bonifacio']],
    );

    $view->assertSee('multiple', false)
        ->assertSee('<option value="1" selected>', false)
        ->assertSee('<option value="2" >', false)
        ->assertSee('<option value="3" selected>', false)
        ->assertSeeText('The selected classroom is invalid.')
        ->assertDontSee('data-icon="chevron-down"', false);
});

it('shows the field error', function () {
    $this->withViewErrors(['subject_id' => 'Choose one of your assigned subjects.']);

    $view = $this->blade('<x-form.select name="subject_id" label="Subject" :options="[1 => \'Health\']" />');

    $view->assertSeeText('Choose one of your assigned subjects.')
        ->assertSee('aria-invalid="true"', false);
});

it('renders slot options instead of the options array', function () {
    $view = $this->blade('<x-form.select name="term"><option value="q1">1st Quarter</option></x-form.select>');

    $view->assertSee('<option value="q1">1st Quarter</option>', false);
});
