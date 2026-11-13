<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\ForumThread;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * A complete, valid settings form payload.
 *
 * @return array<string, string>
 */
function setupSettingsPayload(array $overrides = []): array
{
    return [
        'school_name' => 'Mabini Elementary School',
        'school_id' => '136742',
        'school_address' => 'Apolinario Mabini Street, Marikina City',
        'school_head' => 'Dr. Erlinda M. Castillo',
        'school_email' => 'mabini.es@school.test',
        'school_phone' => '(02) 8941-2357',
        'default_passing_score' => '80',
        'default_max_attempts' => '3',
        'default_learning_mode' => 'learner_choice',
        'points_per_level' => '400',
        'max_level' => '60',
        'forum_enabled' => '1',
        'forum_requires_approval' => '0',
        'announcement' => 'Healthy Baon Week starts Monday!',
        ...$overrides,
    ];
}

describe('edit', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.settings.edit'))->assertRedirectToRoute('login');
    });

    it('forbids every other role', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('admin.settings.edit'))
            ->assertForbidden();
    })->with(['teacher', 'parent', 'student']);

    it('shows every section with the default values', function () {
        $response = $this->actingAs($this->admin)->get(route('admin.settings.edit'));

        $response->assertOk()
            ->assertViewIs('admin.settings.edit')
            ->assertSeeInOrder(['School profile', 'Academic defaults', 'Gamification', 'Parent forum', 'Announcement'])
            ->assertSee('value="NutriMind Elementary School"', false)
            ->assertSee('name="default_passing_score"', false)
            ->assertSee('value="75"', false)
            ->assertSee('value="500"', false)
            ->assertSee('value="50"', false)
            ->assertSeeText('Level 3')
            ->assertSeeText('24,500 XP');
        expect($response->getContent())
            ->toMatch('#<input[^>]*name="default_learning_mode"[^>]*value="automatic"[^>]*\schecked(?=[\s/>])#')
            ->toMatch('#<input[^>]*id="forum_enabled"[^>]*\schecked(?=[\s/>])#')
            ->not->toMatch('#<input[^>]*id="forum_requires_approval"[^>]*\schecked(?=[\s/>])#');
    });

    it('shows the saved values', function () {
        app(Settings::class)->setMany([
            'school_name' => 'Rizal Elementary School',
            'default_max_attempts' => null,
            'default_learning_mode' => 'manual',
            'points_per_level' => 250,
            'max_level' => 10,
            'forum_enabled' => false,
            'announcement' => 'Parent conference on Saturday.',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.settings.edit'));

        $response->assertOk()
            ->assertSee('value="Rizal Elementary School"', false)
            ->assertSeeText('Parent conference on Saturday.')
            ->assertSeeText('Level 6')
            ->assertSeeText('2,250 XP');
        expect($response->getContent())
            ->toMatch('#<input[^>]*name="default_learning_mode"[^>]*value="manual"[^>]*\schecked(?=[\s/>])#')
            ->not->toMatch('#<input[^>]*id="forum_enabled"[^>]*\schecked(?=[\s/>])#')
            ->not->toMatch('#<input[^>]*name="default_max_attempts"[^>]*value=#');
    });
});

