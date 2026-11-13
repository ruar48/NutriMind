<?php

use Database\Seeders\Support\Catalog\SchoolCatalog;

it('runs the active school year from June 2026 to April 2027 with the 2nd Quarter around mid-November', function () {
    $active = collect(SchoolCatalog::schoolYears())->firstWhere('is_active', true);

    expect($active['name'])->toBe('2026–2027');
    expect(substr($active['starts_on'], 0, 7))->toBe('2026-06');
    expect(substr($active['ends_on'], 0, 7))->toBe('2027-04');
    expect($active['quarters'][1][0])->toBeLessThanOrEqual('2026-11-13');
    expect($active['quarters'][1][1])->toBeGreaterThanOrEqual('2026-11-13');
});

it('splits every school year into four consecutive quarters inside its dates', function (array $schoolYear) {
    expect($schoolYear['quarters'])->toHaveCount(4);
    expect($schoolYear['quarters'][0][0])->toBe($schoolYear['starts_on']);
    expect($schoolYear['quarters'][3][1])->toBe($schoolYear['ends_on']);

    foreach ($schoolYear['quarters'] as $index => [$startsOn, $endsOn]) {
        expect($startsOn)->toBeLessThan($endsOn);

        if ($index > 0) {
            expect($startsOn)->toBeGreaterThan($schoolYear['quarters'][$index - 1][1]);
        }
    }
})->with(fn (): array => array_map(fn (array $schoolYear): array => [$schoolYear], array_column(SchoolCatalog::schoolYears(), null, 'name')));

it('keeps only one school year active', function () {
    expect(collect(SchoolCatalog::schoolYears())->where('is_active', true))->toHaveCount(1);
    expect(count(SchoolCatalog::QUARTER_NAMES))->toBe(4);
});

it('describes a plausible Philippine public school', function () {
    $profile = SchoolCatalog::profile();

    expect($profile['school_name'])->toBe('Mabini Elementary School');
    expect($profile['school_id'])->toMatch('/^\d{6}$/');
    expect($profile['school_address'])->toContain('Metro Manila');
    expect(mb_strlen($profile['announcement']))->toBeLessThanOrEqual(500);
});

it('colors and labels the four subjects, with EPP – Home Economics coded EPP-HE', function () {
    $subjects = SchoolCatalog::subjects();
    $palette = ['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate'];
    $icons = require dirname(__DIR__, 4).'/Fixtures/icon-names.php';

    expect(array_keys($subjects))->toBe(['HEALTH', 'SCIENCE', 'EPP-HE', 'PE']);
    expect($subjects['EPP-HE']['name'])->toBe('EPP – Home Economics');

    foreach ($subjects as $code => $subject) {
        expect($code)->toMatch('/^[A-Z0-9-]{1,20}$/');
        expect($palette)->toContain($subject['color']);
        expect($icons)->toContain($subject['icon']);
    }
});
