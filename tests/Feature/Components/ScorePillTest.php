<?php

use App\Enums\PerformanceBand;

it('colors the score by its DepEd performance band', function (?float $score, PerformanceBand $band, string $toneClass) {
    $this->blade('<x-score-pill :value="$score" />', ['score' => $score])
        ->assertSee('data-band="'.$band->value.'"', false)
        ->assertSee('title="'.$band->label().'"', false)
        ->assertSee($toneClass, false);
})->with([
    'outstanding' => [95.0, PerformanceBand::Outstanding, 'bg-leaf-50 text-leaf-800'],
    'very satisfactory at the boundary' => [85.0, PerformanceBand::VerySatisfactory, 'bg-teal-50 text-teal-800'],
    'satisfactory' => [80.0, PerformanceBand::Satisfactory, 'bg-sky-50 text-sky-800'],
    'fairly satisfactory' => [75.0, PerformanceBand::FairlySatisfactory, 'bg-sun-50 text-sun-800'],
    'did not meet expectations' => [74.99, PerformanceBand::DidNotMeetExpectations, 'bg-berry-50 text-berry-800'],
    'no score yet' => [null, PerformanceBand::NoData, 'bg-ink-900/5 text-ink-500'],
]);

it('shows a dash when there is no score', function () {
    $this->blade('<x-score-pill :value="null" />')
        ->assertSeeText('—')
        ->assertSeeText('(No data)')
        ->assertDontSee('%', false);
});

it('formats scores with at most one decimal', function (float $score, string $expected) {
    $this->blade('<x-score-pill :value="$score" />', ['score' => $score])
        ->assertSeeText($expected);
})->with([
    'whole number' => [90.0, '90%'],
    'one decimal' => [86.5, '86.5%'],
    'rounded to one decimal' => [86.26, '86.3%'],
    'perfect score' => [100.0, '100%'],
]);

it('treats a numeric string as a score', function () {
    $this->blade('<x-score-pill value="88.50" />')
        ->assertSee('data-band="very_satisfactory"', false)
        ->assertSeeText('88.5%');
});

it('shows the band short label when asked', function () {
    $this->blade('<x-score-pill :value="91" show-band />')
        ->assertSeeText('· O');
});

it('announces the band label to screen readers', function () {
    $this->blade('<x-score-pill :value="70" />')
        ->assertSee('<span class="sr-only">(Did Not Meet Expectations)</span>', false);
});
