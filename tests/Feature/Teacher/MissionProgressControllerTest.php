<?php

use App\Enums\GradeLevel;
use App\Enums\MissionType;
use App\Enums\ProgressStatus;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A Health teacher with Grade 5 Sampaguita (3 learners) and Grade 6 Narra (2 learners), and three Health missions:
 * Rainbow Plate (Grade 5), Water Warrior (both grades) and Label Reader (Grade 6).
 *
 * Progress: Ana completed Rainbow Plate, Ben is playing it, Gina (Grade 6) completed Water Warrior, and a learner of
 * another teacher completed Rainbow Plate.
 *
 * @return array{teacher: User, subject: Subject, grade5: Classroom, grade6: Classroom, rainbow: Mission, water: Mission, label: Mission, learners: array<string, User>}
 */
function missionPagesSetup(): array
{
    $subject = Subject::factory()->create(['name' => 'Health']);
    $teacher = User::factory()->teaching($subject)->create();
    $grade5 = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'name' => 'Health 5 – Sampaguita']);
    $grade6 = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Six, 'name' => 'Health 6 – Narra']);

    $learners = [];
    foreach (['Ana Cruz', 'Ben Reyes', 'Carlo Diaz'] as $name) {
        $learners[$name] = User::factory()->student(GradeLevel::Five)->enrolledIn($grade5)->create(['name' => $name]);
    }
    foreach (['Gina Lopez', 'Hugo Ramos'] as $name) {
        $learners[$name] = User::factory()->student(GradeLevel::Six)->enrolledIn($grade6)->create(['name' => $name]);
    }

    $rainbow = Mission::factory()->for($subject)->create(['title' => 'Rainbow Plate', 'grade_level' => GradeLevel::Five, 'type' => MissionType::Main, 'sort_order' => 1]);
    $water = Mission::factory()->for($subject)->create(['title' => 'Water Warrior', 'grade_level' => null, 'type' => MissionType::Daily, 'sort_order' => 2]);
    $label = Mission::factory()->for($subject)->create(['title' => 'Label Reader', 'grade_level' => GradeLevel::Six, 'type' => MissionType::Challenge, 'sort_order' => 3]);

    MissionProgress::factory()->completed()->for($rainbow)->for($learners['Ana Cruz'], 'student')->create();
    MissionProgress::factory()->inProgress()->for($rainbow)->for($learners['Ben Reyes'], 'student')->create(['progress_percent' => 40]);
    MissionProgress::factory()->completed()->for($water)->for($learners['Gina Lopez'], 'student')->create();
    $outsider = User::factory()->student(GradeLevel::Five)->enrolledIn(Classroom::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]))->create();
    MissionProgress::factory()->completed()->for($rainbow)->for($outsider, 'student')->create();

    return compact('teacher', 'subject', 'grade5', 'grade6', 'rainbow', 'water', 'label', 'learners');
}

