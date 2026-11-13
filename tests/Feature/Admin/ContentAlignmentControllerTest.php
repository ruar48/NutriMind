<?php

use App\Enums\GradeLevel;
use App\Enums\UserRole;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\Subject;
use App\Models\User;
use App\Services\Content\CompetencyCoverage;
use App\Services\Content\CoverageStatus;
use Illuminate\Support\Facades\DB;

/**
 * Create one covered, one partial and one not-covered competency in a subject.
 *
 * @return array{covered: LearningModule, partial: LearningModule}
 */
function contentAlignmentScenario(Subject $subject): array
{
    LessonPlan::factory()->for($subject)->count(2)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IA-1', 'learning_competency' => 'Classifies foods into Go, Grow and Glow groups.']);
    $covered = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IA-1', 'title' => 'Go, Grow and Glow Foods']);
    LearningActivity::factory()->for($covered, 'module')->count(3)->create();
    Mission::factory()->for($covered, 'module')->create();

    LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IB-2']);
    $partial = LearningModule::factory()->for($subject)->unpublished()->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IB-2', 'title' => 'The Balanced Plate']);

    LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Six, 'competency_code' => 'H6N-IIC-4', 'learning_competency' => 'Reads the Nutrition Facts label.']);

    return ['covered' => $covered, 'partial' => $partial];
}

it('sends guests to the sign-in page', function () {
    $this->get(route('admin.content-alignment.index'))->assertRedirectToRoute('login');
});

it('forbids the other portals', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('admin.content-alignment.index'))
        ->assertForbidden();
})->with([UserRole::Teacher, UserRole::Parent]);

it('shows every planned competency with its coverage', function () {
    $subject = Subject::factory()->create(['name' => 'Health']);
    ['covered' => $covered, 'partial' => $partial] = contentAlignmentScenario($subject);
    LessonPlan::factory()->for($subject)->create(['competency_code' => null]);

    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.content-alignment.index'));

    $response->assertOk()
        ->assertViewIs('admin.content-alignment.index')
        ->assertViewHas('competencyCount', 3)
        ->assertViewHas('plannedLessonCount', 4)
        ->assertViewHas('unmappedPlans', 1)
        ->assertViewHas('statusCounts', ['covered' => 1, 'partial' => 1, 'not_covered' => 1])
        ->assertSeeTextInOrder(['H5N-IA-1', 'H5N-IB-2', 'H6N-IIC-4'])
        ->assertSeeText('Classifies foods into Go, Grow and Glow groups.')
        ->assertSeeText('Go, Grow and Glow Foods')
        ->assertSeeText('2 plans')
        ->assertSeeText('1 lesson plan cannot be matched to game content until its teacher adds a competency code.')
        ->assertSee('data-coverage-status="covered"', false)
        ->assertSee('data-coverage-status="partial"', false)
        ->assertSee('data-coverage-status="not_covered"', false)
        ->assertSee(e(route('admin.modules.show', $covered)), false)
        ->assertSee(e(route('admin.modules.show', $partial)), false);

    $rows = $response->viewData('competencies')->keyBy('code');
    expect($rows['H5N-IA-1'])
        ->publishedActivitiesCount->toBe(3)
        ->missionsCount->toBe(1)
        ->status->toBe(CoverageStatus::Covered);
});

it('links a not-covered competency to a prefilled new module', function () {
    $subject = Subject::factory()->create();
    LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Six, 'competency_code' => 'H6N-IIC-4', 'learning_competency' => 'Reads the Nutrition Facts label.']);

    $url = route('admin.modules.create', [
        'subject_id' => $subject->id,
        'grade_level' => 6,
        'competency_code' => 'H6N-IIC-4',
        'learning_competency' => 'Reads the Nutrition Facts label.',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.content-alignment.index'))
        ->assertOk()
        ->assertSeeText('Create module')
        ->assertSee(e($url), false);
});

it('filters by subject, grade, status and search', function (array $query, array $expected) {
    $subject = Subject::factory()->create();
    contentAlignmentScenario($subject);
    LessonPlan::factory()->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'S5LT-IIA-1']);

    $query = array_map(fn (mixed $value): mixed => $value === 'subject' ? $subject->id : $value, $query);
    $codes = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.content-alignment.index', $query))
        ->assertOk()
        ->viewData('competencies')
        ->map(fn (CompetencyCoverage $coverage): string => $coverage->code)
        ->all();

    expect($codes)->toBe($expected);
})->with([
    'subject' => [['subject' => 'subject'], ['H5N-IA-1', 'H5N-IB-2', 'H6N-IIC-4']],
    'grade' => [['subject' => 'subject', 'grade' => '6'], ['H6N-IIC-4']],
    'covered' => [['status' => 'covered'], ['H5N-IA-1']],
    'partial' => [['status' => 'partial'], ['H5N-IB-2']],
    'not covered' => [['subject' => 'subject', 'status' => 'not_covered'], ['H6N-IIC-4']],
    'search by code' => [['search' => 's5lt'], ['S5LT-IIA-1']],
    'search by competency' => [['search' => 'nutrition facts'], ['H6N-IIC-4']],
]);

it('counts the statuses without the status filter', function () {
    contentAlignmentScenario(Subject::factory()->create());

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.content-alignment.index', ['status' => 'covered']))
        ->assertOk()
        ->assertViewHas('statusCounts', ['covered' => 1, 'partial' => 1, 'not_covered' => 1]);
});

it('paginates twenty competencies per page', function () {
    $subject = Subject::factory()->create();
    foreach (range(1, 21) as $number) {
        LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => sprintf('H5N-IA-%02d', $number)]);
    }

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.content-alignment.index', ['page' => 2]))
        ->assertOk()
        ->assertViewHas('competencies', fn ($competencies): bool => $competencies->total() === 21
            && $competencies->count() === 1
            && $competencies->first()->code === 'H5N-IA-21');
});

it('renders more competencies without more queries', function () {
    $admin = User::factory()->admin()->create();
    $queries = function () use ($admin): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.content-alignment.index'))->assertOk();

        return count(DB::getQueryLog());
    };
    contentAlignmentScenario(Subject::factory()->create());
    $queries();
    $single = $queries();

    contentAlignmentScenario(Subject::factory()->create());
    contentAlignmentScenario(Subject::factory()->create());

    expect($queries())->toBe($single);
});

it('explains the page when no lesson plan has a code yet', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.content-alignment.index'))
        ->assertOk()
        ->assertSeeText('No planned competencies yet');
});

it('explains an empty filtered list', function () {
    LessonPlan::factory()->create(['competency_code' => 'H5N-IA-1']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.content-alignment.index', ['search' => 'zzz']))
        ->assertOk()
        ->assertSeeText('No competencies match your filters');
});
