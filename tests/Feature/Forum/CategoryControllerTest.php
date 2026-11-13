<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\ForumCategory;
use App\Models\ForumThread;
use App\Models\User;
use App\Policies\ForumThreadPolicy;
use App\Services\RolePermissions;
use App\Services\Settings;

it('sends guests to the sign-in page', function () {
    $category = ForumCategory::factory()->create();

    $this->get(route('forum.categories.show', $category))->assertRedirectToRoute('login');
});

it('forbids learner accounts', function () {
    $category = ForumCategory::factory()->create();

    $this->actingAs(User::factory()->student()->create())
        ->get(route('forum.categories.show', $category))
        ->assertForbidden();
});

it('lists only the threads of the category', function (UserRole $role) {
    $category = ForumCategory::factory()->create(['name' => 'Healthy Baon Ideas', 'description' => 'Swap baon ideas.']);
    ForumThread::factory()->for($category, 'category')->create(['title' => 'Rice and adobo baon']);
    ForumThread::factory()->create(['title' => 'Thread in another category']);

    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('forum.categories.show', $category))
        ->assertOk()
        ->assertViewIs('forum.categories.show')
        ->assertSeeText('Healthy Baon Ideas')
        ->assertSeeText('Swap baon ideas.')
        ->assertSeeText('Rice and adobo baon')
        ->assertDontSeeText('Thread in another category');
})->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

it('binds the category by its slug', function () {
    $category = ForumCategory::factory()->create(['slug' => 'healthy-baon-ideas']);

    $this->actingAs(User::factory()->parent()->create())
        ->get('/forum/categories/healthy-baon-ideas')
        ->assertOk()
        ->assertViewHas('category', fn (ForumCategory $viewCategory): bool => $viewCategory->is($category));
});

it('returns 404 for an unknown slug', function () {
    $this->actingAs(User::factory()->parent()->create())
        ->get('/forum/categories/no-such-category')
        ->assertNotFound();
});

it('hides others’ pending and hidden threads from parents', function () {
    $category = ForumCategory::factory()->create();
    ForumThread::factory()->for($category, 'category')->pending()->create(['title' => 'Awaiting review']);
    ForumThread::factory()->for($category, 'category')->hidden()->create(['title' => 'Hidden by moderators']);

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.categories.show', $category))
        ->assertOk()
        ->assertDontSeeText('Awaiting review')
        ->assertDontSeeText('Hidden by moderators');
});

it('returns 404 for an inactive category to teachers and parents', function (UserRole $role) {
    $category = ForumCategory::factory()->inactive()->create();

    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('forum.categories.show', $category))
        ->assertNotFound();
})->with([UserRole::Teacher, UserRole::Parent]);

it('opens an inactive category to administrators with a notice', function () {
    $category = ForumCategory::factory()->inactive()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('forum.categories.show', $category))
        ->assertOk()
        ->assertSeeText('This category is inactive')
        ->assertSee(route('admin.forum-categories.edit', $category), false)
        ->assertDontSee(route('forum.threads.create', ['category' => $category->slug]), false);
});

it('links the new thread button to the category', function () {
    $category = ForumCategory::factory()->create(['slug' => 'healthy-baon-ideas']);

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.categories.show', $category))
        ->assertOk()
        ->assertSee(route('forum.threads.create', ['category' => 'healthy-baon-ideas']), false);
});

it('searches inside the category', function () {
    $category = ForumCategory::factory()->create();
    ForumThread::factory()->for($category, 'category')->create(['title' => 'Breakfast ideas']);
    ForumThread::factory()->for($category, 'category')->create(['title' => 'Water bottles']);

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.categories.show', [$category, 'search' => 'breakfast']))
        ->assertOk()
        ->assertSeeText('Breakfast ideas')
        ->assertDontSeeText('Water bottles');
});

it('invites the first thread in an empty category', function () {
    $category = ForumCategory::factory()->create(['name' => 'School Events']);

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.categories.show', $category))
        ->assertOk()
        ->assertSeeText('No threads in School Events yet')
        ->assertSeeText('Start a thread');
});

it('refuses teachers and parents while the forum is turned off', function () {
    $category = ForumCategory::factory()->create();
    app(Settings::class)->set('forum_enabled', false);

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.categories.show', $category))
        ->assertForbidden()
        ->assertSeeText(ForumThreadPolicy::FORUM_DISABLED_MESSAGE);
});

it('forbids a parent whose forum access was revoked', function () {
    $category = ForumCategory::factory()->create();
    app(RolePermissions::class)->revoke(UserRole::Parent, Permission::AccessForum);

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.categories.show', $category))
        ->assertForbidden();
});
