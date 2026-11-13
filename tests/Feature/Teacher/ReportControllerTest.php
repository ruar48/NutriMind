<?php

use App\Enums\Permission;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use App\Services\Reports\Report;
use App\Services\Reports\ReportRow;
use App\Services\RolePermissions;
use Carbon\CarbonImmutable;
use Tests\Feature\Services\Reports\ReportsFixture;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(ReportsFixture::NOW));
});

describe('index', function () {
    it('shows the reports teachers can generate', function () {
        $response = $this->actingAs(User::factory()->teaching()->create())->get(route('teacher.reports.index'));

        $response->assertOk()
            ->assertViewIs('teacher.reports.index')
            ->assertDontSee('data-report-card="user-accounts"', false)
            ->assertDontSee('data-report-export-denied', false)
            ->assertDontSee('data-report-no-subjects', false);

        foreach (array_filter(ReportType::cases(), fn (ReportType $type): bool => $type->availableToTeachers()) as $type) {
            $response->assertSee('data-report-card="'.$type->value.'"', false)
                ->assertSee(route('teacher.reports.show', $type), false)
                ->assertSee(route('teacher.reports.export', $type), false);
        }
    });

    it('hides the export actions and explains why without the export permission', function () {
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ExportReports);

        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.reports.index'))
            ->assertOk()
            ->assertSee('data-report-export-denied', false)
            ->assertDontSee(route('teacher.reports.export', ReportType::StudentPerformance), false)
            ->assertDontSee(route('teacher.reports.print', ReportType::StudentPerformance), false);
    });

    it('tells a teacher without subjects why the reports are empty', function () {
        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('teacher.reports.index'))
            ->assertOk()
            ->assertSee('data-report-no-subjects', false)
            ->assertSeeText('No subject is assigned to you yet. Ask your school administrator to assign one.');
    });

    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.reports.index'))->assertRedirectToRoute('login');
    });

    it('forbids the other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('teacher.reports.index'))
            ->assertForbidden();
    })->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);
});

