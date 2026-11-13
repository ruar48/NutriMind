<?php

use Database\Seeders\Support\Catalog\ContentCatalog;
use Database\Seeders\Support\Catalog\LessonPlanCatalog;
use Database\Seeders\Support\Catalog\PeopleCatalog;

it('writes lesson plans only for subjects the teacher is assigned', function () {
    $teachers = PeopleCatalog::teachers();

    foreach (LessonPlanCatalog::plans() as $plan) {
        expect($teachers[$plan['teacher']]['subjects'])->toContain($plan['subject']);
        expect($plan['week'])->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(12);
        expect($plan['objectives'])->toHaveCount(3);
    }

    expect(LessonPlanCatalog::plans())->toHaveCount(40);
});

it('links plans to modules of the same subject and grade and copies their competency code', function () {
    foreach (LessonPlanCatalog::plans() as $plan) {
        if ($plan['module'] === null) {
            continue;
        }

        $module = ContentCatalog::moduleByCode($plan['module']);

        expect([$module['subject'], $module['grade'], $plan['code']])->toBe([$plan['subject'], $plan['grade'], $module['code']]);
    }
});

it('leaves some plans unaligned to show every coverage status', function () {
    $plans = collect(LessonPlanCatalog::plans());
    $codes = array_column(ContentCatalog::modules(), 'code');

    $notCovered = $plans->filter(fn (array $plan): bool => $plan['code'] !== null && ! in_array($plan['code'], $codes, true));
    $unmapped = $plans->whereNull('code');
    $unpublishedModule = $plans->where('module', 'H6N-IIIa-1');

    expect($notCovered->pluck('code')->all())->toBe(['S5LT-IIc-3', 'H6N-IIc-4']);
    expect($notCovered->every(fn (array $plan): bool => $plan['competency'] !== null && ! $plan['align']))->toBeTrue();
    expect($unmapped)->toHaveCount(2);
    expect($unpublishedModule)->toHaveCount(2);
    expect($unpublishedModule->every(fn (array $plan): bool => ! $plan['align']))->toBeTrue();
    expect($plans->where('align', true)->count())->toBe(30);
});

it('gives every teacher distinct plan titles', function () {
    $titles = array_map(fn (array $plan): string => $plan['teacher'].'|'.$plan['title'], LessonPlanCatalog::plans());

    expect(array_unique($titles))->toHaveCount(count($titles));
});
