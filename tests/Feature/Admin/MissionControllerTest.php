<?php

use App\Enums\Difficulty;
use App\Enums\GradeLevel;
use App\Enums\MissionType;
use App\Enums\UserRole;
use App\Http\Controllers\Admin\MissionController;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A valid mission form submission.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function missionPayload(Subject $subject, array $overrides = []): array
{
    return [
        'subject_id' => $subject->id,
        'learning_module_id' => '',
        'grade_level' => '',
        'title' => 'Breakfast Champion',
        'description' => 'Start every school day with a healthy breakfast.',
        'objective' => 'Eat a healthy breakfast five days in a row.',
        'type' => MissionType::Weekly->value,
        'difficulty' => Difficulty::Medium->value,
        'points_reward' => 150,
        'starts_on' => '2026-11-16',
        'ends_on' => '2026-11-20',
        'sort_order' => 2,
        'is_active' => '1',
        ...$overrides,
    ];
}

describe('access', function () {
    it('sends guests to the sign-in page', function () {
        $mission = Mission::factory()->create();

        $this->get(route('admin.missions.index'))->assertRedirectToRoute('login');
        $this->get(route('admin.missions.create'))->assertRedirectToRoute('login');
        $this->post(route('admin.missions.store'))->assertRedirectToRoute('login');
        $this->get(route('admin.missions.edit', $mission))->assertRedirectToRoute('login');
        $this->put(route('admin.missions.update', $mission))->assertRedirectToRoute('login');
        $this->delete(route('admin.missions.destroy', $mission))->assertRedirectToRoute('login');
    });

    it('forbids the other portals', function (UserRole $role) {
        $mission = Mission::factory()->create();
        $this->actingAs(User::factory()->{$role->value}()->create());

        $this->get(route('admin.missions.index'))->assertForbidden();
        $this->put(route('admin.missions.update', $mission), missionPayload($mission->subject))->assertForbidden();
        $this->delete(route('admin.missions.destroy', $mission))->assertForbidden();

        $this->assertModelExists($mission);
    })->with([UserRole::Teacher, UserRole::Parent]);

    it('answers an unknown mission with 404', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.missions.edit', 999999))
            ->assertNotFound();
    });
});

describe('index', function () {
    it('lists the missions with their module and completion', function () {
        $module = LearningModule::factory()->create(['title' => 'Healthy Snacks']);
        $mission = Mission::factory()->for($module, 'module')->create(['title' => 'Snack Swap Hero', 'points_reward' => 75]);
        MissionProgress::factory()->for($mission)->completed()->count(2)->create();
        MissionProgress::factory()->for($mission)->inProgress()->create();
        Mission::factory()->inactive()->create(['title' => 'Water Warrior', 'grade_level' => null]);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.missions.index'));

        $response->assertOk()
            ->assertViewIs('admin.missions.index')
            ->assertSeeText('Snack Swap Hero')
            ->assertSeeText('Healthy Snacks')
            ->assertSeeText('75 XP')
            ->assertSeeText('2 / 3')
            ->assertSeeText('Water Warrior')
            ->assertSeeText('Both grades')
            ->assertSeeText('Inactive')
            ->assertSeeText('Delete (learners started it)');
        expect($response->viewData('missions')->pluck('title')->all())->toBe(['Snack Swap Hero', 'Water Warrior']);
    });

    it('filters by subject, type, difficulty, grade, status and search', function (array $query, string $expected) {
        $science = Subject::factory()->create();
        Mission::factory()->for($science)->create(['title' => 'Rainbow Plate', 'type' => MissionType::Main, 'difficulty' => Difficulty::Easy, 'grade_level' => GradeLevel::Five, 'objective' => 'Collect every color.']);
        Mission::factory()->create(['title' => 'Water Warrior', 'type' => MissionType::Daily, 'difficulty' => Difficulty::Hard, 'grade_level' => GradeLevel::Six, 'objective' => 'Log eight glasses of water.']);
        Mission::factory()->inactive()->create(['title' => 'Label Reader', 'type' => MissionType::Challenge, 'difficulty' => Difficulty::Medium, 'grade_level' => null, 'objective' => 'Read three labels.']);

        $query = array_map(fn (mixed $value): mixed => $value === 'science' ? $science->id : $value, $query);
        $titles = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.missions.index', $query))
            ->assertOk()
            ->viewData('missions')
            ->pluck('title')
            ->all();

        expect($titles)->toBe([$expected]);
    })->with([
        'subject' => [['subject' => 'science'], 'Rainbow Plate'],
        'type' => [['type' => 'daily'], 'Water Warrior'],
        'difficulty' => [['difficulty' => 'medium'], 'Label Reader'],
        'grade' => [['grade' => '5'], 'Rainbow Plate'],
        'both grades' => [['grade' => 'both'], 'Label Reader'],
        'inactive' => [['status' => 'inactive'], 'Label Reader'],
        'search objective' => [['search' => 'glasses'], 'Water Warrior'],
    ]);

    it('offers to create the first mission when there is none', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.missions.index'))
            ->assertOk()
            ->assertSeeText('No missions yet');
    });

    it('explains an empty filtered list', function () {
        Mission::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.missions.index', ['search' => 'nothing like this']))
            ->assertOk()
            ->assertSeeText('No missions match your filters');
    });

    it('renders more missions without more queries', function () {
        $admin = User::factory()->admin()->create();
        $queries = function () use ($admin): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get(route('admin.missions.index'))->assertOk();

            return count(DB::getQueryLog());
        };
        Mission::factory()->for(LearningModule::factory(), 'module')->create();
        $queries();
        $single = $queries();

        foreach (Mission::factory()->for(LearningModule::factory(), 'module')->count(4)->create() as $mission) {
            MissionProgress::factory()->for($mission)->completed()->create();
        }

        expect($queries())->toBe($single);
    });
});

