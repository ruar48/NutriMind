<?php

use App\Enums\PerformanceBand;

it('maps a percentage score to its DepEd descriptor band', function (?float $percentage, PerformanceBand $band) {
    expect(PerformanceBand::fromScore($percentage))->toBe($band);
})->with([
    '0' => [0.0, PerformanceBand::DidNotMeetExpectations],
    '74.99' => [74.99, PerformanceBand::DidNotMeetExpectations],
    '75' => [75.0, PerformanceBand::FairlySatisfactory],
    '79.99' => [79.99, PerformanceBand::FairlySatisfactory],
    '80' => [80.0, PerformanceBand::Satisfactory],
    '84.99' => [84.99, PerformanceBand::Satisfactory],
    '85' => [85.0, PerformanceBand::VerySatisfactory],
    '89.99' => [89.99, PerformanceBand::VerySatisfactory],
    '90' => [90.0, PerformanceBand::Outstanding],
    '100' => [100.0, PerformanceBand::Outstanding],
    'no score yet' => [null, PerformanceBand::NoData],
]);

it('labels each band with its descriptor, abbreviation, color and icon', function (PerformanceBand $band, array $descriptors) {
    expect([
        'label' => $band->label(),
        'short' => $band->shortLabel(),
        'color' => $band->color(),
        'icon' => $band->icon(),
    ])->toBe($descriptors);
})->with([
    'outstanding' => [PerformanceBand::Outstanding, ['label' => 'Outstanding', 'short' => 'O', 'color' => 'leaf', 'icon' => 'crown']],
    'very satisfactory' => [PerformanceBand::VerySatisfactory, ['label' => 'Very Satisfactory', 'short' => 'VS', 'color' => 'teal', 'icon' => 'star']],
    'satisfactory' => [PerformanceBand::Satisfactory, ['label' => 'Satisfactory', 'short' => 'S', 'color' => 'sky', 'icon' => 'badge-check']],
    'fairly satisfactory' => [PerformanceBand::FairlySatisfactory, ['label' => 'Fairly Satisfactory', 'short' => 'FS', 'color' => 'sun', 'icon' => 'trending-up']],
    'did not meet expectations' => [PerformanceBand::DidNotMeetExpectations, ['label' => 'Did Not Meet Expectations', 'short' => 'DNME', 'color' => 'berry', 'icon' => 'triangle-alert']],
    'no data' => [PerformanceBand::NoData, ['label' => 'No data', 'short' => '—', 'color' => 'slate', 'icon' => 'minus']],
]);

it('flags only learners who did not meet expectations as needing attention', function (PerformanceBand $band, bool $needsAttention) {
    expect($band->needsAttention())->toBe($needsAttention);
})->with([
    'outstanding' => [PerformanceBand::Outstanding, false],
    'very satisfactory' => [PerformanceBand::VerySatisfactory, false],
    'satisfactory' => [PerformanceBand::Satisfactory, false],
    'fairly satisfactory' => [PerformanceBand::FairlySatisfactory, false],
    'did not meet expectations' => [PerformanceBand::DidNotMeetExpectations, true],
    'no data' => [PerformanceBand::NoData, false],
]);
