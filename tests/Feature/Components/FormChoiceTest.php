<?php

use App\Enums\LearningMode;

/**
 * Flash old input the way a failed form submission does and attach the session to the current request.
 *
 * @param  array<string, mixed>  $input
 */
function flashOldInputForFormChoice(array $input): void
{
    $session = app('session')->driver();
    $session->start();
    $session->flashInput($input);
    request()->setLaravelSession($session);
}

/**
 * Pattern for an input tag with the given value that carries the bare checked attribute. The lookahead keeps
 * Tailwind classes such as "checked:bg-leaf-600" from matching.
 */
function checkedInputPattern(string $value): string
{
    return '#<input[^>]*value="'.preg_quote($value, '#').'"[^>]*\schecked(?=[\s/>])#';
}

it('checks a checkbox from the checked prop when there is no old input', function () {
    $html = (string) $this->blade('<x-form.checkbox name="is_active" label="Active" :checked="true" />');

    expect($html)->toMatch(checkedInputPattern('1'));
});

it('unchecks a checkbox that was left unchecked in the failed submission', function () {
    flashOldInputForFormChoice(['name' => 'Ana']);

    $html = (string) $this->blade('<x-form.checkbox name="is_active" label="Active" :checked="true" />');

    expect($html)->not->toMatch(checkedInputPattern('1'));
});

it('restores the checked members of a checkbox group from old input', function () {
    flashOldInputForFormChoice(['classrooms' => ['3', '7']]);

    $checked = (string) $this->blade('<x-form.checkbox name="classrooms[]" value="7" label="Grade 6 – Rizal" />');
    $unchecked = (string) $this->blade('<x-form.checkbox name="classrooms[]" value="5" label="Grade 5 – Mabini" />');

    expect($checked)->toMatch(checkedInputPattern('7'))->toContain('id="classrooms-7"')
        ->and($unchecked)->not->toMatch(checkedInputPattern('5'))->toContain('id="classrooms-5"');
});

it('keeps its own state when old input belongs to another form', function () {
    flashOldInputForFormChoice(['name' => 'Ana']);

    $checkbox = (string) $this->blade('<x-form.checkbox name="is_active" label="Active" :checked="true" :old="false" />');
    $select = (string) $this->blade('<x-form.select name="color" :options="[\'leaf\' => \'Leaf\', \'sky\' => \'Sky\']" value="sky" :old="false" />');

    expect($checkbox)->toMatch(checkedInputPattern('1'))
        ->and($select)->toContain('<option value="sky" selected>Sky</option>');
});

it('posts the unchecked value through a hidden input', function () {
    $this->blade('<x-form.checkbox name="shuffle_questions" label="Shuffle" unchecked-value="0" />')
        ->assertSee('<input type="hidden" name="shuffle_questions" value="0">', false);
});

it('renders a toggle as a switch and reads old input', function () {
    flashOldInputForFormChoice(['show_correct_answers' => '1']);

    $view = $this->blade('<x-form.toggle name="show_correct_answers" label="Show correct answers" />');

    $view->assertSee('role="switch"', false);

    expect((string) $view)->toMatch(checkedInputPattern('1'));
});

it('builds radio cards from enum cases with their descriptions and icons', function () {
    $view = $this->blade(
        '<x-form.radio-cards name="learning_mode" label="Learning mode" :options="$options" :value="$value" />',
        ['options' => LearningMode::cases(), 'value' => LearningMode::Manual],
    );

    foreach (LearningMode::cases() as $mode) {
        $view->assertSeeText($mode->label())
            ->assertSeeText($mode->description())
            ->assertSee('data-icon="'.$mode->icon().'"', false);
    }

    $view->assertSee('id="learning_mode-manual"', false);

    expect((string) $view)->toMatch(checkedInputPattern('manual'))
        ->not->toMatch(checkedInputPattern('automatic'))
        ->not->toMatch(checkedInputPattern('learner_choice'));
});

it('restores the radio card choice from old input', function () {
    flashOldInputForFormChoice(['learning_mode' => 'learner_choice']);

    $html = (string) $this->blade(
        '<x-form.radio-cards name="learning_mode" :options="$options" value="automatic" />',
        ['options' => LearningMode::cases()],
    );

    expect($html)->toMatch(checkedInputPattern('learner_choice'))
        ->not->toMatch(checkedInputPattern('automatic'));
});

it('offers every palette key as a color swatch and checks the value', function () {
    $view = $this->blade('<x-form.color-picker name="color" value="grape" />');

    foreach (['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate'] as $key) {
        $view->assertSee('value="'.$key.'"', false);
    }

    expect((string) $view)->toMatch(checkedInputPattern('grape'))
        ->not->toMatch(checkedInputPattern('leaf'));
});

it('offers only icons that exist in the icon set', function () {
    $view = $this->blade('<x-form.icon-picker name="icon" :icons="[\'apple\', \'not-a-real-icon\']" value="apple" />');

    $view->assertDontSee('not-a-real-icon', false);

    expect((string) $view)->toMatch(checkedInputPattern('apple'));
});

it('adds a reveal toggle to the password field', function () {
    $this->withViewErrors(['current_password' => 'The password is incorrect.'], 'updatePassword');

    $view = $this->blade('<x-form.password name="current_password" label="Current password" bag="updatePassword" />');

    $view->assertSee('data-password-toggle', false)
        ->assertSee('aria-controls="current_password"', false)
        ->assertSee('aria-pressed="false"', false)
        ->assertSeeText('The password is incorrect.');
});

it('counts characters of a limited textarea from its current value', function () {
    $view = $this->blade('<x-form.textarea name="message" :max-length="500" value="Great job!" />');

    $view->assertSee('maxlength="500"', false)
        ->assertSee('data-max-length="500"', false)
        ->assertSee('data-char-count-for="message"', false)
        ->assertSeeText('10 / 500');
});
