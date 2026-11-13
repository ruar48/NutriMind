<?php

it('fills the bar to the share of the maximum', function () {
    $view = $this->blade('<x-progress :value="18" :max="24" label="Learners finished" show-value />');

    $view->assertSee('style="width: 75%"', false)
        ->assertSee('role="progressbar"', false)
        ->assertSee('aria-valuenow="18"', false)
        ->assertSee('aria-valuemax="24"', false)
        ->assertSee('aria-valuetext="75%"', false)
        ->assertSee('aria-label="Learners finished"', false)
        ->assertSeeText('75%');
});

it('clamps values above the maximum to a full bar', function () {
    $this->blade('<x-progress :value="140" />')
        ->assertSee('style="width: 100%"', false)
        ->assertSee('aria-valuenow="100"', false);
});

it('renders an empty bar when the maximum is zero', function () {
    $this->blade('<x-progress :value="3" :max="0" />')
        ->assertSee('style="width: 0%"', false);
});

it('uses a lighter step of the same ramp for the track', function (string $color, string $track, string $fill) {
    $this->blade('<x-progress :value="50" :color="$color" />', ['color' => $color])
        ->assertSee($track, false)
        ->assertSee($fill, false);
})->with([
    'leaf' => ['leaf', 'bg-leaf-100', 'bg-leaf-500'],
    'berry' => ['berry', 'bg-berry-100', 'bg-berry-500'],
    'sky' => ['sky', 'bg-sky-100', 'bg-sky-500'],
]);

it('shows custom value text', function () {
    $this->blade('<x-progress :value="3" :max="5" label="Missions" show-value value-text="3 of 5" />')
        ->assertSeeText('3 of 5')
        ->assertSee('aria-valuetext="3 of 5"', false);
});

it('adds stripes when asked', function () {
    $this->blade('<x-progress :value="40" striped />')
        ->assertSee('bg-size-[14px_14px]', false);
});

it('draws a progress ring with the matching dash offset', function () {
    $view = $this->blade('<x-progress-ring :value="25" size="md" label="Completion" />');

    $view->assertSee('aria-label="Completion: 25%"', false)
        ->assertSee('stroke-dasharray="204.204"', false)
        ->assertSee('stroke-dashoffset="153.153"', false)
        ->assertSeeText('25%');
});

it('omits the ring fill at zero percent', function () {
    $this->blade('<x-progress-ring :value="0" />')
        ->assertDontSee('data-ring-fill', false)
        ->assertSeeText('0%');
});
