<?php

use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Http\Controllers\Admin\LearningActivityController;
use App\Models\ActivityProgress;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\User;

/**
 * A valid activity form submission.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function learningActivityPayload(array $overrides = []): array
{
    return [
        'title' => 'Sort the Food Groups',
        'type' => ActivityType::MiniGame->value,
        'description' => 'Drag each food into its group.',
        'content_url' => 'https://example.org/food-groups',
        'estimated_minutes' => 12,
        'points' => 20,
        'sort_order' => 4,
        'is_published' => '1',
        ...$overrides,
    ];
}

describe('access', function () {
    it('sends guests to the sign-in page', function () {
        $activity = LearningActivity::factory()->create();

        $this->get(route('admin.modules.activities.create', $activity->learning_module_id))->assertRedirectToRoute('login');
        $this->post(route('admin.modules.activities.store', $activity->learning_module_id), learningActivityPayload())->assertRedirectToRoute('login');
        $this->get(route('admin.activities.edit', $activity))->assertRedirectToRoute('login');
        $this->put(route('admin.activities.update', $activity), learningActivityPayload())->assertRedirectToRoute('login');
        $this->delete(route('admin.activities.destroy', $activity))->assertRedirectToRoute('login');
    });

    it('forbids the other portals', function (UserRole $role) {
        $activity = LearningActivity::factory()->create(['title' => 'Kept']);
        $this->actingAs(User::factory()->{$role->value}()->create());

        $this->get(route('admin.modules.activities.create', $activity->learning_module_id))->assertForbidden();
        $this->put(route('admin.activities.update', $activity), learningActivityPayload())->assertForbidden();
        $this->delete(route('admin.activities.destroy', $activity))->assertForbidden();

        expect($activity->refresh()->title)->toBe('Kept');
    })->with([UserRole::Teacher, UserRole::Parent]);

    it('answers an unknown module or activity with 404', function () {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.modules.activities.create', 999999))->assertNotFound();
        $this->get(route('admin.activities.edit', 999999))->assertNotFound();
    });
});

describe('create', function () {
    it('shows the form placed after the module\'s last activity', function () {
        $module = LearningModule::factory()->create(['title' => 'Healthy Snacks']);
        LearningActivity::factory()->for($module, 'module')->create(['sort_order' => 6]);
        LearningActivity::factory()->create(['sort_order' => 40]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.modules.activities.create', $module));

        $response->assertOk()
            ->assertViewIs('admin.activities.form')
            ->assertSeeText('New activity')
            ->assertSeeText('Healthy Snacks');
        expect($response->viewData('activity'))
            ->sort_order->toBe(7)
            ->points->toBe(10)
            ->is_published->toBeTrue();
    });

    it('starts the first activity of a module at position 1', function () {
        $module = LearningModule::factory()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.modules.activities.create', $module));

        expect($response->viewData('activity')->sort_order)->toBe(1);
    });
});

describe('store', function () {
    it('adds the activity to the module', function () {
        $module = LearningModule::factory()->create(['title' => 'Healthy Snacks']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.modules.activities.store', $module), learningActivityPayload())
            ->assertRedirectToRoute('admin.modules.show', $module)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Activity “Sort the Food Groups” added to Healthy Snacks.');

        expect(LearningActivity::query()->sole())
            ->learning_module_id->toBe($module->id)
            ->type->toBe(ActivityType::MiniGame)
            ->content_url->toBe('https://example.org/food-groups')
            ->estimated_minutes->toBe(12)
            ->points->toBe(20)
            ->sort_order->toBe(4)
            ->is_published->toBeTrue();
    });

    it('requires the title, type, points, play order and published setting', function () {
        $module = LearningModule::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.modules.activities.store', $module), [])
            ->assertSessionHasErrors([
                'title' => 'The title field is required.',
                'type' => 'The activity type field is required.',
                'points' => 'The points field is required.',
                'sort_order' => 'The play order field is required.',
                'is_published' => 'The published setting field is required.',
            ]);

        expect(LearningActivity::query()->count())->toBe(0);
    });

    it('rejects invalid activity details', function (array $overrides, string $field, string $message) {
        $module = LearningModule::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.modules.activities.store', $module), learningActivityPayload($overrides))
            ->assertSessionHasErrors([$field => $message]);

        expect(LearningActivity::query()->count())->toBe(0);
    })->with([
        'long title' => [['title' => str_repeat('a', 151)], 'title', 'The title field must not be greater than 150 characters.'],
        'unknown type' => [['type' => 'boss_fight'], 'type', 'The selected activity type is invalid.'],
        'long description' => [['description' => str_repeat('a', 2001)], 'description', 'The description field must not be greater than 2000 characters.'],
        'not a link' => [['content_url' => 'food groups'], 'content_url', 'The content link field must be a valid URL.'],
        'unsafe scheme' => [['content_url' => 'javascript:alert(1)'], 'content_url', 'The content link field must be a valid URL.'],
        'long link' => [['content_url' => 'https://example.org/'.str_repeat('a', 240)], 'content_url', 'The content link field must not be greater than 255 characters.'],
        'no minutes' => [['estimated_minutes' => 0], 'estimated_minutes', 'The estimated time field must be between 1 and 240.'],
        'too many minutes' => [['estimated_minutes' => 241], 'estimated_minutes', 'The estimated time field must be between 1 and 240.'],
        'negative points' => [['points' => -5], 'points', 'The points field must be between 0 and 1000.'],
        'too many points' => [['points' => 1001], 'points', 'The points field must be between 0 and 1000.'],
        'order too high' => [['sort_order' => 1000], 'sort_order', 'The play order field must be between 0 and 999.'],
        'not a boolean' => [['is_published' => 'yes please'], 'is_published', 'The published setting field must be true or false.'],
    ]);
});

describe('edit', function () {
    it('shows the activity in the form', function () {
        $activity = LearningActivity::factory()->create(['title' => 'Label Detective']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.activities.edit', $activity))
            ->assertOk()
            ->assertViewIs('admin.activities.form')
            ->assertSeeText('Edit activity')
            ->assertSee('value="Label Detective"', false)
            ->assertSee('action="'.route('admin.activities.update', $activity).'"', false);
    });
});

describe('update', function () {
    it('updates the activity', function () {
        $activity = LearningActivity::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.activities.update', $activity), learningActivityPayload(['title' => 'Snack Swap', 'type' => ActivityType::Video->value, 'content_url' => '', 'estimated_minutes' => '', 'is_published' => '0']))
            ->assertRedirectToRoute('admin.modules.show', $activity->learning_module_id)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Activity updated.');

        expect($activity->refresh())
            ->title->toBe('Snack Swap')
            ->type->toBe(ActivityType::Video)
            ->content_url->toBeNull()
            ->estimated_minutes->toBeNull()
            ->is_published->toBeFalse();
    });

    it('validates the update', function () {
        $activity = LearningActivity::factory()->create(['points' => 15]);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.activities.update', $activity), learningActivityPayload(['points' => 5000]))
            ->assertSessionHasErrors(['points' => 'The points field must be between 0 and 1000.']);

        expect($activity->refresh()->points)->toBe(15);
    });
});

describe('destroy', function () {
    it('deletes the activity and its lesson plan alignments', function () {
        $activity = LearningActivity::factory()->create(['title' => 'Snack Swap']);
        $lessonPlan = LessonPlan::factory()->create();
        $lessonPlan->activities()->attach($activity);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.activities.destroy', $activity))
            ->assertRedirectToRoute('admin.modules.show', $activity->learning_module_id)
            ->assertSessionHas('status', 'Activity “Snack Swap” deleted.');

        $this->assertModelMissing($activity);
        $this->assertDatabaseMissing('learning_activity_lesson_plan', ['learning_activity_id' => $activity->id]);
    });

    it('refuses to delete an activity learners played', function () {
        $activity = LearningActivity::factory()->create();
        ActivityProgress::factory()->for($activity, 'activity')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.activities.destroy', $activity))
            ->assertRedirectToRoute('admin.modules.show', $activity->learning_module_id)
            ->assertSessionHas('error', LearningActivityController::HAS_PROGRESS_MESSAGE);

        $this->assertModelExists($activity);
    });
});
