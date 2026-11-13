<?php

use App\Enums\GradeLevel;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Reports\Report;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Services\Reports\ReportsFixture;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(ReportsFixture::NOW));
});

describe('index', function () {
    it('shows a card for every report with its actions', function () {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.index'));

        $response->assertOk()
            ->assertViewIs('admin.reports.index')
            ->assertSeeText('Reports & export')
            ->assertSee('data-report-definitions', false);

        foreach (ReportType::cases() as $type) {
            $response->assertSee('data-report-card="'.$type->value.'"', false)
                ->assertSeeText($type->label())
                ->assertSee(route('admin.reports.show', $type), false)
                ->assertSee(route('admin.reports.export', $type), false)
                ->assertSee(route('admin.reports.print', $type), false);
        }
    });

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.reports.index'))->assertRedirectToRoute('login');
    });

    it('forbids the other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.reports.index'))
            ->assertForbidden();
    })->with([UserRole::Teacher, UserRole::Parent, UserRole::Student]);
});

describe('show', function () {
    it('previews every report with its summary, rows and export actions', function (ReportType $type, string $firstRow) {
        ReportsFixture::create();

        $response = $this->actingAs(User::factory()->admin()->create(['name' => 'Josefina Ramos']))->get(route('admin.reports.show', $type));

        $response->assertOk()
            ->assertViewIs('admin.reports.show')
            ->assertViewHas('reportType', $type)
            ->assertViewHas('report', fn (Report $report): bool => $report->type === $type)
            ->assertViewHas('rows', fn (LengthAwarePaginator $rows): bool => $rows->perPage() === 25)
            ->assertSee('data-report-summary', false)
            ->assertSee('data-report-filters', false)
            ->assertSeeText($firstRow)
            ->assertSee(route('admin.reports.export', $type), false)
            ->assertSee(route('admin.reports.print', $type), false);
    })->with([
        'learner performance' => [ReportType::StudentPerformance, 'Ana Cruz'],
        'quiz results' => [ReportType::QuizResults, 'Food Groups Quiz'],
        'mission progress' => [ReportType::MissionProgress, 'Rainbow Plate'],
        'classroom summary' => [ReportType::ClassroomSummary, 'Health 5 – Rizal'],
        'user accounts' => [ReportType::UserAccounts, 'Josefina Ramos'],
    ]);

    it('shows the learner figures in the preview table', function () {
        $school = ReportsFixture::create();

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports.show', ReportType::StudentPerformance));

        $response->assertOk()
            ->assertSeeInOrder(['Ana Cruz', '136742210001', 'Grade 5', 'Health 5 – Rizal, Science 5 – Rizal', '85%', '90%', '80%', '1,240', 'Very Satisfactory'])
            ->assertSee(route('admin.students.show', $school->ana), false)
            ->assertSee('data-count-up="83.3"', false)
            ->assertSee('data-report-row-count="3"', false)
            ->assertSee('data-chart="stacked"', false);
    });

    it('shows the teacher column of quiz results to administrators', function () {
        ReportsFixture::create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.reports.show', ReportType::QuizResults))
            ->assertOk()
            ->assertSee('data-column="teacher"', false)
            ->assertSeeText('Lorna Reyes');
    });

    it('keeps the filters in the export and print links', function () {
        $school = ReportsFixture::create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.reports.show', ['report' => ReportType::StudentPerformance->value, 'classroom_id' => $school->science5->id, 'from' => '2026-11-01']));

        $response->assertOk()
            ->assertSee(e(route('admin.reports.export', ['report' => 'student-performance', 'classroom_id' => $school->science5->id, 'from' => '2026-11-01'])), false)
            ->assertSee(e(route('admin.reports.print', ['report' => 'student-performance', 'classroom_id' => $school->science5->id, 'from' => '2026-11-01'])), false)
            ->assertSeeText('Ana Cruz')
            ->assertDontSeeText('Ben Diaz');
    });

    it('offers every classroom, subject and teacher in the filters', function () {
        $school = ReportsFixture::create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.reports.show', ReportType::ClassroomSummary))
            ->assertOk()
            ->assertSee('<optgroup label="Archived classrooms">', false)
            ->assertSee('<option value="'.$school->oldHealth5->id.'" >Health 5 – Old ('.$school->oldHealth5->schoolYear->name.')</option>', false)
            ->assertSee('<option value="'.$school->health6->id.'" >Health 6 – Mabini</option>', false)
            ->assertSee('<option value="'.$school->reyes->id.'" >Lorna Reyes</option>', false)
            ->assertSee('name="teacher_id"', false);
    });

    it('offers the role and status filters on the user accounts report', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.reports.show', ReportType::UserAccounts))
            ->assertOk()
            ->assertSee('name="role"', false)
            ->assertSee('name="status"', false)
            ->assertDontSee('name="classroom_id"', false)
            ->assertSeeText('Created from');
    });

    it('paginates the preview 25 rows at a time and keeps the filters in the page links', function () {
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
        User::factory()->student(GradeLevel::Five)->enrolledIn($classroom)->count(27)->create();

        $first = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.reports.show', ['report' => 'student-performance', 'grade_level' => 5]));
        $second = $this->get(route('admin.reports.show', ['report' => 'student-performance', 'grade_level' => 5, 'page' => 2]));
        $beyond = $this->get(route('admin.reports.show', ['report' => 'student-performance', 'grade_level' => 5, 'page' => 9]));

        expect(preg_match_all('/data-report-row="\d+"/', $first->getContent()))->toBe(25)
            ->and(preg_match_all('/data-report-row="\d+"/', $second->getContent()))->toBe(2)
            ->and($beyond->viewData('rows')->currentPage())->toBe(2);
        $first->assertSee('grade_level=5&amp;page=2', false);
    });

    it('applies the filters to the preview', function () {
        $school = ReportsFixture::create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.reports.show', ['report' => 'quiz-results', 'teacher_id' => $school->reyes->id]))
            ->assertOk()
            ->assertSeeText('Food Groups Quiz')
            ->assertDontSeeText('Healthy Plate Quiz');
    });

    it('rejects invalid filters and returns to the unfiltered report', function (ReportType $type, Closure $query, string $field, string $message) {
        $admin = User::factory()->admin()->create();
        $parent = User::factory()->parent()->create();

        $response = $this->actingAs($admin)->get(route('admin.reports.show', ['report' => $type->value] + $query($parent)));

        $response->assertRedirect(route('admin.reports.show', $type))
            ->assertSessionHasErrors([$field => $message]);
    })->with([
        'unknown classroom' => [ReportType::StudentPerformance, fn (): array => ['classroom_id' => 999999], 'classroom_id', 'The selected classroom is invalid.'],
        'non-numeric classroom' => [ReportType::StudentPerformance, fn (): array => ['classroom_id' => 'abc'], 'classroom_id', 'The classroom field must be an integer.'],
        'unknown subject' => [ReportType::QuizResults, fn (): array => ['subject_id' => 999999], 'subject_id', 'The selected subject is invalid.'],
        'unknown grade' => [ReportType::MissionProgress, fn (): array => ['grade_level' => 7], 'grade_level', 'The selected grade level is invalid.'],
        'account that is not a teacher' => [ReportType::ClassroomSummary, fn (User $parent): array => ['teacher_id' => $parent->id], 'teacher_id', 'The selected teacher is invalid.'],
        'malformed start date' => [ReportType::StudentPerformance, fn (): array => ['from' => '11/01/2026'], 'from', 'Enter the start date as a valid date.'],
        'malformed end date' => [ReportType::StudentPerformance, fn (): array => ['to' => 'yesterday'], 'to', 'Enter the end date as a valid date.'],
        'end before start' => [ReportType::QuizResults, fn (): array => ['from' => '2026-11-10', 'to' => '2026-11-01'], 'to', 'The end date must be on or after the start date.'],
        'unknown role' => [ReportType::UserAccounts, fn (): array => ['role' => 'superuser'], 'role', 'The selected role is invalid.'],
        'unknown status' => [ReportType::UserAccounts, fn (): array => ['status' => 'archived'], 'status', 'The selected status is invalid.'],
    ]);

    it('explains rejected filters on the report it returns to', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->followingRedirects()
            ->get(route('admin.reports.show', ['report' => 'student-performance', 'classroom_id' => 999999]))
            ->assertOk()
            ->assertSee('data-report-filter-errors', false)
            ->assertSeeText('Some filters were not valid')
            ->assertSeeText('The selected classroom is invalid.');
    });

    it('ignores filters that do not apply to the report', function () {
        $admin = User::factory()->admin()->create(['name' => 'Josefina Ramos']);

        $this->actingAs($admin)
            ->get(route('admin.reports.show', ['report' => 'user-accounts', 'classroom_id' => 'abc']))
            ->assertOk()
            ->assertSeeText('Josefina Ramos');
    });

    it('shows an empty state without data', function (ReportType $type, string $title) {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.reports.show', $type))
            ->assertOk()
            ->assertSeeText($title)
            ->assertDontSee('data-report-row="1"', false);
    })->with([
        'learner performance' => [ReportType::StudentPerformance, 'No learners in active classrooms'],
        'quiz results' => [ReportType::QuizResults, 'No data to report on yet'],
        'mission progress' => [ReportType::MissionProgress, 'No missions to report on'],
        'classroom summary' => [ReportType::ClassroomSummary, 'No data to report on yet'],
    ]);

    it('shows a reset action when the filters match nothing', function () {
        ReportsFixture::create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.reports.show', ['report' => 'quiz-results', 'from' => '2026-12-01']))
            ->assertOk()
            ->assertSeeText('Nothing matches these filters')
            ->assertSeeText('Reset filters');
    });

    it('keeps the number of queries flat as the report grows', function () {
        $school = ReportsFixture::create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.reports.show', ReportType::StudentPerformance))->assertOk();

        $count = function (ReportType $type) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('admin.reports.show', $type))->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $before = collect(ReportType::cases())->mapWithKeys(fn (ReportType $type): array => [$type->value => $count($type)]);

        $learners = User::factory()->student(GradeLevel::Five)->enrolledIn($school->health5, $school->science5)->count(6)->create();
        $quiz = Quiz::factory()->published()->for($school->santos, 'teacher')->for($school->health)->create(['grade_level' => GradeLevel::Five]);
        $learners->each(fn (User $learner) => QuizAttempt::factory()->for($quiz)->for($learner, 'student')->for($school->health5)->create());

        expect(collect(ReportType::cases())->mapWithKeys(fn (ReportType $type): array => [$type->value => $count($type)]))->toEqual($before);
    });

    it('answers an unknown report with 404', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/reports/attendance')
            ->assertNotFound();
    });

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.reports.show', ReportType::StudentPerformance))->assertRedirectToRoute('login');
    });

    it('forbids the other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.reports.show', ReportType::StudentPerformance))
            ->assertForbidden();
    })->with([UserRole::Teacher, UserRole::Parent, UserRole::Student]);
});
