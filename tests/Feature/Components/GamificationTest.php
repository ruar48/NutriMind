<?php

use App\Models\Reward;
use Illuminate\Support\Carbon;

it('renders a stat card with its value, icon tone, trend and sparkline', function () {
    $html = (string) $this->blade('<x-stat-card label="Average score" value="86.4%" icon="target" color="sky" :trend="2.5" trend-format="points" trend-label="vs last week" :sparkline="[80, 82, 86.4]" :count-up="86.4" :count-decimals="1" count-suffix="%" />');

    expect($html)->toContain('Average score')
        ->toContain('86.4%')
        ->toContain('bg-sky-50 text-sky-700')
        ->toContain('data-count-up="86.4"')
        ->toContain('data-count-suffix="%"')
        ->toContain('data-direction="up"')
        ->toContain('data-chart="sparkline"');
});

it('omits the sparkline and trend of a stat card without them', function () {
    $html = (string) $this->blade('<x-stat-card label="Learners" value="128" />');

    expect($html)->not->toContain('data-chart=')
        ->not->toContain('data-direction')
        ->not->toContain('data-count-up');
});

it('renders a reward from its model with the award date', function () {
    $reward = Reward::factory()->make(['name' => 'Veggie Explorer', 'icon' => 'carrot', 'color' => 'carrot', 'description' => 'Finish five vegetable missions.']);

    $html = (string) $this->blade('<x-reward-badge :reward="$reward" :awarded-at="$at" />', ['reward' => $reward, 'at' => Carbon::parse('2026-10-03')]);

    expect($html)->toContain('Veggie Explorer')
        ->toContain('data-icon="carrot"')
        ->toContain('fill-carrot-400')
        ->toContain('title="Finish five vegetable missions."')
        ->toContain('Oct 3, 2026')
        ->toContain('data-earned="true"');
});

it('greys out a locked reward', function () {
    $html = (string) $this->blade('<x-reward-badge name="Water Champion" icon="glass-water" color="sky" :earned="false" />');

    expect($html)->toContain('grayscale')
        ->toContain('data-icon="lock"')
        ->toContain('Locked')
        ->toContain('data-earned="false"')
        ->not->toContain('fill-sky-400');
});

it('lights the streak flame only for an active streak', function () {
    $this->blade('<x-streak :days="5" />')
        ->assertSee('animate-flicker', false)
        ->assertSeeText('5')
        ->assertSeeText('days');

    $this->blade('<x-streak :days="0" />')
        ->assertDontSee('animate-flicker', false);

    $this->blade('<x-streak :days="1" />')->assertSeeText('day');
});

it('fills the earned stars and clamps the rating', function () {
    $html = (string) $this->blade('<x-star-rating :value="5" :max="3" />');

    expect($html)->toContain('aria-label="3 of 3 stars"')
        ->and(substr_count($html, 'fill-sun-400'))->toBe(3);

    $this->blade('<x-star-rating :value="1" />')->assertSee('aria-label="1 of 3 stars"', false);
});

it('puts the top three of the leaderboard on a podium', function () {
    $entries = collect(['Ana', 'Ben', 'Cora', 'Dan'])
        ->map(fn (string $name, int $index) => ['name' => $name, 'points' => 1000 - $index * 100, 'level' => 3])
        ->all();

    $html = (string) $this->blade('<x-leaderboard :entries="$entries" />', ['entries' => $entries]);

    expect($html)->toContain('aria-label="Top three"')
        ->toMatch('/Ben.*Ana.*Cora/s')
        ->toContain('start="4"')
        ->toContain('Dan')
        ->toContain('700');
});

it('lists a short leaderboard without a podium and shows an empty state', function () {
    $short = (string) $this->blade('<x-leaderboard :entries="[[\'name\' => \'Ana\', \'points\' => 50]]" />');
    $empty = (string) $this->blade('<x-leaderboard :entries="[]" />');

    expect($short)->not->toContain('aria-label="Top three"')
        ->toContain('Ana')
        ->and($empty)->toContain('Leaderboard is empty');
});

it('shows a timeline entry with a relative time and its exact datetime', function () {
    $this->travelTo(Carbon::parse('2026-11-13 10:00:00'));

    $html = (string) $this->blade('<x-timeline><x-timeline.item title="Ana passed Healthy Snacks" icon="circle-check" color="leaf" :time="$time">Scored 92%</x-timeline.item></x-timeline>', [
        'time' => Carbon::parse('2026-11-13 07:00:00'),
    ]);

    expect($html)->toContain('<ol')
        ->toContain('Ana passed Healthy Snacks')
        ->toContain('3 hours ago')
        ->toContain('title="Nov 13, 2026 7:00 AM"')
        ->toContain('bg-leaf-100 text-leaf-700')
        ->toContain('Scored 92%');
});

it('renders a subject chip from the model fields', function () {
    $subject = new class
    {
        public string $name = 'Health';

        public string $color = 'berry';

        public string $icon = 'heart-pulse';
    };

    $html = (string) $this->blade('<x-subject-chip :subject="$subject" href="/teacher/subjects" />', ['subject' => $subject]);

    expect($html)->toContain('Health')
        ->toContain('bg-berry-50 text-berry-800')
        ->toContain('data-icon="heart-pulse"')
        ->toContain('href="/teacher/subjects"');
});

it('falls back to the leaf color for an unknown subject color', function () {
    $this->blade('<x-subject-chip name="Science" color="neon" />')
        ->assertSee('bg-leaf-50 text-leaf-800', false);
});
