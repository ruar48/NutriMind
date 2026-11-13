<?php

use App\Enums\RewardType;
use App\Enums\UserRole;
use App\Http\Controllers\Admin\RewardController;
use App\Models\Reward;
use App\Models\User;

/**
 * A valid reward form submission.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rewardPayload(array $overrides = []): array
{
    return [
        'name' => 'Veggie Champion',
        'description' => 'For learners who love their vegetables.',
        'type' => RewardType::Trophy->value,
        'icon' => 'carrot',
        'color' => 'leaf',
        'criteria' => 'Complete every mission in Veggie Valley.',
        'points_required' => 1500,
        'is_active' => '1',
        ...$overrides,
    ];
}

describe('access', function () {
    it('sends guests to the sign-in page', function () {
        $reward = Reward::factory()->create();

        $this->get(route('admin.rewards.index'))->assertRedirectToRoute('login');
        $this->get(route('admin.rewards.create'))->assertRedirectToRoute('login');
        $this->post(route('admin.rewards.store'), rewardPayload())->assertRedirectToRoute('login');
        $this->get(route('admin.rewards.edit', $reward))->assertRedirectToRoute('login');
        $this->put(route('admin.rewards.update', $reward), rewardPayload())->assertRedirectToRoute('login');
        $this->delete(route('admin.rewards.destroy', $reward))->assertRedirectToRoute('login');
    });

    it('forbids the other portals', function (UserRole $role) {
        $reward = Reward::factory()->create();
        $this->actingAs(User::factory()->{$role->value}()->create());

        $this->get(route('admin.rewards.index'))->assertForbidden();
        $this->post(route('admin.rewards.store'), rewardPayload())->assertForbidden();
        $this->delete(route('admin.rewards.destroy', $reward))->assertForbidden();

        $this->assertModelExists($reward);
        expect(Reward::query()->count())->toBe(1);
    })->with([UserRole::Teacher, UserRole::Parent]);

    it('answers an unknown reward with 404', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.rewards.edit', 999999))
            ->assertNotFound();
    });
});

describe('index', function () {
    it('shows each reward as a card with how often it was earned', function () {
        $reward = Reward::factory()->create(['name' => 'Fruit Explorer', 'type' => RewardType::Badge, 'criteria' => 'Collect ten different fruits.', 'points_required' => 1250]);
        $reward->students()->attach(User::factory()->student()->count(3)->create());
        Reward::factory()->inactive()->create(['name' => 'Retired Badge']);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.rewards.index'));

        $response->assertOk()
            ->assertViewIs('admin.rewards.index')
            ->assertSeeText('Fruit Explorer')
            ->assertSeeText('Collect ten different fruits.')
            ->assertSeeText('1,250 XP')
            ->assertSeeText('Retired Badge')
            ->assertSeeText('Inactive')
            ->assertSee('data-reward-card="'.$reward->id.'"', false)
            ->assertDontSee('action="'.route('admin.rewards.destroy', $reward).'"', false);
        expect($response->viewData('rewards')->firstWhere('id', $reward->id)->students_count)->toBe(3)
            ->and($response->viewData('rewards')->pluck('name')->all())->toBe(['Fruit Explorer', 'Retired Badge']);
    });

    it('filters by type, status and search', function (array $query, string $expected) {
        Reward::factory()->create(['name' => 'Golden Spoon', 'type' => RewardType::Trophy, 'criteria' => 'Reach 2,000 points.']);
        Reward::factory()->create(['name' => 'Chef Hat', 'type' => RewardType::AvatarItem, 'criteria' => 'Cook five recipes.']);
        Reward::factory()->inactive()->create(['name' => 'Old Badge', 'type' => RewardType::Badge, 'criteria' => 'Retired.']);

        $names = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.rewards.index', $query))
            ->assertOk()
            ->viewData('rewards')
            ->pluck('name')
            ->all();

        expect($names)->toBe([$expected]);
    })->with([
        'type' => [['type' => 'avatar_item'], 'Chef Hat'],
        'inactive' => [['status' => 'inactive'], 'Old Badge'],
        'search criteria' => [['search' => '2,000'], 'Golden Spoon'],
    ]);

    it('offers to create the first reward when there is none', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.rewards.index'))
            ->assertOk()
            ->assertSeeText('No rewards yet');
    });

    it('explains an empty filtered list', function () {
        Reward::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.rewards.index', ['search' => 'nothing like this']))
            ->assertOk()
            ->assertSeeText('No rewards match your filters');
    });
});

describe('create', function () {
    it('shows the form with the curated icons and the palette', function () {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.rewards.create'));

        $response->assertOk()
            ->assertViewIs('admin.rewards.form')
            ->assertSeeText('New reward')
            ->assertSee('name="icon" value="trophy"', false)
            ->assertSee('name="color" value="grape"', false);
        expect($response->viewData('reward'))
            ->type->toBe(RewardType::Badge)
            ->icon->toBe('award')
            ->color->toBe('sun');
    });
});

describe('store', function () {
    it('creates a reward', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.rewards.store'), rewardPayload())
            ->assertRedirectToRoute('admin.rewards.index')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Reward “Veggie Champion” created.');

        expect(Reward::query()->sole())
            ->type->toBe(RewardType::Trophy)
            ->icon->toBe('carrot')
            ->color->toBe('leaf')
            ->criteria->toBe('Complete every mission in Veggie Valley.')
            ->points_required->toBe(1500)
            ->is_active->toBeTrue();
    });

    it('creates a reward that is not earned by points', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.rewards.store'), rewardPayload(['points_required' => '', 'criteria' => '', 'description' => '', 'is_active' => '0']))
            ->assertSessionHasNoErrors();

        expect(Reward::query()->sole())
            ->points_required->toBeNull()
            ->criteria->toBeNull()
            ->is_active->toBeFalse();
    });

    it('requires the name, type, icon, color and active setting', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.rewards.store'), [])
            ->assertSessionHasErrors([
                'name' => 'The name field is required.',
                'type' => 'The reward type field is required.',
                'icon' => 'The icon field is required.',
                'color' => 'The color field is required.',
                'is_active' => 'The active setting field is required.',
            ]);

        expect(Reward::query()->count())->toBe(0);
    });

    it('rejects invalid reward details', function (array $overrides, string $field, string $message) {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.rewards.store'), rewardPayload($overrides))
            ->assertSessionHasErrors([$field => $message]);

        expect(Reward::query()->count())->toBe(0);
    })->with([
        'long name' => [['name' => str_repeat('a', 101)], 'name', 'The name field must not be greater than 100 characters.'],
        'long description' => [['description' => str_repeat('a', 1001)], 'description', 'The description field must not be greater than 1000 characters.'],
        'unknown type' => [['type' => 'sticker'], 'type', 'The selected reward type is invalid.'],
        'icon outside the set' => [['icon' => 'trash-2'], 'icon', 'Choose one of the reward icons.'],
        'unknown color' => [['color' => 'magenta'], 'color', 'Choose one of the reward colors.'],
        'long criteria' => [['criteria' => str_repeat('a', 256)], 'criteria', 'The how to earn it field must not be greater than 255 characters.'],
        'zero points' => [['points_required' => 0], 'points_required', 'The points required field must be between 1 and 100000.'],
        'too many points' => [['points_required' => 100001], 'points_required', 'The points required field must be between 1 and 100000.'],
        'not a boolean' => [['is_active' => 'perhaps'], 'is_active', 'The active setting field must be true or false.'],
    ]);

    it('rejects a name another reward uses', function () {
        Reward::factory()->create(['name' => 'Veggie Champion']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.rewards.store'), rewardPayload())
            ->assertSessionHasErrors(['name' => 'Another reward already uses this name.']);

        expect(Reward::query()->count())->toBe(1);
    });
});

describe('edit', function () {
    it('shows the reward in the form with how often it was earned', function () {
        $reward = Reward::factory()->create(['name' => 'Water Warrior']);
        $reward->students()->attach(User::factory()->student()->count(2)->create());

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.rewards.edit', $reward))
            ->assertOk()
            ->assertViewIs('admin.rewards.form')
            ->assertSee('value="Water Warrior"', false)
            ->assertSeeText('Earned by 2 learners.');
    });
});

describe('update', function () {
    it('updates the reward and keeps its own name', function () {
        $reward = Reward::factory()->create(['name' => 'Veggie Champion', 'is_active' => true]);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.rewards.update', $reward), rewardPayload(['is_active' => '0', 'color' => 'berry']))
            ->assertRedirectToRoute('admin.rewards.index')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Reward updated.');

        expect($reward->refresh())
            ->name->toBe('Veggie Champion')
            ->color->toBe('berry')
            ->is_active->toBeFalse();
    });

    it('keeps an icon chosen before the curated set but refuses another icon outside it', function () {
        $reward = Reward::factory()->create(['icon' => 'school']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.rewards.update', $reward), rewardPayload(['icon' => 'school']))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->put(route('admin.rewards.update', $reward), rewardPayload(['icon' => 'calendar']))
            ->assertSessionHasErrors(['icon' => 'Choose one of the reward icons.']);

        expect($reward->refresh()->icon)->toBe('school');
    });

    it('rejects the name of another reward', function () {
        Reward::factory()->create(['name' => 'Golden Spoon']);
        $reward = Reward::factory()->create(['name' => 'Silver Spoon']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.rewards.update', $reward), rewardPayload(['name' => 'Golden Spoon']))
            ->assertSessionHasErrors(['name' => 'Another reward already uses this name.']);

        expect($reward->refresh()->name)->toBe('Silver Spoon');
    });
});

describe('destroy', function () {
    it('deletes a reward nobody earned', function () {
        $reward = Reward::factory()->create(['name' => 'Snack Smart']);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.rewards.destroy', $reward))
            ->assertRedirectToRoute('admin.rewards.index')
            ->assertSessionHas('status', 'Reward “Snack Smart” deleted.');

        $this->assertModelMissing($reward);
    });

    it('refuses to delete a reward learners earned', function () {
        $reward = Reward::factory()->create();
        $reward->students()->attach(User::factory()->student()->create());

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.rewards.destroy', $reward))
            ->assertRedirectToRoute('admin.rewards.index')
            ->assertSessionHas('error', RewardController::AWARDED_MESSAGE);

        $this->assertModelExists($reward);
    });
});
