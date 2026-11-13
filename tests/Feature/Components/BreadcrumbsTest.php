<?php

it('links every ancestor and marks the current page', function () {
    $html = (string) $this->blade('<x-breadcrumbs :items="$items" />', ['items' => [
        ['label' => 'Classrooms', 'url' => '/teacher/classrooms'],
        ['label' => 'Grade 6 – Rizal', 'url' => '/teacher/classrooms/4'],
        ['label' => 'Add learner'],
    ]]);

    expect($html)->toContain('aria-label="Breadcrumb"')
        ->toContain('<a href="/teacher/classrooms"')
        ->toContain('<a href="/teacher/classrooms/4"')
        ->toMatch('/<span\s+aria-current="page"[^>]*>Add learner<\/span>/');
});

it('keeps only the parent link visible on small screens', function () {
    $html = (string) $this->blade('<x-breadcrumbs :items="$items" />', ['items' => [
        ['label' => 'Quizzes', 'url' => '/teacher/quizzes'],
        ['label' => 'Healthy Snacks', 'url' => '/teacher/quizzes/3'],
        ['label' => 'Edit'],
    ]]);

    expect(substr_count($html, 'max-sm:hidden'))->toBe(3)
        ->and($html)->toContain('data-icon="chevron-left"');
});

it('does not link the current page even when it has a url', function () {
    $this->blade('<x-breadcrumbs :items="[[\'label\' => \'Dashboard\', \'url\' => \'/admin\']]" />')
        ->assertDontSee('<a href="/admin"', false);
});

it('renders nothing without items', function () {
    expect(trim((string) $this->blade('<x-breadcrumbs :items="[]" />')))->toBe('');
});
