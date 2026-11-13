<?php

it('renders a card with header and footer slots', function () {
    $html = (string) $this->blade(<<<'BLADE'
        <x-card padding="lg">
            <x-slot:header><h2>Roster</h2></x-slot:header>
            Body
            <x-slot:footer>Footer</x-slot:footer>
        </x-card>
        BLADE);

    expect($html)->toContain('<div class="p-6 sm:p-8">')
        ->toContain('<h2>Roster</h2>')
        ->toContain('Footer')
        ->not->toContain('hover:-translate-y-px');
});

it('turns a card with an href into an interactive link', function () {
    $html = (string) $this->blade('<x-card href="/teacher/classrooms/4">Grade 6 – Rizal</x-card>');

    expect($html)->toContain('<a')
        ->toContain('href="/teacher/classrooms/4"')
        ->toContain('hover:-translate-y-px');
});

it('renders a section with title, description, icon and actions', function () {
    $html = (string) $this->blade(<<<'BLADE'
        <x-section title="Recent submissions" description="Latest quiz attempts" icon="clipboard-check" color="grape">
            <x-slot:actions><a href="/teacher/quizzes">View all</a></x-slot:actions>
            Rows
        </x-section>
        BLADE);

    expect($html)->toContain('<h2 class="font-display text-base font-semibold')
        ->toContain('Recent submissions')
        ->toContain('Latest quiz attempts')
        ->toContain('bg-grape-50 text-grape-700')
        ->toContain('data-icon="clipboard-check"')
        ->toContain('<a href="/teacher/quizzes">View all</a>')
        ->toContain('Rows');
});

it('renders the page header with eyebrow, breadcrumbs, meta and actions', function () {
    $html = (string) $this->blade(<<<'BLADE'
        <x-page-header title="Quizzes" eyebrow="Assessments" description="Build and review quizzes." :breadcrumbs="[['label' => 'Dashboard', 'url' => '/teacher'], ['label' => 'Quizzes']]">
            <x-slot:meta><span>12 quizzes</span></x-slot:meta>
            <x-slot:actions><a href="/teacher/quizzes/create">New quiz</a></x-slot:actions>
        </x-page-header>
        BLADE);

    expect($html)->toMatch('/<h1[^>]*>Quizzes<\/h1>/')
        ->toContain('Assessments')
        ->toContain('Build and review quizzes.')
        ->toContain('aria-label="Breadcrumb"')
        ->toContain('<span>12 quizzes</span>')
        ->toContain('<a href="/teacher/quizzes/create">New quiz</a>');
});

it('marks the active tab as the current page and shows counts', function () {
    $html = (string) $this->blade(<<<'BLADE'
        <x-tabs label="Learner sections">
            <x-tab href="?tab=overview" :active="true">Overview</x-tab>
            <x-tab href="?tab=quizzes" :count="4">Quizzes</x-tab>
        </x-tabs>
        BLADE);

    expect($html)->toContain('aria-label="Learner sections"')
        ->toMatch('/href="\?tab=overview"\s+aria-current="page"/')
        ->not->toMatch('/href="\?tab=quizzes"\s+aria-current/')
        ->toMatch('/tabular-nums[^"]*">4<\/span>/');
});

it('lays out detail rows for the definition list columns and dashes empty values', function () {
    $single = (string) $this->blade('<x-definition-list><x-detail-row label="LRN" value="136512090001" /><x-detail-row label="Phone" /></x-definition-list>');
    $double = (string) $this->blade('<x-definition-list columns="2"><x-detail-row label="LRN" value="136512090001" /></x-definition-list>');

    expect($single)->toContain('divide-y')
        ->toContain('@md:w-44')
        ->toContain('136512090001')
        ->toMatch('/<dd[^>]*>\s*<span[^>]*>—<\/span>/')
        ->and($double)->toContain('@lg:grid-cols-2')
        ->not->toContain('@md:w-44');
});

it('shows an empty state with icon, copy and actions', function () {
    $html = (string) $this->blade(<<<'BLADE'
        <x-empty-state icon="school" title="No classrooms yet" description="Create your first classroom to invite learners." variant="dashed">
            <x-slot:actions><a href="/teacher/classrooms/create">New classroom</a></x-slot:actions>
        </x-empty-state>
        BLADE);

    expect($html)->toContain('data-icon="school"')
        ->toContain('No classrooms yet')
        ->toContain('Create your first classroom to invite learners.')
        ->toContain('border-dashed')
        ->toContain('<a href="/teacher/classrooms/create">New classroom</a>');
});

it('shows an illustration in the empty state when named', function () {
    $this->blade('<x-empty-state illustration="forum" title="No threads yet" />')
        ->assertSee('data-illustration="forum"', false);
});

it('draws a known illustration and falls back for unknown names', function () {
    $this->blade('<x-illustration name="trophy" label="Trophy" />')
        ->assertSee('data-illustration="trophy"', false)
        ->assertSee('role="img"', false);

    $this->blade('<x-illustration name="unicorn" />')
        ->assertSee('data-illustration="empty-plate"', false)
        ->assertSee('aria-hidden="true"', false);
});

it('paints the hero banner in the portal accent with an illustration', function () {
    $html = (string) $this->blade(<<<'BLADE'
        <x-hero-banner title="Good morning, Ms. Santos" eyebrow="Teacher Portal" description="3 quizzes are due this week." color="grape" heading-level="h2">
            <x-slot:actions><a href="/teacher/quizzes">Review quizzes</a></x-slot:actions>
        </x-hero-banner>
        BLADE);

    expect($html)->toContain('bg-grape-900')
        ->toContain('bg-grape-400/55')
        ->toMatch('/<h2[^>]*>Good morning, Ms\. Santos<\/h2>/')
        ->toContain('data-illustration="hero-produce"')
        ->toContain('<a href="/teacher/quizzes">Review quizzes</a>');
});

it('renders the logo wordmark or an accessible mark', function () {
    $this->blade('<x-logo />')->assertSeeText('Mind');

    $this->blade('<x-logo mark-only />')
        ->assertSee('aria-label="NutriMind"', false)
        ->assertDontSeeText('Mind');
});

it('renders a labelled divider', function () {
    $this->blade('<x-divider label="or" />')
        ->assertSee('role="separator"', false)
        ->assertSeeText('or');
});
