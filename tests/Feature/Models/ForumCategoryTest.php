<?php

use App\Models\ForumCategory;
use App\Models\ForumThread;

it('resolves route bindings by slug', function () {
    $category = ForumCategory::factory()->create(['slug' => 'healthy-recipes']);

    $boundCategory = (new ForumCategory)->resolveRouteBinding('healthy-recipes');

    expect($category->getRouteKey())->toBe('healthy-recipes');
    expect($boundCategory?->is($category))->toBeTrue();
});

it('filters active categories', function () {
    $activeCategory = ForumCategory::factory()->create();
    ForumCategory::factory()->inactive()->create();

    expect(ForumCategory::query()->active()->pluck('id')->all())->toBe([$activeCategory->id]);
});

it('reaches the threads posted in it', function () {
    $category = ForumCategory::factory()->create();
    $thread = ForumThread::factory()->for($category, 'category')->create();

    expect($category->threads()->sole()->is($thread))->toBeTrue();
});