describe('update', function () {
    it('saves every setting with its type', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), setupSettingsPayload())
            ->assertRedirectToRoute('admin.settings.edit')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Settings saved.');

        $settings = app(Settings::class);
        expect($settings->get('school_name'))->toBe('Mabini Elementary School')
            ->and($settings->get('school_id'))->toBe('136742')
            ->and($settings->get('school_address'))->toBe('Apolinario Mabini Street, Marikina City')
            ->and($settings->get('school_head'))->toBe('Dr. Erlinda M. Castillo')
            ->and($settings->get('school_email'))->toBe('mabini.es@school.test')
            ->and($settings->get('school_phone'))->toBe('(02) 8941-2357')
            ->and($settings->get('default_passing_score'))->toBe(80)
            ->and($settings->get('default_max_attempts'))->toBe(3)
            ->and($settings->get('default_learning_mode'))->toBe('learner_choice')
            ->and($settings->get('points_per_level'))->toBe(400)
            ->and($settings->get('max_level'))->toBe(60)
            ->and($settings->get('forum_enabled'))->toBeTrue()
            ->and($settings->get('forum_requires_approval'))->toBeFalse()
            ->and($settings->get('announcement'))->toBe('Healthy Baon Week starts Monday!');
        $this->assertDatabaseHas('settings', ['key' => 'default_passing_score']);
    });

    it('stores blank optional fields as empty values and unlimited attempts', function () {
        app(Settings::class)->set('announcement', 'Old news');

        $this->actingAs($this->admin)->put(route('admin.settings.update'), setupSettingsPayload([
            'school_id' => '',
            'school_address' => '',
            'school_head' => '',
            'school_email' => '',
            'school_phone' => '',
            'default_max_attempts' => '',
            'announcement' => '   ',
            'forum_enabled' => '0',
            'forum_requires_approval' => '1',
        ]))->assertSessionHasNoErrors();

        $settings = app(Settings::class);
        expect($settings->get('school_id'))->toBeNull()
            ->and($settings->get('school_address'))->toBeNull()
            ->and($settings->get('school_head'))->toBeNull()
            ->and($settings->get('school_email'))->toBeNull()
            ->and($settings->get('school_phone'))->toBeNull()
            ->and($settings->get('default_max_attempts'))->toBeNull()
            ->and($settings->get('announcement'))->toBeNull()
            ->and($settings->get('forum_enabled'))->toBeFalse()
            ->and($settings->get('forum_requires_approval'))->toBeTrue();
    });

    it('keeps the leading zeros of the school ID', function () {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), setupSettingsPayload(['school_id' => '0123456']));

        expect(app(Settings::class)->get('school_id'))->toBe('0123456');
    });

    it('shows the new school name and announcement across the portal', function () {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), setupSettingsPayload([
            'school_name' => 'Rizal Elementary School',
            'announcement' => 'Nutrition Month starts Monday!',
        ]));

        $this->get(route('admin.school-years.index'))
            ->assertOk()
            ->assertSeeText('Rizal Elementary School')
            ->assertSeeText('Nutrition Month starts Monday!');
    });

    it('turns the parent forum off for teachers and parents', function () {
        $teacher = User::factory()->teacher()->create();
        $parent = User::factory()->parent()->create();

        $this->actingAs($this->admin)->put(route('admin.settings.update'), setupSettingsPayload(['forum_enabled' => '0']));

        expect(Gate::forUser($teacher)->allows('viewAny', ForumThread::class))->toBeFalse()
            ->and(Gate::forUser($parent)->allows('viewAny', ForumThread::class))->toBeFalse()
            ->and(Gate::forUser($this->admin)->allows('viewAny', ForumThread::class))->toBeTrue();
    });

    it('requires the school name and the academic and gamification values', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), [])
            ->assertSessionHasErrors([
                'school_name' => 'The school name field is required.',
                'default_passing_score' => 'The passing score field is required.',
                'default_learning_mode' => 'The learning mode field is required.',
                'points_per_level' => 'The points per level field is required.',
                'max_level' => 'The highest level field is required.',
            ]);

        expect(app(Settings::class)->get('school_name'))->toBe('NutriMind Elementary School');
    });

    it('rejects invalid settings', function (array $payload, string $field, string $message) {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.update'), setupSettingsPayload($payload))
            ->assertSessionHasErrors([$field => $message]);

        expect(app(Settings::class)->get('school_name'))->toBe('NutriMind Elementary School');
    })->with([
        'long school name' => [['school_name' => str_repeat('a', 151)], 'school_name', 'The school name field must not be greater than 150 characters.'],
        'short school ID' => [['school_id' => '12345'], 'school_id', 'The DepEd School ID must be 6 to 10 digits.'],
        'long school ID' => [['school_id' => '12345678901'], 'school_id', 'The DepEd School ID must be 6 to 10 digits.'],
        'school ID with letters' => [['school_id' => '1367A2'], 'school_id', 'The DepEd School ID must be 6 to 10 digits.'],
        'long address' => [['school_address' => str_repeat('a', 256)], 'school_address', 'The school address field must not be greater than 255 characters.'],
        'long school head' => [['school_head' => str_repeat('a', 151)], 'school_head', 'The school head field must not be greater than 150 characters.'],
        'invalid email' => [['school_email' => 'not-an-email'], 'school_email', 'The school email field must be a valid email address.'],
        'long phone' => [['school_phone' => str_repeat('9', 31)], 'school_phone', 'The school phone field must not be greater than 30 characters.'],
        'passing score below 50' => [['default_passing_score' => '49'], 'default_passing_score', 'The passing score field must be between 50 and 100.'],
        'passing score above 100' => [['default_passing_score' => '101'], 'default_passing_score', 'The passing score field must be between 50 and 100.'],
        'passing score not whole' => [['default_passing_score' => '75.5'], 'default_passing_score', 'The passing score field must be an integer.'],
        'no attempts' => [['default_max_attempts' => '0'], 'default_max_attempts', 'The attempts allowed field must be between 1 and 10.'],
        'too many attempts' => [['default_max_attempts' => '11'], 'default_max_attempts', 'The attempts allowed field must be between 1 and 10.'],
        'unknown learning mode' => [['default_learning_mode' => 'random'], 'default_learning_mode', 'Choose one of the learning modes.'],
        'too few points per level' => [['points_per_level' => '49'], 'points_per_level', 'The points per level field must be between 50 and 10000.'],
        'too many points per level' => [['points_per_level' => '10001'], 'points_per_level', 'The points per level field must be between 50 and 10000.'],
        'highest level below 5' => [['max_level' => '4'], 'max_level', 'The highest level field must be between 5 and 200.'],
        'highest level above 200' => [['max_level' => '201'], 'max_level', 'The highest level field must be between 5 and 200.'],
        'non-boolean forum' => [['forum_enabled' => 'sometimes'], 'forum_enabled', 'The parent forum field must be true or false.'],
        'non-boolean approval' => [['forum_requires_approval' => 'maybe'], 'forum_requires_approval', 'The post approval field must be true or false.'],
        'long announcement' => [['announcement' => str_repeat('a', 501)], 'announcement', 'The announcement field must not be greater than 500 characters.'],
    ]);

    it('ignores keys that are not part of the form', function () {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), setupSettingsPayload([
            'role_permissions' => ['teacher' => []],
        ]));

        expect(app(Settings::class)->get('role_permissions'))->toBeNull()
            ->and(User::factory()->teacher()->create()->hasPermission(Permission::CreateQuizzes))->toBeTrue();
    });

    it('forbids every other role before validating', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->put(route('admin.settings.update'), ['school_name' => ''])
            ->assertForbidden()
            ->assertSessionHasNoErrors();
    })->with([UserRole::Teacher, UserRole::Parent]);

    it('sends guests to the sign-in page', function () {
        $this->put(route('admin.settings.update'), setupSettingsPayload())->assertRedirectToRoute('login');

        expect(app(Settings::class)->get('school_name'))->toBe('NutriMind Elementary School');
    });
});
