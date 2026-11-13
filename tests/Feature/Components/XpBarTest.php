<?php

it('fills the bar with the points earned toward the next level', function () {
    $view = $this->blade('<x-xp-bar :level="7" :current="320" :needed="500" />');

    $view->assertSee('style="width: 64%"', false)
        ->assertSee('aria-valuenow="320"', false)
        ->assertSee('aria-valuemax="500"', false)
        ->assertSee('aria-valuetext="320 of 500 XP toward level 8"', false)
        ->assertSeeText('Level 7')
        ->assertSeeText('320 / 500 XP')
        ->assertSeeText('180 XP to level 8');
});

it('reads the level calculator progress array', function () {
    $view = $this->blade('<x-xp-bar :progress="$progress" />', [
        'progress' => ['level' => 3, 'into' => 125, 'needed' => 500, 'percent' => 25.0],
    ]);

    $view->assertSee('style="width: 25%"', false)
        ->assertSeeText('Level 3')
        ->assertSeeText('125 / 500 XP');
});

it('shows a full bar and no next level at the maximum level', function () {
    $view = $this->blade('<x-xp-bar :level="50" :current="40" :needed="500" :max-level="50" />');

    $view->assertSee('style="width: 100%"', false)
        ->assertSee('aria-valuetext="Maximum level reached"', false)
        ->assertSeeText('Max level')
        ->assertDontSeeText('to level 51');
});

it('uses the configured maximum level by default', function () {
    config(['nutrimind.settings.max_level' => 10]);

    $this->blade('<x-xp-bar :level="10" :current="0" :needed="500" />')
        ->assertSeeText('Max level');
});

it('hides the labels when asked', function () {
    $view = $this->blade('<x-xp-bar :level="2" :current="50" :needed="500" :show-labels="false" />');

    $view->assertDontSeeText('Level 2')
        ->assertSee('role="progressbar"', false);
});
