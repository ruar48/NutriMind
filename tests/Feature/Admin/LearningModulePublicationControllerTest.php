<?php

use App\Enums\UserRole;
use App\Models\LearningModule;
use App\Models\User;

it('sends guests to the sign-in page', function () {
    $module = LearningModule::factory()->create();

    $this->put(route('admin.modules.publication.update', $module), ['is_published' => '0'])->assertRedirectToRoute('login');

    expect($module->refresh()->is_published)->toBeTrue();
});

it('forbids the other portals', function (UserRole $role) {
    $module = LearningModule::factory()->create();

    $this->actingAs(User::factory()->{$role->value}()->create())
        ->put(route('admin.modules.publication.update', $module), ['is_published' => '0'])
        ->assertForbidden();

    expect($module->refresh()->is_published)->toBeTrue();
})->with([UserRole::Teacher, UserRole::Parent]);

it('answers an unknown module with 404', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.modules.publication.update', 999999), ['is_published' => '1'])
        ->assertNotFound();
});

it('publishes a draft module and returns to the page it came from', function () {
    $module = LearningModule::factory()->unpublished()->create(['title' => 'Healthy Snacks']);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.modules.index'))
        ->put(route('admin.modules.publication.update', $module), ['is_published' => '1'])
        ->assertRedirect(route('admin.modules.index'))
        ->assertSessionHas('status', 'Module “Healthy Snacks” published. Learners can play its published activities.');

    expect($module->refresh()->is_published)->toBeTrue();
});

it('unpublishes a module', function () {
    $module = LearningModule::factory()->create(['title' => 'Healthy Snacks']);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.modules.show', $module))
        ->put(route('admin.modules.publication.update', $module), ['is_published' => '0'])
        ->assertRedirect(route('admin.modules.show', $module))
        ->assertSessionHas('status', 'Module “Healthy Snacks” unpublished. Learners no longer see it in the game.');

    expect($module->refresh()->is_published)->toBeFalse();
});

it('requires a true or false published setting', function (array $payload, string $message) {
    $module = LearningModule::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.modules.publication.update', $module), $payload)
        ->assertSessionHasErrors(['is_published' => $message]);

    expect($module->refresh()->is_published)->toBeTrue();
})->with([
    'missing' => [[], 'The published setting field is required.'],
    'not a boolean' => [['is_published' => 'later'], 'The published setting field must be true or false.'],
]);

it('offers the opposite action on the module page', function (bool $published, string $label) {
    $module = LearningModule::factory()->create(['is_published' => $published]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.modules.show', $module))
        ->assertOk()
        ->assertSee('action="'.route('admin.modules.publication.update', $module).'"', false)
        ->assertSee('name="is_published" value="'.($published ? '0' : '1').'"', false)
        ->assertSeeText($label);
})->with([
    'published module' => [true, 'Unpublish'],
    'draft module' => [false, 'Publish'],
]);
