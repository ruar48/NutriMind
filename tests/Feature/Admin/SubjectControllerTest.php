<?php

use App\Models\Classroom;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * A valid subject form payload.
 *
 * @return array<string, string>
 */
function setupSubjectPayload(array $overrides = []): array
{
    return [
        'name' => 'Health',
        'code' => 'HEALTH',
        'description' => 'Nutrition and healthy habits.',
        'color' => 'berry',
        'icon' => 'heart-pulse',
        'is_active' => '1',
        ...$overrides,
    ];
}

describe('index', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.subjects.index'))->assertRedirectToRoute('login');
    });

    it('forbids every other role', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('admin.subjects.index'))
            ->assertForbidden();
    })->with(['teacher', 'parent', 'student']);

    it('shows subject cards in name order with their usage counts', function () {
        $science = Subject::factory()->create(['name' => 'Science', 'code' => 'SCI']);
        $health = Subject::factory()->create(['name' => 'Health', 'code' => 'HEALTH', 'description' => 'Nutrition for Grade 5 and 6.']);
        Subject::factory()->inactive()->create(['name' => 'Values Education', 'code' => 'VE']);
        User::factory()->teaching($health)->count(2)->create();
        Classroom::factory()->for($health)->count(3)->create();
        LearningModule::factory()->for($health)->count(2)->create();
        Quiz::factory()->for($health)->create();
        Mission::factory()->for($science)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.subjects.index'));

        $response->assertOk()
            ->assertViewIs('admin.subjects.index')
            ->assertSeeInOrder(['Health', 'Science', 'Values Education'])
            ->assertSeeText('Nutrition for Grade 5 and 6.')
            ->assertSeeText('3 subjects')
            ->assertSeeText('2 active')
            ->assertSee('data-subject-card="'.$health->id.'"', false);

        $cards = $response->viewData('subjects')->getCollection()->keyBy('id');
        expect($cards[$health->id])
            ->teachers_count->toBe(6)
            ->classrooms_count->toBe(3)
            ->learning_modules_count->toBe(2)
            ->quizzes_count->toBe(1)
            ->and($cards[$science->id])->missions_count->toBe(1)->teachers_count->toBe(0);
    });

    it('offers deletion only for subjects nothing uses', function () {
        $used = Subject::factory()->create(['name' => 'Health']);
        Classroom::factory()->for($used)->create();
        $unused = Subject::factory()->create(['name' => 'Science']);

        $this->actingAs($this->admin)
            ->get(route('admin.subjects.index'))
            ->assertOk()
            ->assertSee('action="'.route('admin.subjects.destroy', $unused).'"', false)
            ->assertDontSee('action="'.route('admin.subjects.destroy', $used).'"', false)
            ->assertSeeText('In use · deactivate instead');
    });

    it('filters by name, code and status', function (array $query, array $visible) {
        Subject::factory()->create(['name' => 'Health', 'code' => 'HEALTH']);
        Subject::factory()->create(['name' => 'Science', 'code' => 'SCI']);
        Subject::factory()->inactive()->create(['name' => 'EPP – Home Economics', 'code' => 'EPP-HE']);

        $response = $this->actingAs($this->admin)->get(route('admin.subjects.index', $query));

        expect($response->viewData('subjects')->getCollection()->pluck('name')->all())->toBe($visible);
    })->with([
        'name' => [['search' => 'scien'], ['Science']],
        'code' => [['search' => 'epp-'], ['EPP – Home Economics']],
        'active' => [['status' => 'active'], ['Health', 'Science']],
        'inactive' => [['status' => 'inactive'], ['EPP – Home Economics']],
        'unknown status ignored' => [['status' => 'archived'], ['EPP – Home Economics', 'Health', 'Science']],
    ]);

    it('shows a call to action when there are no subjects', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.subjects.index'))
            ->assertOk()
            ->assertSeeText('No subjects yet')
            ->assertSee(route('admin.subjects.create'), false);
    });

    it('offers to clear the filters when nothing matches', function () {
        Subject::factory()->create(['name' => 'Health']);

        $this->actingAs($this->admin)
            ->get(route('admin.subjects.index', ['search' => 'Astronomy']))
            ->assertOk()
            ->assertSeeText('No subjects match your filters');
    });

    it('ignores filters that are not text', function () {
        Subject::factory()->count(2)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.subjects.index', ['search' => ['health'], 'status' => ['active']]));

        $response->assertOk();
        expect($response->viewData('subjects'))->toHaveCount(2);
    });

    it('paginates twelve subjects per page', function () {
        Subject::factory()->count(13)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.subjects.index', ['page' => 2]));

        expect($response->viewData('subjects'))->toHaveCount(1)
            ->and($response->viewData('subjects')->total())->toBe(13);
    });

    it('runs the same number of queries for more subjects', function () {
        Subject::factory()->count(2)->has(Classroom::factory()->count(2))->create();
        $this->actingAs($this->admin)->get(route('admin.subjects.index'))->assertOk();

        DB::enableQueryLog();
        $this->get(route('admin.subjects.index'))->assertOk();
        $fewer = count(DB::getQueryLog());
        Subject::factory()->count(5)->has(Classroom::factory()->count(2))->create();
        DB::flushQueryLog();
        $this->get(route('admin.subjects.index'))->assertOk();

        expect(count(DB::getQueryLog()))->toBe($fewer);
    });
});

