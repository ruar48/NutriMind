<?php

use App\Enums\UserRole;
use App\Models\ForumCategory;
use App\Models\ForumThread;
use App\Models\User;
use App\Services\Forum\ForumCategories;

describe('index', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.forum-categories.index'))->assertRedirectToRoute('login');
    });

    it('forbids every role but administrators', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.forum-categories.index'))
            ->assertForbidden();
    })->with([UserRole::Teacher, UserRole::Parent, UserRole::Student]);

    it('lists every category in display order with its thread count', function () {
        $second = ForumCategory::factory()->create(['name' => 'Healthy Recipes', 'sort_order' => 2]);
        ForumCategory::factory()->inactive()->create(['name' => 'Archived Topics', 'sort_order' => 1]);
        ForumThread::factory()->for($second, 'category')->count(2)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum-categories.index'))
            ->assertOk()
            ->assertViewIs('admin.forum-categories.index')
            ->assertSeeTextInOrder(['Archived Topics', 'Inactive', 'Healthy Recipes', 'Active', '2 threads']);
    });

    it('filters by status', function (string $status, string $shown, string $hidden) {
        ForumCategory::factory()->create(['name' => 'Healthy Recipes']);
        ForumCategory::factory()->inactive()->create(['name' => 'Archived Topics']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum-categories.index', ['status' => $status]))
            ->assertOk()
            ->assertSeeText($shown)
            ->assertDontSeeText($hidden);
    })->with([
        'active' => ['active', 'Healthy Recipes', 'Archived Topics'],
        'inactive' => ['inactive', 'Archived Topics', 'Healthy Recipes'],
    ]);

    it('searches names and descriptions', function (string $search) {
        ForumCategory::factory()->create(['name' => 'Healthy Recipes', 'description' => 'Budget-friendly family meals.']);
        ForumCategory::factory()->create(['name' => 'Game Help', 'description' => 'Questions about missions.']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum-categories.index', ['search' => $search]))
            ->assertOk()
            ->assertSeeText('Healthy Recipes')
            ->assertDontSeeText('Game Help');
    })->with([
        'name' => 'recipes',
        'description' => 'budget',
    ]);

    it('offers to delete only categories without threads', function () {
        ForumCategory::factory()->create(['name' => 'Empty Topic']);
        $used = ForumCategory::factory()->create(['name' => 'Busy Topic']);
        ForumThread::factory()->for($used, 'category')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum-categories.index'))
            ->assertOk()
            ->assertSeeText('Delete “Empty Topic”?')
            ->assertDontSeeText('Delete “Busy Topic”?')
            ->assertSeeText('Has threads');
    });

    it('invites the first category', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum-categories.index'))
            ->assertOk()
            ->assertSeeText('No categories yet')
            ->assertSee(route('admin.forum-categories.create'), false);
    });

    it('offers to clear the filters when nothing matches', function () {
        ForumCategory::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum-categories.index', ['search' => 'nothing matches this']))
            ->assertOk()
            ->assertSeeText('No categories match your filters');
    });

    it('escapes category names and descriptions', function () {
        ForumCategory::factory()->create(['name' => '<script>alert("c")</script>', 'description' => '<img src=x onerror=alert(1)>']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum-categories.index'))
            ->assertOk()
            ->assertDontSee('<script>alert("c")</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    });
});

describe('create', function () {
    it('places a new category after the existing ones', function () {
        ForumCategory::factory()->create(['sort_order' => 7]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum-categories.create'))
            ->assertOk()
            ->assertViewIs('admin.forum-categories.create')
            ->assertSee('value="8"', false)
            ->assertSee('value="messages-square" class="sr-only" checked', false);
    });

    it('forbids teachers', function () {
        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('admin.forum-categories.create'))
            ->assertForbidden();
    });
});

describe('store', function () {
    it('creates a category with a slug made from its name', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.forum-categories.store'), [
                'name' => 'Healthy Recipes & Tips',
                'description' => 'Budget-friendly family meals.',
                'color' => 'carrot',
                'icon' => 'cooking-pot',
                'sort_order' => 3,
                'is_active' => '1',
            ])
            ->assertRedirectToRoute('admin.forum-categories.index')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', '“Healthy Recipes & Tips” was created.');

        expect(ForumCategory::query()->sole())
            ->name->toBe('Healthy Recipes & Tips')
            ->slug->toBe('healthy-recipes-tips')
            ->description->toBe('Budget-friendly family meals.')
            ->color->toBe('carrot')
            ->icon->toBe('cooking-pot')
            ->sort_order->toBe(3)
            ->is_active->toBeTrue();
    });

    it('creates an inactive category', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.forum-categories.store'), ['name' => 'Archive', 'color' => 'slate', 'icon' => 'folder', 'sort_order' => 0, 'is_active' => '0'])
            ->assertSessionHasNoErrors();

        expect(ForumCategory::query()->sole()->is_active)->toBeFalse();
    });

    it('numbers the slug when another category already uses it', function () {
        ForumCategory::factory()->create(['name' => 'Healthy Recipes', 'slug' => 'healthy-recipes']);
        ForumCategory::factory()->create(['name' => 'Healthy recipes 2', 'slug' => 'healthy-recipes-2']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.forum-categories.store'), ['name' => 'Healthy Recipes!', 'color' => 'leaf', 'icon' => 'salad', 'sort_order' => 0, 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        expect(ForumCategory::query()->where('name', 'Healthy Recipes!')->sole()->slug)->toBe('healthy-recipes-3');
    });

    it('falls back to a generic slug for a name without letters or digits', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.forum-categories.store'), ['name' => '¿¡!', 'color' => 'leaf', 'icon' => 'salad', 'sort_order' => 0, 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        expect(ForumCategory::query()->sole()->slug)->toBe(ForumCategories::SLUG_FALLBACK);
    });

    it('ignores a slug in the payload', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.forum-categories.store'), ['name' => 'Game Help', 'slug' => 'admin', 'color' => 'grape', 'icon' => 'gamepad-2', 'sort_order' => 0, 'is_active' => '1']);

        expect(ForumCategory::query()->sole()->slug)->toBe('game-help');
    });

    it('requires every field but the description', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.forum-categories.store'), [])
            ->assertSessionHasErrors([
                'name' => 'The name field is required.',
                'color' => 'The color field is required.',
                'icon' => 'The icon field is required.',
                'sort_order' => 'The display order field is required.',
                'is_active' => 'The status field is required.',
            ])
            ->assertSessionDoesntHaveErrors('description');

        $this->assertDatabaseCount('forum_categories', 0);
    });

    it('rejects invalid values', function (array $input, string $field, string $message) {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.forum-categories.store'), [
                'name' => 'Game Help',
                'color' => 'grape',
                'icon' => 'gamepad-2',
                'sort_order' => 0,
                'is_active' => '1',
                ...$input,
            ])
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('forum_categories', 0);
    })->with([
        'name too long' => [['name' => str_repeat('a', 61)], 'name', 'The name field must not be greater than 60 characters.'],
        'description too long' => [['description' => str_repeat('a', 201)], 'description', 'The description field must not be greater than 200 characters.'],
        'unknown color' => [['color' => 'gold'], 'color', 'Choose one of the listed colors.'],
        'unknown icon' => [['icon' => 'skull'], 'icon', 'Choose one of the listed icons.'],
        'order not a number' => [['sort_order' => 'first'], 'sort_order', 'The display order field must be an integer.'],
        'negative order' => [['sort_order' => -1], 'sort_order', 'The display order field must be at least 0.'],
        'order too large' => [['sort_order' => 10000], 'sort_order', 'The display order field must not be greater than 9999.'],
        'status not a boolean' => [['is_active' => 'maybe'], 'is_active', 'The status field must be true or false.'],
    ]);

    it('rejects a name another category uses', function () {
        ForumCategory::factory()->create(['name' => 'Game Help']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.forum-categories.store'), ['name' => 'Game Help', 'color' => 'grape', 'icon' => 'gamepad-2', 'sort_order' => 0, 'is_active' => '1'])
            ->assertSessionHasErrors(['name' => 'Another category already uses this name.']);

        $this->assertDatabaseCount('forum_categories', 1);
    });

    it('forbids teachers', function () {
        $this->actingAs(User::factory()->teacher()->create())
            ->post(route('admin.forum-categories.store'), ['name' => 'Game Help', 'color' => 'grape', 'icon' => 'gamepad-2', 'sort_order' => 0, 'is_active' => '1'])
            ->assertForbidden();

        $this->assertDatabaseCount('forum_categories', 0);
    });
});

