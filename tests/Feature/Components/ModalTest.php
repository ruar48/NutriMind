<?php

it('renders a named native dialog labelled by its title', function () {
    $html = (string) $this->blade('<x-modal name="new-category" title="New category" description="Group forum threads by topic.">Fields</x-modal>');

    expect($html)->toMatch('/<dialog\s+data-modal="new-category"/')
        ->toContain('aria-labelledby="modal-new-category-title"')
        ->toContain('id="modal-new-category-title"')
        ->toContain('aria-describedby="modal-new-category-description"')
        ->toContain('data-modal-close')
        ->toContain('Fields')
        ->not->toContain('<form');
});

it('wraps the body and footer in a form with method spoofing', function () {
    $html = (string) $this->blade(<<<'BLADE'
        <x-modal name="edit-category" title="Edit category" action="/admin/forum-categories/2" method="PUT" files>
            <input name="name">
            <x-slot:footer><button type="submit">Save</button></x-slot:footer>
        </x-modal>
        BLADE);

    expect($html)->toMatch('#<form method="POST" action="/admin/forum-categories/2"\s+enctype="multipart/form-data"\s+data-submit-once>#')
        ->toContain('name="_token"')
        ->toContain('name="_method" value="PUT"')
        ->toMatch('/<form.*<input name="name">.*<button type="submit">Save<\/button>.*<\/form>/s');
});

it('opens on page load when asked, for example after a validation error', function () {
    $this->blade('<x-modal name="new-category" title="New category" :open="true">Fields</x-modal>')
        ->assertSee('data-modal-autoopen', false);
});
