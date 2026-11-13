<?php

it('builds a dropdown on a native disclosure with menu items', function () {
    $html = (string) $this->blade(<<<'BLADE'
        <x-dropdown label="Quiz actions" icon="ellipsis-vertical" variant="icon" align="left">
            <x-dropdown.item href="/teacher/quizzes/3/edit" icon="pencil">Edit</x-dropdown.item>
            <x-dropdown.divider />
            <x-dropdown.item type="submit" icon="trash-2" danger>Delete</x-dropdown.item>
        </x-dropdown>
        BLADE);

    expect($html)->toMatch('/<details\s+data-dropdown/')
        ->toContain('aria-label="Quiz actions"')
        ->toContain('left-0 origin-top-left')
        ->toMatch('/<a href="\/teacher\/quizzes\/3\/edit" data-dropdown-item/')
        ->toMatch('/<button type="submit" data-dropdown-item/')
        ->toContain('text-berry-700')
        ->toContain('role="separator"');
});

it('shows the label and a chevron on a text dropdown trigger', function () {
    $html = (string) $this->blade('<x-dropdown label="Export"><x-dropdown.item href="/export.csv">CSV</x-dropdown.item></x-dropdown>');

    expect($html)->toContain('<span>Export</span>')
        ->toContain('data-icon="chevron-down"');
});

it('offers the three theme choices', function () {
    $html = (string) $this->blade('<x-theme-switch />');

    expect($html)->toContain('data-theme-set="light"')
        ->toContain('data-theme-set="dark"')
        ->toContain('data-theme-set="system"')
        ->toContain('data-theme-icon="system"')
        ->toContain('data-js-only');
});

it('copies its text and announces the result', function () {
    $html = (string) $this->blade('<x-copy-button text="K7M2QX" label="Copy code" />');

    expect($html)->toContain('data-copy="K7M2QX"')
        ->toContain('data-copy-feedback="Copied!"')
        ->toContain('<span data-copy-label>Copy code</span>')
        ->toContain('aria-live="polite"')
        ->toContain('data-js-only');
});

it('previews the current image and offers to remove it', function () {
    $html = (string) $this->blade('<x-form.file name="image" label="Question image" accept="image/*" preview current="/storage/quiz-media/images/a.png" remove-name="remove_image" />');

    expect($html)->toContain('data-image-preview="image-preview"')
        ->toMatch('#id="image-preview"\s+src="/storage/quiz-media/images/a\.png"#')
        ->toContain('accept="image/*"')
        ->toContain('name="remove_image"')
        ->toContain('data-file-remove')
        ->toContain('Choose a replacement file');
});

it('hides the empty preview and links a current non-image file', function () {
    $html = (string) $this->blade('<x-form.file name="video" label="Video" current="/storage/quiz-media/videos/b.mp4" current-label="lesson.mp4" />');

    expect($html)->toContain('<a href="/storage/quiz-media/videos/b.mp4" target="_blank" rel="noopener noreferrer"')
        ->toContain('lesson.mp4')
        ->not->toContain('data-image-preview');
});

it('shows the upload error', function () {
    $this->withViewErrors(['image' => 'The image may not be greater than 2048 kilobytes.']);

    $this->blade('<x-form.file name="image" label="Question image" />')
        ->assertSeeText('The image may not be greater than 2048 kilobytes.')
        ->assertSee('aria-invalid="true"', false);
});

it('lays a form section out in two columns when asked', function () {
    $this->blade('<x-form.section title="Profile" description="Basic details." columns="2">Fields</x-form.section>')
        ->assertSee('@lg:grid-cols-2', false)
        ->assertSeeText('Basic details.');
});

it('renders sticky form actions with a cancel link', function () {
    $html = (string) $this->blade('<x-form.actions cancel="/teacher/quizzes" submit="Create quiz" submit-icon="plus" />');

    expect($html)->toContain('sticky bottom-0')
        ->toContain('href="/teacher/quizzes"')
        ->toContain('Cancel')
        ->toContain('type="submit"')
        ->toContain('Create quiz')
        ->toContain('data-icon="plus"');
});

it('renders inline form actions without a cancel link', function () {
    $html = (string) $this->blade('<x-form.actions :sticky="false" />');

    expect($html)->not->toContain('sticky bottom-0')
        ->not->toContain('Cancel')
        ->toContain('Save changes');
});