describe('edit', function () {
    it('shows the category’s values, bound by its slug', function () {
        $category = ForumCategory::factory()->create(['name' => 'Game Help', 'slug' => 'game-help']);
        ForumThread::factory()->for($category, 'category')->count(3)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/forum-categories/game-help/edit')
            ->assertOk()
            ->assertViewIs('admin.forum-categories.edit')
            ->assertSee('value="Game Help"', false)
            ->assertSeeText('This category has 3 threads.')
            ->assertDontSeeText('Delete “Game Help”?')
            ->assertDontSee('name="_method" value="DELETE"', false);
    });

    it('offers to delete an empty category', function () {
        $category = ForumCategory::factory()->create(['name' => 'Game Help']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum-categories.edit', $category))
            ->assertOk()
            ->assertSeeText('Delete “Game Help”?')
            ->assertSee('<input type="hidden" name="_method" value="DELETE">', false);
    });

    it('returns 404 for an unknown slug', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/forum-categories/no-such-category/edit')
            ->assertNotFound();
    });
});

describe('update', function () {
    it('saves the changes and gives a renamed category a new slug', function () {
        $category = ForumCategory::factory()->create(['name' => 'Game Help', 'slug' => 'game-help', 'is_active' => true]);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.forum-categories.update', $category), [
                'name' => 'NutriMind Game Help',
                'description' => 'Missions, levels and offline play.',
                'color' => 'sky',
                'icon' => 'gamepad-2',
                'sort_order' => 5,
                'is_active' => '0',
            ])
            ->assertRedirectToRoute('admin.forum-categories.index')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', '“NutriMind Game Help” was updated.');

        expect($category->refresh())
            ->name->toBe('NutriMind Game Help')
            ->slug->toBe('nutrimind-game-help')
            ->description->toBe('Missions, levels and offline play.')
            ->color->toBe('sky')
            ->sort_order->toBe(5)
            ->is_active->toBeFalse();
    });

    it('keeps the slug when the name stays the same', function () {
        $category = ForumCategory::factory()->create(['name' => 'Game Help', 'slug' => 'game-help-original']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.forum-categories.update', $category), ['name' => 'Game Help', 'color' => 'sky', 'icon' => 'gamepad-2', 'sort_order' => 1, 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        expect($category->refresh()->slug)->toBe('game-help-original');
    });

    it('rejects a name another category uses', function () {
        ForumCategory::factory()->create(['name' => 'Healthy Recipes']);
        $category = ForumCategory::factory()->create(['name' => 'Game Help']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.forum-categories.update', $category), ['name' => 'Healthy Recipes', 'color' => 'sky', 'icon' => 'gamepad-2', 'sort_order' => 1, 'is_active' => '1'])
            ->assertSessionHasErrors(['name' => 'Another category already uses this name.']);

        expect($category->refresh()->name)->toBe('Game Help');
    });

    it('forbids teachers', function () {
        $category = ForumCategory::factory()->create(['name' => 'Game Help']);

        $this->actingAs(User::factory()->teacher()->create())
            ->put(route('admin.forum-categories.update', $category), ['name' => 'Renamed', 'color' => 'sky', 'icon' => 'gamepad-2', 'sort_order' => 1, 'is_active' => '1'])
            ->assertForbidden();

        expect($category->refresh()->name)->toBe('Game Help');
    });
});

describe('destroy', function () {
    it('deletes an empty category', function () {
        $category = ForumCategory::factory()->create(['name' => 'Game Help']);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.forum-categories.destroy', $category))
            ->assertRedirectToRoute('admin.forum-categories.index')
            ->assertSessionHas('status', '“Game Help” was deleted.');

        $this->assertModelMissing($category);
    });

    it('refuses to delete a category that still has threads', function () {
        $category = ForumCategory::factory()->create();
        ForumThread::factory()->for($category, 'category')->hidden()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.forum-categories.destroy', $category))
            ->assertRedirectToRoute('admin.forum-categories.index')
            ->assertSessionHas('error', 'This category still has threads. Deactivate it instead.');

        $this->assertModelExists($category);
    });

    it('forbids teachers', function () {
        $category = ForumCategory::factory()->create();

        $this->actingAs(User::factory()->teacher()->create())
            ->delete(route('admin.forum-categories.destroy', $category))
            ->assertForbidden();

        $this->assertModelExists($category);
    });
});