describe('create', function () {
    it('shows the form with the curated colors and icons', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.subjects.create'))
            ->assertOk()
            ->assertViewIs('admin.subjects.form')
            ->assertSee('action="'.route('admin.subjects.store').'"', false)
            ->assertSee('value="heart-pulse"', false)
            ->assertSee('value="indigo"', false)
            ->assertSee('data-setup-subject-preview', false);
    });
});

describe('store', function () {
    it('creates a subject with an uppercase code', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.subjects.store'), setupSubjectPayload(['code' => ' epp-he ', 'name' => 'EPP – Home Economics', 'color' => 'carrot', 'icon' => 'utensils']))
            ->assertRedirectToRoute('admin.subjects.index')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'EPP – Home Economics was added.');

        $this->assertDatabaseHas('subjects', [
            'name' => 'EPP – Home Economics',
            'code' => 'EPP-HE',
            'description' => 'Nutrition and healthy habits.',
            'color' => 'carrot',
            'icon' => 'utensils',
            'is_active' => true,
        ]);
    });

    it('creates an inactive subject when the toggle is off', function () {
        $this->actingAs($this->admin)->post(route('admin.subjects.store'), setupSubjectPayload(['is_active' => '0', 'description' => '']));

        expect(Subject::query()->sole())->is_active->toBeFalse()->description->toBeNull();
    });

    it('requires the name, code, color and icon', function () {
        $this->actingAs($this->admin)
            ->post(route('admin.subjects.store'), [])
            ->assertSessionHasErrors([
                'name' => 'The subject name field is required.',
                'code' => 'The subject code field is required.',
                'color' => 'The color field is required.',
                'icon' => 'The icon field is required.',
            ]);

        expect(Subject::query()->exists())->toBeFalse();
    });

    it('rejects invalid subject details', function (array $payload, string $field, string $message) {
        Subject::factory()->create(['name' => 'Science', 'code' => 'SCI']);

        $this->actingAs($this->admin)
            ->post(route('admin.subjects.store'), setupSubjectPayload($payload))
            ->assertSessionHasErrors([$field => $message]);

        expect(Subject::query()->count())->toBe(1);
    })->with([
        'long name' => [['name' => str_repeat('a', 101)], 'name', 'The subject name field must not be greater than 100 characters.'],
        'taken name' => [['name' => 'Science'], 'name', 'Another subject already uses this name.'],
        'long code' => [['code' => str_repeat('A', 21)], 'code', 'The subject code field must not be greater than 20 characters.'],
        'taken code in lowercase' => [['code' => 'sci'], 'code', 'Another subject already uses this code.'],
        'code with spaces' => [['code' => 'HOME EC'], 'code', 'The subject code may only contain letters, numbers and hyphens.'],
        'code with symbols' => [['code' => 'H&E'], 'code', 'The subject code may only contain letters, numbers and hyphens.'],
        'long description' => [['description' => str_repeat('a', 501)], 'description', 'The description field must not be greater than 500 characters.'],
        'unknown color' => [['color' => 'magenta'], 'color', 'Choose one of the palette colors.'],
        'unknown icon' => [['icon' => 'skull'], 'icon', 'Choose one of the subject icons.'],
        'icon outside the subject list' => [['icon' => 'trash-2'], 'icon', 'Choose one of the subject icons.'],
        'non-boolean status' => [['is_active' => 'sometimes'], 'is_active', 'The active field must be true or false.'],
    ]);

    it('forbids every other role before validating', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->post(route('admin.subjects.store'), [])
            ->assertForbidden()
            ->assertSessionHasNoErrors();
    })->with(['teacher', 'parent']);
});

describe('edit', function () {
    it('shows the current values and what uses the subject', function () {
        $subject = Subject::factory()->create(['name' => 'Health', 'code' => 'HEALTH', 'color' => 'berry', 'icon' => 'heart-pulse']);
        Classroom::factory()->for($subject)->count(2)->create();
        Quiz::factory()->for($subject)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.subjects.edit', $subject));

        $response->assertOk()
            ->assertSee('action="'.route('admin.subjects.update', $subject).'"', false)
            ->assertSee('value="Health"', false)
            ->assertSee('value="HEALTH"', false)
            ->assertSeeText('2 classrooms')
            ->assertSeeText('1 quiz')
            ->assertSeeText('A subject in use can\'t be deleted. Deactivate it instead.')
            ->assertDontSee('name="_method" value="DELETE"', false);
        expect($response->getContent())
            ->toMatch('#<input[^>]*name="color"[^>]*value="berry"[^>]*\schecked(?=[\s/>])#')
            ->toMatch('#<input[^>]*name="icon"[^>]*value="heart-pulse"[^>]*\schecked(?=[\s/>])#');
    });

    it('offers deletion for an unused subject', function () {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.subjects.edit', $subject))
            ->assertOk()
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSeeText('Delete '.$subject->name.'?');
    });

    it('answers an unknown subject with 404', function () {
        $this->actingAs($this->admin)->get(route('admin.subjects.edit', 999999))->assertNotFound();
    });
});