describe('index', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.missions.index'))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('teacher.missions.index'))
            ->assertForbidden();
    })->with([
        'administrator' => UserRole::Admin,
        'parent' => UserRole::Parent,
    ]);

    it('lists the active missions of the teacher’s subjects with each mission’s completion', function () {
        $data = missionPagesSetup();
        Mission::factory()->for($data['subject'])->inactive()->create(['title' => 'Draft Mission']);
        Mission::factory()->create(['title' => 'Other Subject Mission']);

        $response = $this->actingAs($data['teacher'])->get(route('teacher.missions.index'));

        $response->assertOk()
            ->assertViewIs('teacher.missions.index')
            ->assertSeeTextInOrder(['Rainbow Plate', 'Water Warrior', 'Label Reader'])
            ->assertDontSeeText('Draft Mission')
            ->assertDontSeeText('Other Subject Mission');
        $completion = $response->viewData('completion');
        expect([$completion[$data['rainbow']->id]->eligible, $completion[$data['rainbow']->id]->completed, $completion[$data['rainbow']->id]->inProgress])->toBe([3, 1, 1])
            ->and($completion[$data['rainbow']->id]->rate())->toBe(33.3)
            ->and([$completion[$data['water']->id]->eligible, $completion[$data['water']->id]->completed, $completion[$data['water']->id]->inProgress])->toBe([5, 1, 0])
            ->and([$completion[$data['label']->id]->eligible, $completion[$data['label']->id]->completed])->toBe([2, 0]);
    });

    it('shows each classroom’s completion over the missions its learners can play', function () {
        $data = missionPagesSetup();

        $response = $this->actingAs($data['teacher'])->get(route('teacher.missions.index'));

        $rows = collect($response->viewData('classroomCompletion'))->keyBy(fn (array $row): string => $row['classroom']->name);
        expect($rows['Health 5 – Sampaguita']['missions'])->toBe(2)
            ->and($rows['Health 5 – Sampaguita']['completion']->eligible)->toBe(6)
            ->and($rows['Health 5 – Sampaguita']['completion']->completed)->toBe(1)
            ->and($rows['Health 5 – Sampaguita']['completion']->rate())->toBe(16.7)
            ->and($rows['Health 6 – Narra']['completion']->eligible)->toBe(4)
            ->and($rows['Health 6 – Narra']['completion']->rate())->toBe(25.0);
        $response->assertSee('data-chart="hbar"', false)->assertSeeText('1 of 6 learner missions completed');
    });

    it('limits missions and counts to the chosen classroom', function () {
        $data = missionPagesSetup();

        $response = $this->actingAs($data['teacher'])->get(route('teacher.missions.index', ['classroom' => $data['grade6']->id]));

        $response->assertOk()
            ->assertSeeText('Water Warrior')
            ->assertSeeText('Label Reader')
            ->assertDontSeeText('Rainbow Plate');
        expect($response->viewData('completion')[$data['water']->id]->eligible)->toBe(2)
            ->and($response->viewData('completion')[$data['water']->id]->completed)->toBe(1);
    });

    it('reports a classroom outside the teacher’s active classrooms as missing', function () {
        $data = missionPagesSetup();

        $this->actingAs($data['teacher'])
            ->get(route('teacher.missions.index', ['classroom' => Classroom::factory()->for($data['subject'])->create()->id]))
            ->assertNotFound();
    });

    it('filters by mission type and title', function (array $query, array $expected) {
        $data = missionPagesSetup();

        $response = $this->actingAs($data['teacher'])->get(route('teacher.missions.index', $query));

        expect($response->viewData('missions')->pluck('title')->all())->toBe($expected);
    })->with([
        'type' => [['type' => 'daily'], ['Water Warrior']],
        'title' => [['search' => 'label'], ['Label Reader']],
        'unknown type is ignored' => [['type' => 'boss'], ['Rainbow Plate', 'Water Warrior', 'Label Reader']],
    ]);

    it('explains an empty filtered list', function () {
        $data = missionPagesSetup();

        $this->actingAs($data['teacher'])
            ->get(route('teacher.missions.index', ['search' => 'Nothing like this']))
            ->assertOk()
            ->assertSeeText('No missions match your filters');
    });

    it('explains an empty list without missions', function () {
        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.missions.index'))
            ->assertOk()
            ->assertSeeText('No active missions yet');
    });

    it('paginates 15 missions per page', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        foreach (range(1, 16) as $number) {
            Mission::factory()->for($subject)->create(['title' => sprintf('Mission %02d', $number), 'sort_order' => $number]);
        }

        $response = $this->actingAs($teacher)->get(route('teacher.missions.index', ['page' => 2]));

        expect($response->viewData('missions')->pluck('title')->all())->toBe(['Mission 16']);
    });

    it('shows when each dated mission runs', function (?string $startsOn, ?string $endsOn, string $expected) {
        $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        Mission::factory()->for($subject)->create(['starts_on' => $startsOn, 'ends_on' => $endsOn]);

        $this->actingAs($teacher)
            ->get(route('teacher.missions.index'))
            ->assertOk()
            ->assertSeeText($expected);
    })->with([
        'ended' => ['2026-10-12', '2026-11-06', 'Ended Nov 6, 2026'],
        'ends today' => ['2026-11-09', '2026-11-13', 'Running until Nov 13, 2026'],
        'upcoming' => ['2026-11-23', '2026-11-27', 'Starts Nov 23, 2026'],
        'open-ended' => ['2026-11-01', null, 'Running since Nov 1, 2026'],
        'no start date' => [null, '2026-11-20', 'Running until Nov 20, 2026'],
    ]);

    it('shows no schedule for an undated mission', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        Mission::factory()->for($subject)->create(['starts_on' => null, 'ends_on' => null]);

        $this->actingAs($teacher)
            ->get(route('teacher.missions.index'))
            ->assertOk()
            ->assertDontSeeText('Running')
            ->assertDontSeeText('Ended')
            ->assertDontSeeText('Starts');
    });
});

