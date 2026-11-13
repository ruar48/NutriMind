<?php

use Database\Seeders\Support\Catalog\ForumCatalog;
use Database\Seeders\Support\Catalog\PeopleCatalog;

it('has the five forum categories', function () {
    expect(array_column(ForumCatalog::categories(), 'name'))->toBe([
        'School Announcements', 'General Discussion', 'Healthy Meals at Home', 'Homework & Learning Help', 'Healthy Baon Ideas',
    ]);
});

it('has about 25 threads and 120 replies with one pinned, one locked, one hidden and two pending threads', function () {
    $threads = collect(ForumCatalog::threads());

    expect($threads)->toHaveCount(27);
    expect($threads->sum(fn (array $thread): int => count($thread['replies'])))->toBe(122);
    expect($threads->where('pinned', true)->keys()->all())->toBe(['guidelines']);
    expect($threads->where('locked', true)->keys()->all())->toBe(['nutrition-month']);
    expect($threads->where('status', 'hidden')->keys()->all())->toBe(['spam']);
    expect($threads->where('status', 'pending')->keys()->all())->toBe(['banana-pancakes', 'instant-noodles']);
    expect($threads->whereIn('status', ['hidden', 'pending'])->every(fn (array $thread): bool => $thread['replies'] === []))->toBeTrue();
});

it('keeps thread titles and posts within the forum limits', function () {
    foreach (ForumCatalog::threads() as $thread) {
        expect(ForumCatalog::categories())->toHaveKey($thread['category']);
        expect(mb_strlen($thread['title']))->toBeGreaterThanOrEqual(5)->toBeLessThanOrEqual(150);
        expect(mb_strlen($thread['body']))->toBeGreaterThanOrEqual(10)->toBeLessThanOrEqual(5000);

        foreach ($thread['replies'] as $reply) {
            expect(mb_strlen($reply['body']))->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(3000);
        }
    }
});

it('names only known authors', function () {
    $authors = ['admin', 'demo-parent', 'parent', ...array_map(fn (string $key): string => 'teacher:'.$key, array_keys(PeopleCatalog::teachers()))];

    foreach (ForumCatalog::threads() as $thread) {
        expect($authors)->toContain($thread['author']);

        foreach ($thread['replies'] as $reply) {
            expect([...$authors, 'author'])->toContain($reply['author']);
        }
    }
});

it('lists replies in the order they were posted', function () {
    foreach (ForumCatalog::threads() as $thread) {
        $hours = array_column($thread['replies'], 'after');
        $sorted = $hours;
        sort($sorted);

        expect($hours)->toBe($sorted);
    }
});

it('reports existing threads and replies, three of them still waiting for a moderator', function () {
    $threads = ForumCatalog::threads();
    $reports = collect(ForumCatalog::reports());

    foreach ($reports as $report) {
        expect($threads)->toHaveKey($report['thread']);

        if ($report['reply'] !== null) {
            expect(array_column($threads[$report['thread']]['replies'], 'key'))->toContain($report['reply']);
        }

        if ($report['resolution'] !== null) {
            expect($report['resolved_after'])->toBeGreaterThan($report['after']);
        }
    }

    expect($reports->whereNull('resolution'))->toHaveCount(3);
    expect($reports->whereNull('resolution')->every(fn (array $report): bool => $report['reply'] !== null))->toBeTrue();
});
