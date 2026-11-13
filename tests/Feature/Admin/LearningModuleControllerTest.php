<?php

use App\Enums\GradeLevel;
use App\Enums\UserRole;
use App\Http\Controllers\Admin\LearningModuleController;
use App\Models\ActivityProgress;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;

/**
 * A valid module form submission.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function learningModulePayload(Subject $subject, array $overrides = []): array
{
    return [
        'subject_id' => $subject->id,
        'grade_level' => 5,
        'quarter' => 2,
        'title' => 'Go, Grow and Glow Foods',
        'world_name' => 'Veggie Valley',
        'description' => 'Learners sort foods into the three food groups.',
        'competency_code' => 'h5n-ia-1',
        'learning_competency' => 'Classifies foods into Go, Grow and Glow groups.',
        'sort_order' => 3,
        'is_published' => '1',
        ...$overrides,
    ];
}

describe('access', function () {
    it('sends guests to the sign-in page', function (string $method, string $route) {
        $module = LearningModule::factory()->create();

        $this->{$method}(route($route, $route === 'admin.modules.index' || $route === 'admin.modules.create' || $route === 'admin.modules.store' ? [] : $module))
            ->assertRedirectToRoute('login');
    })->with([
        'index' => ['get', 'admin.modules.index'],
        'create' => ['get', 'admin.modules.create'],
        'store' => ['post', 'admin.modules.store'],
        'show' => ['get', 'admin.modules.show'],
        'edit' => ['get', 'admin.modules.edit'],
        'update' => ['put', 'admin.modules.update'],
        'destroy' => ['delete', 'admin.modules.destroy'],
    ]);

    it('forbids the other portals', function (UserRole $role) {
        $module = LearningModule::factory()->create();
        $this->actingAs(User::factory()->{$role->value}()->create());

        $this->get(route('admin.modules.index'))->assertForbidden();
        $this->get(route('admin.modules.show', $module))->assertForbidden();
        $this->put(route('admin.modules.update', $module), [])->assertForbidden();
        $this->delete(route('admin.modules.destroy', $module))->assertForbidden();

        $this->assertModelExists($module);
    })->with([UserRole::Teacher, UserRole::Parent]);

    it('answers an unknown module with 404', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.modules.show', 999999))
            ->assertNotFound();
    });
});

describe('index', function () {
    it('lists the modules with their counts', function () {
        $subject = Subject::factory()->create(['name' => 'Health']);
        $module = LearningModule::factory()->for($subject)->create([
            'title' => 'Go, Grow and Glow Foods', 'world_name' => 'Veggie Valley', 'grade_level' => GradeLevel::Five,
            'quarter' => 1, 'competency_code' => 'H5N-IA-1',
        ]);
        $activities = LearningActivity::factory()->for($module, 'module')->count(3)->create();
        Mission::factory()->for($module, 'module')->count(2)->create();
        LessonPlan::factory()->for($module, 'module')->create();
        LessonPlan::factory()->create()->activities()->attach($activities[0]);
        $both = LessonPlan::factory()->for($module, 'module')->create();
        $both->activities()->attach($activities[1]);
        LessonPlan::factory()->create();
        LearningModule::factory()->unpublished()->create(['title' => 'Reading Food Labels']);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.modules.index'));

        $response->assertOk()
            ->assertViewIs('admin.modules.index')
            ->assertSeeText('Go, Grow and Glow Foods')
            ->assertSeeText('Veggie Valley')
            ->assertSeeText('H5N-IA-1')
            ->assertSeeText('Reading Food Labels')
            ->assertSeeText('Draft');

        $listed = $response->viewData('modules')->firstWhere('id', $module->id);
        expect($listed)
            ->activities_count->toBe(3)
            ->missions_count->toBe(2)
            ->lesson_plans_count->toBe(3);
    });

    it('filters by subject, grade, quarter, status and search', function (array $query, string $expected) {
        $health = Subject::factory()->create();
        LearningModule::factory()->for($health)->create(['title' => 'Healthy Snacks', 'grade_level' => GradeLevel::Five, 'quarter' => 1, 'world_name' => 'Snack Shack']);
        LearningModule::factory()->create(['title' => 'Water and Hydration', 'grade_level' => GradeLevel::Six, 'quarter' => 3, 'world_name' => 'Hydration Harbor']);
        LearningModule::factory()->unpublished()->create(['title' => 'Food Safety at Home', 'grade_level' => GradeLevel::Six, 'quarter' => null, 'world_name' => 'Grain Grove']);

        $query = array_map(fn (mixed $value): mixed => $value === 'health' ? $health->id : $value, $query);
        $titles = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.modules.index', $query))
            ->assertOk()
            ->viewData('modules')
            ->pluck('title')
            ->all();

        expect($titles)->toBe([$expected]);
    })->with([
        'subject' => [['subject' => 'health'], 'Healthy Snacks'],
        'grade' => [['grade' => '6', 'status' => 'published'], 'Water and Hydration'],
        'quarter' => [['quarter' => '3'], 'Water and Hydration'],
        'no quarter' => [['quarter' => 'none'], 'Food Safety at Home'],
        'draft' => [['status' => 'draft'], 'Food Safety at Home'],
        'search by world' => [['search' => 'snack shack'], 'Healthy Snacks'],
    ]);

    it('ignores malformed filters', function () {
        LearningModule::factory()->count(2)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.modules.index', ['subject' => 'abc', 'grade' => '9', 'quarter' => '7', 'status' => 'gone', 'search' => ['x']]))
            ->assertOk()
            ->assertViewHas('modules', fn ($modules): bool => $modules->total() === 2);
    });

    it('paginates twenty modules per page', function () {
        LearningModule::factory()->count(21)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.modules.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('modules', fn ($modules): bool => $modules->count() === 1 && $modules->total() === 21);
    });

    it('offers to create the first module when there is none', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.modules.index'))
            ->assertOk()
            ->assertSeeText('No learning modules yet');
    });

    it('explains an empty filtered list', function () {
        LearningModule::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.modules.index', ['search' => 'nothing like this']))
            ->assertOk()
            ->assertSeeText('No modules match your filters')
            ->assertSeeText('Clear filters');
    });

    it('offers no delete action for a module learners played', function () {
        $module = LearningModule::factory()->create();
        ActivityProgress::factory()->for(LearningActivity::factory()->for($module, 'module'), 'activity')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.modules.index'))
            ->assertOk()
            ->assertSeeText('Delete (learners played it)')
            ->assertDontSee('action="'.route('admin.modules.destroy', $module).'"', false);
    });
});

describe('create', function () {
    it('shows an empty form with only active subjects', function () {
        Subject::factory()->create(['name' => 'Health']);
        Subject::factory()->inactive()->create(['name' => 'Retired Subject']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.modules.create'))
            ->assertOk()
            ->assertViewIs('admin.modules.form')
            ->assertViewHas('isPrefilled', false)
            ->assertSeeText('New learning module')
            ->assertSeeText('Health')
            ->assertDontSeeText('Retired Subject');
    });

    it('prefills the form from the query string', function () {
        $subject = Subject::factory()->create();

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.modules.create', [
            'subject_id' => $subject->id,
            'grade_level' => 6,
            'quarter' => 2,
            'competency_code' => ' h6n-iic-4 ',
            'learning_competency' => 'Explains the importance of reading food labels.',
        ]));

        $response->assertOk()
            ->assertViewHas('isPrefilled', true)
            ->assertSeeText('Prefilled from lesson alignment')
            ->assertSee('value="H6N-IIC-4"', false)
            ->assertSeeText('Explains the importance of reading food labels.');
        expect($response->viewData('module'))
            ->subject_id->toBe($subject->id)
            ->grade_level->toBe(GradeLevel::Six)
            ->quarter->toBe(2);
    });

    it('drops prefill values that are not valid', function () {
        $inactive = Subject::factory()->inactive()->create();

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.modules.create', [
            'subject_id' => $inactive->id,
            'grade_level' => 4,
            'quarter' => 9,
            'competency_code' => str_repeat('A', 41),
            'title' => ['not', 'a', 'string'],
        ]));

        $response->assertOk()->assertViewHas('isPrefilled', false);
        expect($response->viewData('module'))
            ->subject_id->toBeNull()
            ->grade_level->toBeNull()
            ->quarter->toBeNull()
            ->competency_code->toBeNull();
    });
});

describe('store', function () {
    it('creates a module with an uppercased competency code', function () {
        $subject = Subject::factory()->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.modules.store'), learningModulePayload($subject));

        $module = LearningModule::query()->sole();
        $response->assertRedirectToRoute('admin.modules.show', $module)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Module “Go, Grow and Glow Foods” created. Add its activities next.');
        expect($module)
            ->subject_id->toBe($subject->id)
            ->grade_level->toBe(GradeLevel::Five)
            ->quarter->toBe(2)
            ->world_name->toBe('Veggie Valley')
            ->competency_code->toBe('H5N-IA-1')
            ->sort_order->toBe(3)
            ->is_published->toBeTrue();
    });

    it('stores a draft module without the optional fields', function () {
        $subject = Subject::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.modules.store'), learningModulePayload($subject, [
                'quarter' => '', 'world_name' => '', 'description' => '', 'competency_code' => '   ', 'learning_competency' => '', 'is_published' => '0',
            ]))
            ->assertSessionHasNoErrors();

        expect(LearningModule::query()->sole())
            ->quarter->toBeNull()
            ->world_name->toBeNull()
            ->competency_code->toBeNull()
            ->is_published->toBeFalse();
    });

    it('requires the subject, grade, title, display order and published setting', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.modules.store'), [])
            ->assertSessionHasErrors([
                'subject_id' => 'The subject field is required.',
                'grade_level' => 'The grade level field is required.',
                'title' => 'The title field is required.',
                'sort_order' => 'The display order field is required.',
                'is_published' => 'The published setting field is required.',
            ]);

        expect(LearningModule::query()->count())->toBe(0);
    });

    it('rejects invalid module details', function (array $overrides, string $field, string $message) {
        $subject = Subject::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.modules.store'), learningModulePayload($subject, $overrides))
            ->assertSessionHasErrors([$field => $message]);

        expect(LearningModule::query()->count())->toBe(0);
    })->with([
        'unknown grade' => [['grade_level' => 4], 'grade_level', 'The selected grade level is invalid.'],
        'quarter too high' => [['quarter' => 5], 'quarter', 'The quarter field must be between 1 and 4.'],
        'long title' => [['title' => str_repeat('a', 151)], 'title', 'The title field must not be greater than 150 characters.'],
        'long world' => [['world_name' => str_repeat('a', 101)], 'world_name', 'The game world field must not be greater than 100 characters.'],
        'long description' => [['description' => str_repeat('a', 2001)], 'description', 'The description field must not be greater than 2000 characters.'],
        'long code' => [['competency_code' => str_repeat('a', 41)], 'competency_code', 'The competency code field must not be greater than 40 characters.'],
        'long competency' => [['learning_competency' => str_repeat('a', 1001)], 'learning_competency', 'The learning competency field must not be greater than 1000 characters.'],
        'negative order' => [['sort_order' => -1], 'sort_order', 'The display order field must be between 0 and 999.'],
        'order too high' => [['sort_order' => 1000], 'sort_order', 'The display order field must be between 0 and 999.'],
        'not a boolean' => [['is_published' => 'maybe'], 'is_published', 'The published setting field must be true or false.'],
    ]);

    it('rejects an inactive or unknown subject', function (bool $exists) {
        $subject = $exists ? Subject::factory()->inactive()->create() : Subject::factory()->make(['id' => 999999]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.modules.store'), learningModulePayload($subject))
            ->assertSessionHasErrors(['subject_id' => 'Choose an active subject.']);
    })->with(['inactive subject' => true, 'unknown subject' => false]);
});

describe('show', function () {
    it('shows the activities, missions and lesson plan usage', function () {
        $module = LearningModule::factory()->create(['title' => 'The Balanced Plate', 'competency_code' => 'H5N-IB-2', 'grade_level' => GradeLevel::Five]);
        $played = LearningActivity::factory()->for($module, 'module')->create(['title' => 'Build a Healthy Plate', 'sort_order' => 1]);
        LearningActivity::factory()->for($module, 'module')->unpublished()->create(['title' => 'Plate Quiz Practice', 'sort_order' => 2]);
        ActivityProgress::factory()->for($played, 'activity')->count(2)->create();
        $mission = Mission::factory()->for($module, 'module')->create(['title' => 'Rainbow Plate']);
        MissionProgress::factory()->for($mission)->completed()->create();
        MissionProgress::factory()->for($mission)->inProgress()->create();
        $following = LessonPlan::factory()->for($module, 'module')->create(['title' => 'Planning a Balanced Plate']);
        $following->activities()->attach($played);
        LessonPlan::factory()->create(['subject_id' => $module->subject_id, 'grade_level' => GradeLevel::Five, 'competency_code' => 'h5n-ib-2']);
        Quiz::factory()->for($module, 'module')->create();

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.modules.show', $module));

        $response->assertOk()
            ->assertViewIs('admin.modules.show')
            ->assertSeeText('Build a Healthy Plate')
            ->assertSeeText('Plate Quiz Practice')
            ->assertSeeText('1 of 2 published')
            ->assertSeeText('Rainbow Plate')
            ->assertSeeText('1 / 2')
            ->assertSeeText('Planning a Balanced Plate')
            ->assertSeeText('Delete (learners played it)')
            ->assertViewHas('usage', [
                'lessonPlans' => 1,
                'followingModule' => 1,
                'sameCompetency' => 2,
                'quizzes' => 1,
            ]);
        expect($response->viewData('activities')->firstWhere('id', $played->id))
            ->progress_count->toBe(2)
            ->lesson_plans_count->toBe(1);
    });

    it('shows the empty states of a new module', function () {
        $module = LearningModule::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.modules.show', $module))
            ->assertOk()
            ->assertSeeText('No activities yet')
            ->assertSeeText('No missions in this module')
            ->assertSeeText('Not in any lesson plan yet')
            ->assertSee('action="'.route('admin.modules.destroy', $module).'"', false);
    });
});

describe('edit', function () {
    it('shows the module in the form and keeps its inactive subject selectable', function () {
        $subject = Subject::factory()->inactive()->create(['name' => 'Old Health']);
        $module = LearningModule::factory()->for($subject)->create(['title' => 'Healthy Snacks']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.modules.edit', $module))
            ->assertOk()
            ->assertViewIs('admin.modules.form')
            ->assertSee('value="Healthy Snacks"', false)
            ->assertSeeText('Old Health (inactive)');
    });
});

describe('update', function () {
    it('updates the module', function () {
        $module = LearningModule::factory()->create(['title' => 'Old title']);
        $subject = Subject::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.modules.update', $module), learningModulePayload($subject, ['title' => 'Healthy Snacks', 'is_published' => '0']))
            ->assertRedirectToRoute('admin.modules.show', $module)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Module updated.');

        expect($module->refresh())
            ->title->toBe('Healthy Snacks')
            ->subject_id->toBe($subject->id)
            ->is_published->toBeFalse();
    });

    it('keeps the module\'s own inactive subject but refuses another inactive one', function () {
        $current = Subject::factory()->inactive()->create();
        $module = LearningModule::factory()->for($current)->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), learningModulePayload($current))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->put(route('admin.modules.update', $module), learningModulePayload(Subject::factory()->inactive()->create()))
            ->assertSessionHasErrors(['subject_id' => 'Choose an active subject.']);

        expect($module->refresh()->subject_id)->toBe($current->id);
    });

    it('validates the update', function () {
        $module = LearningModule::factory()->create(['title' => 'Kept']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.modules.update', $module), learningModulePayload($module->subject, ['title' => '']))
            ->assertSessionHasErrors(['title' => 'The title field is required.']);

        expect($module->refresh()->title)->toBe('Kept');
    });
});

describe('destroy', function () {
    it('deletes the module and its activities and unlinks the rest', function () {
        $module = LearningModule::factory()->create(['title' => 'Healthy Snacks']);
        $activity = LearningActivity::factory()->for($module, 'module')->create();
        $mission = Mission::factory()->for($module, 'module')->create();
        $lessonPlan = LessonPlan::factory()->for($module, 'module')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.modules.destroy', $module))
            ->assertRedirectToRoute('admin.modules.index')
            ->assertSessionHas('status', 'Module “Healthy Snacks” deleted.');

        $this->assertModelMissing($module);
        $this->assertModelMissing($activity);
        expect($mission->refresh()->learning_module_id)->toBeNull()
            ->and($lessonPlan->refresh()->learning_module_id)->toBeNull();
    });

    it('refuses to delete a module whose activities learners played', function () {
        $module = LearningModule::factory()->create();
        ActivityProgress::factory()->for(LearningActivity::factory()->for($module, 'module'), 'activity')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.modules.destroy', $module))
            ->assertRedirectToRoute('admin.modules.show', $module)
            ->assertSessionHas('error', LearningModuleController::HAS_PROGRESS_MESSAGE);

        $this->assertModelExists($module);
    });
});
