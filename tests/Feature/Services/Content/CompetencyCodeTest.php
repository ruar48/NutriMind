<?php

use App\Services\Content\CompetencyCode;

it('normalizes a submitted code', function (mixed $input, mixed $expected) {
    expect(CompetencyCode::normalize($input))->toBe($expected);
})->with([
    'lowercase' => ['h5n-ia-1', 'H5N-IA-1'],
    'surrounding and repeated spaces' => ['  h5n  ia-1 ', 'H5N IA-1'],
    'blank' => ['   ', null],
    'empty' => ['', null],
    'null' => [null, null],
    'not a string' => [['H5N'], ['H5N']],
]);

it('compares codes without regard to case or surrounding spaces', function (?string $first, ?string $second, bool $expected) {
    expect(CompetencyCode::matches($first, $second))->toBe($expected);
})->with([
    'same code' => ['H5N-IA-1', 'H5N-IA-1', true],
    'different case' => ['H5N-IIa-1', 'h5n-iia-1', true],
    'surrounding spaces' => [' H5N-IA-1', 'H5N-IA-1 ', true],
    'different code' => ['H5N-IA-1', 'H5N-IA-2', false],
    'blank first code' => [null, null, false],
    'blank second code' => ['H5N-IA-1', '', false],
]);

it('builds the comparison key of a code', function () {
    expect(CompetencyCode::key(' s5lt-iia-1 '))->toBe('S5LT-IIA-1')
        ->and(CompetencyCode::key(''))->toBeNull()
        ->and(CompetencyCode::key(null))->toBeNull();
});