describe('create', function () {
    it('shows an empty form', function () {
        LearningModule::factory()->create(['title' => 'Go, Grow and Glow Foods']);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.missions.create'));

        $response->assertOk()
            ->assertViewIs('admin.missions.form')
            ->assertSeeText('New mission')
            ->assertSeeText('Go, Grow and Glow Foods')
            ->assertViewHas('startingModule', null);
        expect($response->viewData('mission'))
            ->difficulty->toBe(Difficulty::Easy)
            ->points_reward->toBe(50)
            ->is_active->toBeTrue();
    });

    it('starts the mission inside the given module', function () {
        $module = LearningModule::factory()->create(['grade_level' => GradeLevel::Six]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.missions.create', ['learning_module_id' => $module->id]));

        $response->assertOk()->assertViewHas('startingModule', fn (LearningModule $starting): bool => $starting->is($module));
        expect($response->viewData('mission'))
            ->subject_id->toBe($module->subject_id)
            ->learning_module_id->toBe($module->id)
            ->grade_level->toBe(GradeLevel::Six);
    });
});

describe('store', function () {
    it('creates a mission for both grades', function () {
        $subject = Subject::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.missions.store'), missionPayload($subject))
            ->assertRedirectToRoute('admin.missions.index')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Mission “Breakfast Champion” created.');

        expect(Mission::query()->sole())
            ->subject_id->toBe($subject->id)
            ->learning_module_id->toBeNull()
            ->grade_level->toBeNull()
            ->type->toBe(MissionType::Weekly)
            ->difficulty->toBe(Difficulty::Medium)
            ->points_reward->toBe(150)
            ->starts_on->toDateString()->toBe('2026-11-16')
            ->ends_on->toDateString()->toBe('2026-11-20')
            ->is_active->toBeTrue();
    });

    it('creates a mission in a module of its subject and grade', function () {
        $module = LearningModule::factory()->create(['grade_level' => GradeLevel::Five]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.missions.store'), missionPayload($module->subject, ['learning_module_id' => $module->id, 'grade_level' => 5]))
            ->assertSessionHasNoErrors();

        expect(Mission::query()->sole())
            ->learning_module_id->toBe($module->id)
            ->grade_level->toBe(GradeLevel::Five);
    });

    it('accepts a one-day mission and open dates', function (array $dates) {
        $subject = Subject::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.missions.store'), missionPayload($subject, $dates))
            ->assertSessionHasNoErrors();

        expect(Mission::query()->count())->toBe(1);
    })->with([
        'same day' => [['starts_on' => '2026-11-16', 'ends_on' => '2026-11-16']],
        'no dates' => [['starts_on' => '', 'ends_on' => '']],
        'only an end date' => [['starts_on' => '', 'ends_on' => '2026-12-01']],
    ]);

    it('requires the mission fields', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.missions.store'), [])
            ->assertSessionHasErrors([
                'subject_id' => 'The subject field is required.',
                'title' => 'The title field is required.',
                'description' => 'The description field is required.',
                'objective' => 'The objective field is required.',
                'type' => 'The mission type field is required.',
                'difficulty' => 'The difficulty field is required.',
                'points_reward' => 'The reward points field is required.',
                'sort_order' => 'The display order field is required.',
                'is_active' => 'The active setting field is required.',
            ]);

        expect(Mission::query()->count())->toBe(0);
    });

    it('rejects invalid mission details', function (array $overrides, string $field, string $message) {
        $subject = Subject::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.missions.store'), missionPayload($subject, $overrides))
            ->assertSessionHasErrors([$field => $message]);

        expect(Mission::query()->count())->toBe(0);
    })->with([
        'unknown grade' => [['grade_level' => 7], 'grade_level', 'The selected grade level is invalid.'],
        'long title' => [['title' => str_repeat('a', 151)], 'title', 'The title field must not be greater than 150 characters.'],
        'long description' => [['description' => str_repeat('a', 2001)], 'description', 'The description field must not be greater than 2000 characters.'],
        'long objective' => [['objective' => str_repeat('a', 256)], 'objective', 'The objective field must not be greater than 255 characters.'],
        'unknown type' => [['type' => 'monthly'], 'type', 'The selected mission type is invalid.'],
        'unknown difficulty' => [['difficulty' => 'extreme'], 'difficulty', 'The selected difficulty is invalid.'],
        'negative points' => [['points_reward' => -1], 'points_reward', 'The reward points field must be between 0 and 10000.'],
        'too many points' => [['points_reward' => 10001], 'points_reward', 'The reward points field must be between 0 and 10000.'],
        'bad start date' => [['starts_on' => 'next week'], 'starts_on', 'The start date field must be a valid date.'],
        'ends before it starts' => [['starts_on' => '2026-11-16', 'ends_on' => '2026-11-15'], 'ends_on', 'The end date must be on or after the start date.'],
        'order too high' => [['sort_order' => 1000], 'sort_order', 'The display order field must be between 0 and 999.'],
        'not a boolean' => [['is_active' => 'sometimes'], 'is_active', 'The active setting field must be true or false.'],
    ]);

    it('rejects an inactive subject', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.missions.store'), missionPayload(Subject::factory()->inactive()->create()))
            ->assertSessionHasErrors(['subject_id' => 'Choose an active subject.']);
    });

    it('rejects a module of another subject', function () {
        $module = LearningModule::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.missions.store'), missionPayload(Subject::factory()->create(), ['learning_module_id' => $module->id]))
            ->assertSessionHasErrors(['learning_module_id' => 'Choose a module of the mission\'s subject.']);

        expect(Mission::query()->count())->toBe(0);
    });

    it('rejects a module of another grade', function () {
        $module = LearningModule::factory()->create(['grade_level' => GradeLevel::Six]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.missions.store'), missionPayload($module->subject, ['learning_module_id' => $module->id, 'grade_level' => 5]))
            ->assertSessionHasErrors(['learning_module_id' => "This module is for Grade 6. Choose a module for the mission's grade, or open the mission to both grades."]);

        expect(Mission::query()->count())->toBe(0);
    });
});

describe('edit', function () {
    it('shows the mission in the form', function () {
        $mission = Mission::factory()->create(['title' => 'Market Explorer']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.missions.edit', $mission))
            ->assertOk()
            ->assertViewIs('admin.missions.form')
            ->assertSeeText('Edit mission')
            ->assertSee('value="Market Explorer"', false);
    });
});

describe('update', function () {
    it('updates the mission', function () {
        $mission = Mission::factory()->create(['title' => 'Old title']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.missions.update', $mission), missionPayload($mission->subject, ['title' => 'Market Explorer', 'is_active' => '0', 'grade_level' => 6]))
            ->assertRedirectToRoute('admin.missions.index')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Mission updated.');

        expect($mission->refresh())
            ->title->toBe('Market Explorer')
            ->grade_level->toBe(GradeLevel::Six)
            ->is_active->toBeFalse();
    });

    it('keeps the mission\'s own inactive subject', function () {
        $subject = Subject::factory()->inactive()->create();
        $mission = Mission::factory()->for($subject)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.missions.update', $mission), missionPayload($subject))
            ->assertSessionHasNoErrors();

        expect($mission->refresh()->subject_id)->toBe($subject->id);
    });

    it('validates the update', function () {
        $mission = Mission::factory()->create(['points_reward' => 50]);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.missions.update', $mission), missionPayload($mission->subject, ['points_reward' => 20000]))
            ->assertSessionHasErrors(['points_reward' => 'The reward points field must be between 0 and 10000.']);

        expect($mission->refresh()->points_reward)->toBe(50);
    });
});

describe('destroy', function () {
    it('deletes the mission and its lesson plan alignments', function () {
        $mission = Mission::factory()->create(['title' => 'Water Warrior']);
        LessonPlan::factory()->create()->missions()->attach($mission);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.missions.destroy', $mission))
            ->assertRedirectToRoute('admin.missions.index')
            ->assertSessionHas('status', 'Mission “Water Warrior” deleted.');

        $this->assertModelMissing($mission);
        $this->assertDatabaseMissing('lesson_plan_mission', ['mission_id' => $mission->id]);
    });

    it('refuses to delete a mission learners started', function () {
        $mission = Mission::factory()->create();
        MissionProgress::factory()->for($mission)->inProgress()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.missions.destroy', $mission))
            ->assertRedirectToRoute('admin.missions.index')
            ->assertSessionHas('error', MissionController::HAS_PROGRESS_MESSAGE);

        $this->assertModelExists($mission);
    });
});
