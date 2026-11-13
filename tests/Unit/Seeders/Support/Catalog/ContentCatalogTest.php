<?php

use App\Enums\ActivityType;
use App\Enums\Difficulty;
use App\Enums\MissionType;
use App\Enums\RewardType;
use Database\Seeders\Support\Catalog\ContentCatalog;
use Database\Seeders\Support\Catalog\SchoolCatalog;

it('gives every subject and grade two to four learning modules with unique competency codes', function () {
    $modules = collect(ContentCatalog::modules());

    expect($modules)->toHaveCount(24);
    expect($modules->pluck('code')->unique())->toHaveCount(24);

    foreach (array_keys(SchoolCatalog::subjects()) as $subject) {
        foreach ([5, 6] as $grade) {
            expect($modules->where('subject', $subject)->where('grade', $grade)->count())->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(4);
        }
    }
});

it('places the modules in quarters with the 3rd Quarter mostly still ahead', function () {
    $quarters = collect(ContentCatalog::modules())->countBy('quarter')->all();
    $unpublished = collect(ContentCatalog::modules())->where('published', false);

    expect($quarters)->toBe([1 => 10, 2 => 12, 3 => 2]);
    expect($unpublished->pluck('code')->all())->toBe(['H6N-IIIa-1']);
});

it('gives every module three to six activities and uses every activity type', function () {
    $activities = collect(ContentCatalog::modules())->flatMap(fn (array $module): array => $module['activities']);

    foreach (ContentCatalog::modules() as $module) {
        expect(count($module['activities']))->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(6);
    }

    expect($activities)->toHaveCount(99);
    expect($activities->pluck('type')->unique()->sort()->values()->all())
        ->toBe(collect(ActivityType::cases())->pluck('value')->sort()->values()->all());
    expect(array_keys(ContentCatalog::ACTIVITY_POINTS))->toEqualCanonicalizing(collect(ActivityType::cases())->pluck('value')->all());
});

it('links story missions to modules of the same subject and grade', function () {
    foreach (ContentCatalog::missions() as $mission) {
        if ($mission['module'] === null) {
            continue;
        }

        $module = ContentCatalog::moduleByCode($mission['module']);

        expect($module['subject'])->toBe($mission['subject']);
        expect($module['grade'])->toBe($mission['grade']);
    }
});

it('has about two dozen missions of every type and difficulty, some dated, retired or upcoming', function () {
    $missions = collect(ContentCatalog::missions());

    expect($missions)->toHaveCount(25);
    expect($missions->pluck('title')->unique())->toHaveCount(25);
    expect($missions->pluck('type')->unique()->sort()->values()->all())->toBe(collect(MissionType::cases())->pluck('value')->sort()->values()->all());
    expect($missions->pluck('difficulty')->unique()->sort()->values()->all())->toBe(collect(Difficulty::cases())->pluck('value')->sort()->values()->all());
    expect($missions->where('active', false)->pluck('title')->all())->toBe(['Nutrition Month 2026 Challenge', 'Sports Fest Fitness Quest']);

    foreach ($missions->whereNotNull('starts_on') as $mission) {
        expect($mission['ends_on'])->toBeGreaterThan($mission['starts_on']);
    }
});

it('awards rewards by rules that point at existing missions and subjects', function () {
    $rewards = collect(ContentCatalog::rewards());
    $missionTitles = array_column(ContentCatalog::missions(), 'title');

    expect($rewards)->toHaveCount(14);
    expect($rewards->pluck('name')->unique())->toHaveCount(14);
    expect($rewards->pluck('type')->unique()->sort()->values()->all())->toBe(collect(RewardType::cases())->pluck('value')->sort()->values()->all());

    foreach ($rewards as $reward) {
        expect($reward['rule'][0])->toBeIn(['activities', 'points', 'module', 'missions', 'quizzes_passed', 'perfect', 'mission', 'subject_activities']);
        expect($reward['points_required'])->toBe($reward['rule'][0] === 'points' ? $reward['rule'][1] : null);

        match ($reward['rule'][0]) {
            'mission' => expect($missionTitles)->toContain($reward['rule'][1]),
            'subject_activities' => expect(SchoolCatalog::subjects())->toHaveKey($reward['rule'][1]),
            default => expect($reward['rule'][1])->toBeInt()->toBeGreaterThan(0),
        };
    }
});

it('rejects an unknown competency code', function () {
    ContentCatalog::moduleByCode('X0-0');
})->throws(InvalidArgumentException::class, 'Unknown demo module [X0-0].');