describe('show', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.missions.show', Mission::factory()->create()))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('teacher.missions.show', Mission::factory()->create()))
            ->assertForbidden();
    })->with([
        'administrator' => UserRole::Admin,
        'parent' => UserRole::Parent,
    ]);

    it('reports missions of subjects the teacher does not teach as missing', function () {
        $data = missionPagesSetup();

        $this->actingAs($data['teacher'])
            ->get(route('teacher.missions.show', Mission::factory()->create()))
            ->assertNotFound();
    });

    it('reports a mission as missing after its subject is revoked', function () {
        $data = missionPagesSetup();
        $data['teacher']->assignedSubjects()->detach($data['subject']);

        $this->actingAs($data['teacher']->refresh())
            ->get(route('teacher.missions.show', $data['rainbow']))
            ->assertNotFound();
    });

    it('lists every eligible learner with their status on the mission', function () {
        $data = missionPagesSetup();

        $response = $this->actingAs($data['teacher'])->get(route('teacher.missions.show', $data['rainbow']));

        $response->assertOk()
            ->assertViewIs('teacher.missions.show')
            ->assertSeeTextInOrder(['Ana Cruz', 'Ben Reyes', 'Carlo Diaz'])
            ->assertDontSeeText('Gina Lopez')
            ->assertSee('data-status="completed"', false)
            ->assertSee('data-status="in_progress"', false)
            ->assertSee('data-status="not_started"', false);
        $completion = $response->viewData('completion');
        expect([$completion->eligible, $completion->completed, $completion->inProgress, $completion->notStarted()])->toBe([3, 1, 1, 1]);
    });

    it('filters learners by status', function (ProgressStatus $status, array $expected) {
        $data = missionPagesSetup();

        $response = $this->actingAs($data['teacher'])->get(route('teacher.missions.show', [$data['rainbow'], 'status' => $status->value]));

        expect($response->viewData('learners')->pluck('name')->all())->toBe($expected);
    })->with([
        'completed' => [ProgressStatus::Completed, ['Ana Cruz']],
        'in progress' => [ProgressStatus::InProgress, ['Ben Reyes']],
        'not started' => [ProgressStatus::NotStarted, ['Carlo Diaz']],
    ]);

    it('limits a mission open to both grades to the chosen classroom', function () {
        $data = missionPagesSetup();

        $response = $this->actingAs($data['teacher'])->get(route('teacher.missions.show', [$data['water'], 'classroom' => $data['grade6']->id]));

        expect($response->viewData('learners')->pluck('name')->all())->toBe(['Gina Lopez', 'Hugo Ramos'])
            ->and($response->viewData('completion')->eligible)->toBe(2)
            ->and($response->viewData('completion')->completed)->toBe(1);
    });

    it('reports a classroom that cannot play the mission as missing', function () {
        $data = missionPagesSetup();

        $this->actingAs($data['teacher'])
            ->get(route('teacher.missions.show', [$data['rainbow'], 'classroom' => $data['grade6']->id]))
            ->assertNotFound();
    });

    it('explains when none of the teacher’s classrooms can play the mission', function () {
        $subject = Subject::factory()->create(['name' => 'Health']);
        $teacher = User::factory()->teaching($subject)->create();
        Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $mission = Mission::factory()->for($subject)->create(['grade_level' => GradeLevel::Six]);

        $this->actingAs($teacher)
            ->get(route('teacher.missions.show', $mission))
            ->assertOk()
            ->assertSeeText('None of your classrooms can play this mission');
    });

    it('shows an inactive mission with a notice', function () {
        $data = missionPagesSetup();
        $data['rainbow']->update(['is_active' => false]);

        $this->actingAs($data['teacher'])
            ->get(route('teacher.missions.show', $data['rainbow']))
            ->assertOk()
            ->assertSeeText('Not active in the game');
    });

    it('explains an empty filtered list', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        User::factory()->student(GradeLevel::Five)->enrolledIn($classroom)->create();
        $mission = Mission::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);

        $this->actingAs($teacher)
            ->get(route('teacher.missions.show', [$mission, 'status' => 'completed']))
            ->assertOk()
            ->assertSeeText('No learners match your filters');
    });
});