describe('update', function () {
    it('updates the subject and can deactivate it', function () {
        $subject = Subject::factory()->create(['name' => 'Health', 'code' => 'HEALTH']);

        $this->actingAs($this->admin)
            ->put(route('admin.subjects.update', $subject), setupSubjectPayload(['name' => 'MAPEH Health', 'code' => 'mapeh-h', 'color' => 'teal', 'icon' => 'apple', 'is_active' => '0']))
            ->assertRedirectToRoute('admin.subjects.index')
            ->assertSessionHas('status', 'MAPEH Health was updated.');

        expect($subject->refresh())
            ->name->toBe('MAPEH Health')
            ->code->toBe('MAPEH-H')
            ->color->toBe('teal')
            ->icon->toBe('apple')
            ->is_active->toBeFalse();
    });

    it('keeps its own name and code valid', function () {
        $subject = Subject::factory()->create(['name' => 'Health', 'code' => 'HEALTH']);

        $this->actingAs($this->admin)
            ->put(route('admin.subjects.update', $subject), setupSubjectPayload())
            ->assertSessionHasNoErrors();
    });

    it('refuses the name or code of another subject', function (array $payload, string $field, string $message) {
        Subject::factory()->create(['name' => 'Science', 'code' => 'SCI']);
        $subject = Subject::factory()->create(['name' => 'Health', 'code' => 'HEALTH']);

        $this->actingAs($this->admin)
            ->put(route('admin.subjects.update', $subject), setupSubjectPayload($payload))
            ->assertSessionHasErrors([$field => $message]);

        expect($subject->refresh()->name)->toBe('Health');
    })->with([
        'name' => [['name' => 'Science'], 'name', 'Another subject already uses this name.'],
        'code' => [['code' => 'sci'], 'code', 'Another subject already uses this code.'],
    ]);

    it('forbids every other role', function (string $role) {
        $subject = Subject::factory()->create(['name' => 'Health']);

        $this->actingAs(User::factory()->{$role}()->create())
            ->put(route('admin.subjects.update', $subject), setupSubjectPayload(['name' => 'Renamed']))
            ->assertForbidden();

        expect($subject->refresh()->name)->toBe('Health');
    })->with(['teacher', 'parent']);
});

describe('destroy', function () {
    it('deletes an unused subject and its teacher assignments', function () {
        $subject = Subject::factory()->create(['name' => 'Values Education']);
        $teacher = User::factory()->teaching($subject)->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.subjects.destroy', $subject))
            ->assertRedirectToRoute('admin.subjects.index')
            ->assertSessionHas('status', 'Values Education was deleted.');

        $this->assertModelMissing($subject);
        $this->assertDatabaseMissing('subject_teacher', ['teacher_id' => $teacher->id]);
        $this->assertModelExists($teacher);
    });

    it('refuses to delete a subject that something uses', function (Closure $use, string $usage) {
        $subject = Subject::factory()->create(['name' => 'Health']);
        $use($subject);

        $this->actingAs($this->admin)
            ->from(route('admin.subjects.index'))
            ->delete(route('admin.subjects.destroy', $subject))
            ->assertRedirect(route('admin.subjects.index'))
            ->assertSessionHas('error', "Health is used by {$usage}. Deactivate it instead.");

        $this->assertModelExists($subject);
    })->with([
        'a classroom' => [fn (Subject $subject) => Classroom::factory()->for($subject)->create(), '1 classroom'],
        'modules' => [fn (Subject $subject) => LearningModule::factory()->for($subject)->count(2)->create(), '2 learning modules'],
        'a mission' => [fn (Subject $subject) => Mission::factory()->for($subject)->create(), '1 mission'],
        'a quiz' => [fn (Subject $subject) => Quiz::factory()->for($subject)->create(), '1 quiz'],
        'a bank item' => [fn (Subject $subject) => QuestionBankItem::factory()->for($subject)->create(), '1 question bank item'],
        'a lesson plan' => [fn (Subject $subject) => LessonPlan::factory()->for($subject)->create(), '1 lesson plan'],
        'several kinds' => [function (Subject $subject): void {
            Classroom::factory()->for($subject)->count(2)->create();
            Quiz::factory()->for($subject)->count(3)->create();
            LessonPlan::factory()->for($subject)->create();
        }, '2 classrooms, 3 quizzes and 1 lesson plan'],
    ]);

    it('forbids every other role', function (string $role) {
        $subject = Subject::factory()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->delete(route('admin.subjects.destroy', $subject))
            ->assertForbidden();

        $this->assertModelExists($subject);
    })->with(['teacher', 'parent']);
});
