<?php

it('renders nothing without credentials', function (?array $credentials) {
    $html = (string) $this->blade('<x-credentials-alert :credentials="$credentials" />', ['credentials' => $credentials]);

    expect(trim($html))->toBe('');
})->with([
    'empty list' => [[]],
    'missing session value' => [null],
]);

it('lists every account in a warning alert with a copy button per row', function () {
    $credentials = [
        ['name' => 'Ana Santos', 'username' => 'ana.santos', 'password' => 'tulip-42-mango'],
        ['name' => 'Ben Cruz', 'username' => 'ben.cruz', 'password' => 'river-17-guava'],
        ['name' => 'Carla Reyes', 'username' => 'carla.reyes', 'password' => 'pearl-08-melon'],
    ];

    $view = $this->blade('<x-credentials-alert :credentials="$credentials" />', ['credentials' => $credentials]);

    $view->assertSee('role="alert"', false)
        ->assertSee('bg-sun-50', false)
        ->assertSeeText('Save these sign-in details')
        ->assertSeeText('Passwords are shown only once — copy or download them now.')
        ->assertSee('<caption class="sr-only">Sign-in details for 3 accounts</caption>', false)
        ->assertSeeTextInOrder(['Name', 'Username', 'Password'])
        ->assertSeeTextInOrder(['Ana Santos', 'ana.santos', 'tulip-42-mango', 'Ben Cruz', 'ben.cruz', 'river-17-guava', 'Carla Reyes', 'carla.reyes', 'pearl-08-melon'])
        ->assertSee('data-copy="ana.santos / tulip-42-mango"', false)
        ->assertSee('data-copy="ben.cruz / river-17-guava"', false)
        ->assertSee('data-copy="carla.reyes / pearl-08-melon"', false)
        ->assertSee('aria-label="Copy sign-in details for Ben Cruz"', false);
    expect(substr_count((string) $view, 'data-credential-row'))->toBe(3);
});

it('shows usernames and passwords in a monospace font', function () {
    $credentials = [['name' => 'Ana Santos', 'username' => 'ana.santos', 'password' => 'tulip-42-mango']];

    $html = (string) $this->blade('<x-credentials-alert :credentials="$credentials" />', ['credentials' => $credentials]);

    expect($html)
        ->toMatch('#<span class="font-mono[^"]*">ana\.santos</span>#')
        ->toMatch('#<span class="font-mono[^"]*">tulip-42-mango</span>#');
});

it('copies every account on its own line with Copy all', function () {
    $credentials = [
        ['name' => 'Ana Santos', 'username' => 'ana.santos', 'password' => 'tulip-42-mango'],
        ['name' => 'Ben Cruz', 'username' => 'ben.cruz', 'password' => 'river-17-guava'],
    ];

    $html = (string) $this->blade('<x-credentials-alert :credentials="$credentials" />', ['credentials' => $credentials]);

    expect($html)->toMatch("#data-copy=\"Ana Santos — ana\\.santos / tulip-42-mango\nBen Cruz — ben\\.cruz / river-17-guava\"[^>]*\\sdata-copy-all[^>]*>.*?Copy all#s");
});

it('offers a CSV download when a download URL is given', function () {
    $credentials = [['name' => 'Ana Santos', 'username' => 'ana.santos', 'password' => 'tulip-42-mango']];

    $html = (string) $this->blade('<x-credentials-alert :credentials="$credentials" download-url="https://nutrimind.test/admin/students/import/credentials" />', ['credentials' => $credentials]);

    expect($html)->toMatch('#<a href="https://nutrimind\.test/admin/students/import/credentials"[^>]*>.*?data-icon="download".*?Download CSV\s*</a>#s');
});

it('leaves out the CSV download without a download URL', function () {
    $credentials = [['name' => 'Ana Santos', 'username' => 'ana.santos', 'password' => 'tulip-42-mango']];

    $this->blade('<x-credentials-alert :credentials="$credentials" :download-url="null" />', ['credentials' => $credentials])
        ->assertDontSeeText('Download CSV');
});

it('can be dismissed', function () {
    $credentials = [['name' => 'Ana Santos', 'username' => 'ana.santos', 'password' => 'tulip-42-mango']];

    $html = (string) $this->blade('<x-credentials-alert :credentials="$credentials" />', ['credentials' => $credentials]);

    expect($html)
        ->toContain('data-dismissible')
        ->toMatch('#<button type="button" data-dismiss data-js-only[^>]*>.*?<span class="sr-only">Dismiss</span>#s');
});

it('uses a custom title', function () {
    $credentials = [['name' => 'Ana Santos', 'username' => 'ana.santos', 'password' => 'tulip-42-mango']];

    $this->blade('<x-credentials-alert :credentials="$credentials" title="New password for Ana Santos" />', ['credentials' => $credentials])
        ->assertSeeText('New password for Ana Santos')
        ->assertDontSeeText('Save these sign-in details');
});

it('merges extra attributes into the alert', function () {
    $credentials = [['name' => 'Ana Santos', 'username' => 'ana.santos', 'password' => 'tulip-42-mango']];

    $html = (string) $this->blade('<x-credentials-alert :credentials="$credentials" class="mb-6" id="new-credentials" />', ['credentials' => $credentials]);

    expect($html)->toMatch('#<div\s+role="alert"\s+class="[^"]*\bmb-6\b[^"]*"[^>]*\sid="new-credentials"#');
});

it('escapes HTML in names, usernames and passwords', function () {
    $credentials = [
        ['name' => '<img src=x onerror=alert("name")>', 'username' => '<b>user</b>', 'password' => '"><script>alert(1)</script>'],
    ];

    $html = (string) $this->blade('<x-credentials-alert :credentials="$credentials" />', ['credentials' => $credentials]);

    expect($html)
        ->toContain('&lt;img src=x onerror=alert(&quot;name&quot;)&gt;')
        ->toContain('aria-label="Copy sign-in details for &lt;img src=x onerror=alert(&quot;name&quot;)&gt;"')
        ->toContain('data-copy="&lt;b&gt;user&lt;/b&gt; / &quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"')
        ->toContain('data-copy="&lt;img src=x onerror=alert(&quot;name&quot;)&gt; — &lt;b&gt;user&lt;/b&gt; / &quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"')
        ->not->toContain('<img src=x')
        ->not->toContain('<b>user')
        ->not->toContain('<script>alert');
});
