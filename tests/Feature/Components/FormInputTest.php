<?php

use App\Enums\QuizStatus;
use Illuminate\Support\Carbon;

/**
 * Flash old input the way a failed form submission does and attach the session to the current request.
 *
 * @param  array<string, mixed>  $input
 */
function flashOldInputForFormInput(array $input): void
{
    $session = app('session')->driver();
    $session->start();
    $session->flashInput($input);
    request()->setLaravelSession($session);
}

it('links the label to the input derived from the name', function () {
    $view = $this->blade('<x-form.input name="title" label="Quiz title" />');

    $view->assertSee('for="title"', false)
        ->assertSee('id="title"', false)
        ->assertSee('name="title"', false)
        ->assertSee('type="text"', false)
        ->assertSeeText('Quiz title');
});

it('marks a required field with an asterisk and the required attribute', function () {
    $view = $this->blade('<x-form.input name="title" label="Quiz title" required />');

    $view->assertSee('<span class="text-berry-600 dark:text-berry-400" aria-hidden="true">*</span>', false);

    expect((string) $view)->toMatch('#<input[^>]*\srequired(?=[\s/>])#');
});

it('marks an optional field without the required attribute', function () {
    $view = $this->blade('<x-form.input name="phone" label="Mobile number" optional />');

    $view->assertSeeText('(optional)');

    expect((string) $view)->not->toMatch('#<input[^>]*\srequired(?=[\s/>])#');
});

it('shows the first error for the field from the default bag', function () {
    $this->withViewErrors(['email' => 'The email field is required.']);

    $view = $this->blade('<x-form.input name="email" type="email" label="Email" />');

    $view->assertSeeText('The email field is required.')
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('aria-describedby="email-error"', false)
        ->assertSee('id="email-error"', false)
        ->assertSee('ring-berry-500/70', false);
});

it('reads errors from the named bag only', function () {
    $this->withViewErrors(['password' => 'The password is incorrect.'], 'updatePassword');

    $this->blade('<x-form.input name="password" type="password" label="Password" bag="updatePassword" />')
        ->assertSeeText('The password is incorrect.');

    $this->blade('<x-form.input name="password" type="password" label="Password" />')
        ->assertDontSeeText('The password is incorrect.')
        ->assertDontSee('aria-invalid', false);
});

it('resolves dotted error keys and dashed ids for array names', function () {
    $this->withViewErrors(['choices.0' => 'The first choice is required.']);

    $view = $this->blade('<x-form.input name="choices[0]" label="Choice A" />');

    $view->assertSee('name="choices[0]"', false)
        ->assertSee('id="choices-0"', false)
        ->assertSee('id="choices-0-error"', false)
        ->assertSeeText('The first choice is required.');
});

it('does not show errors that belong to a sibling array item', function () {
    $this->withViewErrors(['choices.1' => 'The second choice is required.']);

    $this->blade('<x-form.input name="choices[0]" label="Choice A" />')
        ->assertDontSeeText('The second choice is required.');
});

it('uses an explicit error key instead of the name', function () {
    $this->withViewErrors(['email' => 'These credentials do not match our records.']);

    $this->blade('<x-form.input name="login" label="Email or username" error-key="email" />')
        ->assertSeeText('These credentials do not match our records.');
});

it('prefers old input over the value prop', function () {
    flashOldInputForFormInput(['title' => 'Healthy Snacks Quiz']);

    $this->blade('<x-form.input name="title" value="Draft title" />')
        ->assertSee('value="Healthy Snacks Quiz"', false)
        ->assertDontSee('value="Draft title"', false);
});

it('reads old input for dotted array names', function () {
    flashOldInputForFormInput(['choices' => ['Banana', 'Apple']]);

    $this->blade('<x-form.input name="choices[1]" />')
        ->assertSee('value="Apple"', false);
});

it('ignores old input when the field belongs to another form', function () {
    flashOldInputForFormInput(['name' => 'Edited elsewhere']);

    $this->blade('<x-form.input name="name" value="Healthy Habits" :old="false" />')
        ->assertSee('value="Healthy Habits"', false)
        ->assertDontSee('Edited elsewhere', false);
});

it('falls back to the value prop without old input', function () {
    $this->blade('<x-form.input name="title" value="Draft title" />')
        ->assertSee('value="Draft title"', false);
});

it('never repopulates a password field', function () {
    flashOldInputForFormInput(['secret' => 'hunter2']);

    $this->blade('<x-form.input name="secret" type="password" value="hunter2" />')
        ->assertDontSee('hunter2', false);
});

it('formats dates and backed enums for the input value', function () {
    $this->blade('<x-form.input name="starts_on" type="date" :value="$date" />', ['date' => Carbon::parse('2026-06-15 08:30:00')])
        ->assertSee('value="2026-06-15"', false);

    $this->blade('<x-form.input name="status" :value="$status" />', ['status' => QuizStatus::Draft])
        ->assertSee('value="draft"', false);
});

it('describes the input with its hint', function () {
    $view = $this->blade('<x-form.input name="code" label="Code" hint="Up to 20 characters." />');

    $view->assertSee('aria-describedby="code-hint"', false)
        ->assertSee('id="code-hint"', false)
        ->assertSeeText('Up to 20 characters.');
});

it('puts the class on the wrapper and other attributes on the input', function () {
    $html = (string) $this->blade('<x-form.input name="room" class="sm:col-span-2" placeholder="Room 4" />');

    expect($html)->toMatch('/<div class="[^"]*sm:col-span-2[^"]*">/')
        ->and($html)->toMatch('/<input[^>]*placeholder="Room 4"/')
        ->and($html)->not->toMatch('/<input[^>]*sm:col-span-2/');
});

it('escapes values', function () {
    $this->blade('<x-form.input name="title" :value="$title" />', ['title' => '"><script>alert(1)</script>'])
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false);
});

it('renders prefix and suffix adornments with matching padding', function () {
    $view = $this->blade('<x-form.input name="passing_score" type="number" suffix="%" prefix-icon="target" />');

    $view->assertSee('data-icon="target"', false)
        ->assertSee('pl-10', false)
        ->assertSee('pr-12', false)
        ->assertSeeText('%');
});