describe('show', function () {
    it('previews every report for the teacher\'s classrooms', function (ReportType $type, string $own, string $others) {
        $school = ReportsFixture::create();

        $response = $this->actingAs($school->santos)->get(route('teacher.reports.show', $type));

        $response->assertOk()
            ->assertViewIs('teacher.reports.show')
            ->assertViewHas('reportType', $type)
            ->assertSeeText($own)
            ->assertDontSeeText($others)
            ->assertSee(route('teacher.reports.export', $type), false)
            ->assertSee(route('teacher.reports.print', $type), false)
            ->assertDontSee('data-report-card="user-accounts"', false)
            ->assertDontSee(route('admin.reports.show', ReportType::UserAccounts), false);
    })->with([
        'learner performance' => [ReportType::StudentPerformance, 'Ben Diaz', 'Carla Santos'],
        'quiz results' => [ReportType::QuizResults, 'Healthy Plate Quiz', 'Food Groups Quiz'],
        'mission progress' => [ReportType::MissionProgress, 'Rainbow Plate', 'Body Builders'],
        'classroom summary' => [ReportType::ClassroomSummary, 'Science 5 – Rizal', 'Health 6 – Mabini'],
    ]);

    it('hides the teacher column of quiz results from teachers', function () {
        $school = ReportsFixture::create();

        $this->actingAs($school->santos)
            ->get(route('teacher.reports.show', ReportType::QuizResults))
            ->assertOk()
            ->assertDontSee('data-column="teacher"', false)
            ->assertSee(route('teacher.quizzes.results.show', $school->healthyPlate), false);
    });

    it('offers only the teacher\'s classrooms and subjects in the filters', function () {
        $school = ReportsFixture::create();
        $otherSubject = Subject::factory()->create(['name' => 'Physical Education']);

        $this->actingAs($school->santos)
            ->get(route('teacher.reports.show', ReportType::StudentPerformance))
            ->assertOk()
            ->assertSee('<option value="'.$school->health5->id.'" >Health 5 – Rizal</option>', false)
            ->assertSee('<option value="'.$school->oldHealth5->id.'" >Health 5 – Old ('.$school->oldHealth5->schoolYear->name.')</option>', false)
            ->assertDontSee('>Health 6 – Mabini</option>', false)
            ->assertSee('<option value="'.$school->science->id.'" >Science</option>', false)
            ->assertDontSee('>'.$otherSubject->name.'</option>', false)
            ->assertDontSee('name="teacher_id"', false);
    });

    it('reports on the teacher\'s archived classroom when it is chosen', function () {
        $school = ReportsFixture::create();

        $this->actingAs($school->santos)
            ->get(route('teacher.reports.show', ['report' => 'classroom-summary', 'classroom_id' => $school->oldHealth5->id]))
            ->assertOk()
            ->assertViewHas('report', fn (Report $report): bool => $report->rows->map(fn (ReportRow $row): mixed => $row->value('classroom'))->all() === ['Health 5 – Old'])
            ->assertSeeInOrder(['data-report-row="1"', 'Health 5 – Old', 'Archived']);
    });

    it('rejects classrooms and subjects outside the teacher\'s access', function (Closure $query, string $field, string $message) {
        $school = ReportsFixture::create();
        $revoked = Classroom::factory()->for($school->santos, 'teacher')->revokedSubject()->create();

        $this->actingAs($school->santos)
            ->get(route('teacher.reports.show', ['report' => 'student-performance'] + $query($school, $revoked)))
            ->assertRedirect(route('teacher.reports.show', ReportType::StudentPerformance))
            ->assertSessionHasErrors([$field => $message]);
    })->with([
        'another teacher\'s classroom' => [fn (ReportsFixture $school): array => ['classroom_id' => $school->health6->id], 'classroom_id', 'The selected classroom is invalid.'],
        'own classroom of a revoked subject' => [fn (ReportsFixture $school, Classroom $revoked): array => ['classroom_id' => $revoked->id], 'classroom_id', 'The selected classroom is invalid.'],
        'unassigned subject' => [fn (ReportsFixture $school, Classroom $revoked): array => ['subject_id' => $revoked->subject_id], 'subject_id', 'The selected subject is invalid.'],
    ]);

    it('ignores a forged teacher filter', function () {
        $school = ReportsFixture::create();

        $this->actingAs($school->santos)
            ->get(route('teacher.reports.show', ['report' => 'student-performance', 'teacher_id' => $school->reyes->id]))
            ->assertOk()
            ->assertSeeText('Ana Cruz')
            ->assertDontSeeText('Carla Santos');
    });

    it('answers the user accounts report with 404', function () {
        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.reports.show', ReportType::UserAccounts))
            ->assertNotFound();
    });

    it('disables the export actions without the export permission', function () {
        $school = ReportsFixture::create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ExportReports);

        $this->actingAs($school->santos)
            ->get(route('teacher.reports.show', ReportType::StudentPerformance))
            ->assertOk()
            ->assertSee('data-report-export-denied', false)
            ->assertSee('aria-disabled="true"', false)
            ->assertDontSee('href="'.route('teacher.reports.export', ReportType::StudentPerformance).'"', false)
            ->assertSeeText('Ana Cruz');
    });

    it('shows the subject empty state to a teacher without subjects', function () {
        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('teacher.reports.show', ReportType::ClassroomSummary))
            ->assertOk()
            ->assertSeeText('No subject assigned yet')
            ->assertSee(route('teacher.subjects.index'), false);
    });

    it('shows an empty state with a next step when there is nothing to report', function (ReportType $type, string $title, string $route) {
        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.reports.show', $type))
            ->assertOk()
            ->assertSeeText($title)
            ->assertSee(route($route), false);
    })->with([
        'learner performance' => [ReportType::StudentPerformance, 'No learners in your active classrooms', 'teacher.classrooms.create'],
        'quiz results' => [ReportType::QuizResults, 'No published quizzes yet', 'teacher.quizzes.create'],
        'mission progress' => [ReportType::MissionProgress, 'No missions for your classrooms yet', 'teacher.missions.index'],
        'classroom summary' => [ReportType::ClassroomSummary, 'No active classrooms yet', 'teacher.classrooms.create'],
    ]);

    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.reports.show', ReportType::StudentPerformance))->assertRedirectToRoute('login');
    });

    it('forbids the other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('teacher.reports.show', ReportType::StudentPerformance))
            ->assertForbidden();
    })->with([UserRole::Admin, UserRole::Parent, UserRole::Student]);
});
