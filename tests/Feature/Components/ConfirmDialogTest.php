<?php

it('posts to the action with a spoofed DELETE method and a CSRF token', function () {
    $html = (string) $this->blade('<x-confirm-dialog action="/teacher/classrooms/4" title="Delete this classroom?" message="This cannot be undone." />');

    expect($html)->toContain('<form method="POST" action="/teacher/classrooms/4"')
        ->toContain('name="_method" value="DELETE"')
        ->toContain('name="_token"')
        ->toContain('data-submit-once');
});

it('spoofs PUT and PATCH methods', function (string $method) {
    $this->blade('<x-confirm-dialog action="/admin/users/7/status" :method="$method" />', ['method' => $method])
        ->assertSee('name="_method" value="'.$method.'"', false);
})->with(['PUT', 'PATCH']);

it('posts without spoofing for POST', function () {
    $view = $this->blade('<x-confirm-dialog action="/teacher/quizzes/3/duplicate" method="post" confirm="Duplicate" :danger="false" />');

    $view->assertSee('<form method="POST"', false)
        ->assertDontSee('name="_method"', false);
});

it('opens its own dialog from the trigger', function () {
    $html = (string) $this->blade('<x-confirm-dialog action="/forum/threads/9" title="Delete thread?" button="Delete thread" />');

    preg_match('/<dialog\s+id="([^"]+)"/', $html, $matches);

    expect($matches[1] ?? null)->toStartWith('confirm-')
        ->and($html)->toContain('data-confirm-open="'.$matches[1].'"')
        ->and($html)->toContain('aria-labelledby="'.$matches[1].'-title"')
        ->and($html)->toContain('role="alertdialog"');
});

it('shows the title, message, labels and extra body content', function () {
    $view = $this->blade(<<<'BLADE'
        <x-confirm-dialog action="/teacher/classrooms/4/students/12" title="Remove Ana from this class?" message="Her progress stays in the system." confirm="Remove learner" cancel="Keep" button="Remove">
            <p>Quiz attempts remain visible in reports.</p>
        </x-confirm-dialog>
        BLADE);

    $view->assertSeeText('Remove Ana from this class?')
        ->assertSeeText('Her progress stays in the system.')
        ->assertSeeText('Remove learner')
        ->assertSeeText('Keep')
        ->assertSeeText('Quiz attempts remain visible in reports.');
});

it('submits extra hidden fields', function () {
    $this->blade(<<<'BLADE'
        <x-confirm-dialog action="/admin/forum/reports/5" method="PUT" confirm="Hide content">
            <x-slot:fields><input type="hidden" name="resolution" value="content_hidden"></x-slot:fields>
        </x-confirm-dialog>
        BLADE)->assertSee('<input type="hidden" name="resolution" value="content_hidden">', false);
});

it('renders the trigger as a dropdown item for menus', function () {
    $view = $this->blade('<x-confirm-dialog action="/admin/subjects/2" button="Delete subject" button-variant="menu" />');

    $view->assertSee('data-dropdown-item', false)
        ->assertSee('data-dropdown-keep-open', false)
        ->assertSeeText('Delete subject');
});

it('uses friendly styling for a non-destructive confirmation', function () {
    $this->blade('<x-confirm-dialog action="/teacher/quizzes/3/status" method="PUT" confirm="Publish" :danger="false" />')
        ->assertSee('data-icon="circle-help"', false)
        ->assertDontSee('bg-berry-50 text-berry-600', false);
});
