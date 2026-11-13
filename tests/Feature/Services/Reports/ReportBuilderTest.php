<?php

use App\Enums\GradeLevel;
use App\Enums\PerformanceBand;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Feature\Services\Reports\ReportsFixture;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(ReportsFixture::NOW));
});

describe('learner performance', function () {
    it('lists every learner of the active classrooms with their best-attempt figures for administrators', function () {
        $school = ReportsFixture::create();

        $report = app(ReportBuilder::class)->build(ReportType::StudentPerformance, User::factory()->admin()->create(), new ReportFilters);

        expect(array_map(fn (array $row): string => $row['learner'], ReportsFixture::texts($report)))->toBe(['Ana Cruz', 'Ben Diaz', 'Carla Santos'])
            ->and(ReportsFixture::texts($report)[0])->toBe([
                'learner' => 'Ana Cruz',
                'lrn' => '136742210001',
                'grade' => 'Grade 5',
                'classrooms' => 'Health 5 – Rizal, Science 5 – Rizal',
                'quizzes_taken' => '2',
                'average' => '85%',
                'highest' => '90%',
                'lowest' => '80%',
                'quizzes_passed' => '2',
                'missions_completed' => '3',
                'points' => '1,240',
                'level' => '3',
                'band' => 'Very Satisfactory',
            ])
            ->and(ReportsFixture::texts($report)[1])->toMatchArray([
                'quizzes_taken' => '1', 'average' => '70%', 'quizzes_passed' => '0', 'missions_completed' => '0', 'level' => '1', 'band' => 'Did Not Meet Expectations',
            ])
            ->and(ReportsFixture::texts($report)[2])->toMatchArray([
                'grade' => 'Grade 6', 'average' => '95%', 'quizzes_passed' => '1', 'missions_completed' => '1', 'points' => '2,600', 'level' => '6', 'band' => 'Outstanding',
            ])
            ->and($report->rows[0]->url)->toBe(route('admin.students.show', $school->ana))
            ->and(ReportsFixture::summary($report))->toBe([
                'Learners' => '3',
                'Average score' => '83.3%',
                'Needs attention' => '1',
                'Missions completed' => '4',
            ])
            ->and($report->filters)->toBe(['Classrooms' => 'All active classrooms'])
            ->and(collect($report->breakdown['segments'])->pluck('value', 'label')->all())->toBe([
                'Outstanding' => 1,
                'Very Satisfactory' => 1,
                'Satisfactory' => 0,
                'Fairly Satisfactory' => 0,
                'Did Not Meet Expectations' => 1,
                'No data' => 0,
            ]);
    });

    it('keeps numbers numeric in the CSV cells', function () {
        ReportsFixture::create();

        $report = app(ReportBuilder::class)->build(ReportType::StudentPerformance, User::factory()->admin()->create(), new ReportFilters);

        expect($report->csvHeadings())->toBe([
            'Learner', 'LRN', 'Grade', 'Classrooms', 'Quizzes taken', 'Average score', 'Highest score', 'Lowest score',
            'Quizzes passed', 'Missions completed', 'Points', 'Level', 'Performance band',
        ])->and($report->csvRows()->first())->toBe([
            'Ana Cruz', '136742210001', 'Grade 5', 'Health 5 – Rizal; Science 5 – Rizal', 2, 85.0, 90.0, 80.0, 2, 3, 1240, 3, 'Very Satisfactory',
        ]);
    });

    it('limits teachers to the learners and attempts of their accessible classrooms', function () {
        $school = ReportsFixture::create();

        $santos = app(ReportBuilder::class)->build(ReportType::StudentPerformance, $school->santos, new ReportFilters);
        $reyes = app(ReportBuilder::class)->build(ReportType::StudentPerformance, $school->reyes, new ReportFilters);

        expect(array_column(ReportsFixture::texts($santos), 'learner'))->toBe(['Ana Cruz', 'Ben Diaz'])
            ->and(ReportsFixture::texts($santos)[0])->toMatchArray(['quizzes_taken' => '2', 'average' => '85%'])
            ->and($santos->rows[0]->url)->toBe(route('teacher.students.show', $school->ana))
            ->and($santos->filters)->toBe(['Classrooms' => 'Your active classrooms'])
            ->and(array_column(ReportsFixture::texts($reyes), 'learner'))->toBe(['Carla Santos']);
    });

    it('drops a classroom from a teacher\'s reports once its subject is revoked', function () {
        $school = ReportsFixture::create();
        $school->santos->assignedSubjects()->detach($school->science);
        $school->santos->refresh();

        $report = app(ReportBuilder::class)->build(ReportType::StudentPerformance, $school->santos, new ReportFilters);

        expect(ReportsFixture::texts($report)[0])->toMatchArray([
            'learner' => 'Ana Cruz', 'classrooms' => 'Health 5 – Rizal', 'quizzes_taken' => '1', 'average' => '80%', 'missions_completed' => '1',
        ]);
    });

    it('reports on an archived classroom only when it is chosen', function () {
        $school = ReportsFixture::create();

        $report = app(ReportBuilder::class)->build(ReportType::StudentPerformance, $school->santos, new ReportFilters(classroomId: $school->oldHealth5->id));

        expect(ReportsFixture::texts($report))->toHaveCount(1)
            ->and(ReportsFixture::texts($report)[0])->toMatchArray([
                'learner' => 'Ana Cruz', 'classrooms' => 'Health 5 – Old', 'quizzes_taken' => '1', 'average' => '50%', 'missions_completed' => '1', 'band' => 'Did Not Meet Expectations',
            ])
            ->and($report->filters)->toBe(['Classrooms' => 'Health 5 – Old (archived)']);
    });

    it('narrows the classrooms by subject, grade and teacher', function (string $filter, array $learners, array $first) {
        $school = ReportsFixture::create();
        $filters = match ($filter) {
            'subject' => new ReportFilters(subjectId: $school->science->id),
            'grade' => new ReportFilters(gradeLevel: GradeLevel::Six),
            'teacher' => new ReportFilters(teacherId: $school->reyes->id),
        };

        $report = app(ReportBuilder::class)->build(ReportType::StudentPerformance, User::factory()->admin()->create(), $filters);

        expect(array_column(ReportsFixture::texts($report), 'learner'))->toBe($learners)
            ->and(ReportsFixture::texts($report)[0])->toMatchArray($first);
    })->with([
        'subject' => ['subject', ['Ana Cruz'], ['classrooms' => 'Science 5 – Rizal', 'quizzes_taken' => '1', 'average' => '90%', 'missions_completed' => '2']],
        'grade' => ['grade', ['Carla Santos'], ['average' => '95%']],
        'teacher' => ['teacher', ['Carla Santos'], ['classrooms' => 'Health 6 – Mabini']],
    ]);

    it('describes the applied filters', function () {
        $school = ReportsFixture::create();
        $filters = ReportFilters::fromValidated([
            'subject_id' => (string) $school->health->id,
            'grade_level' => '5',
            'teacher_id' => (string) $school->santos->id,
            'from' => '2026-11-01',
            'to' => '2026-11-13',
        ]);

        $report = app(ReportBuilder::class)->build(ReportType::StudentPerformance, User::factory()->admin()->create(), $filters);

        expect($report->filters)->toBe([
            'Classrooms' => 'All active classrooms',
            'Subject' => 'Health',
            'Grade' => 'Grade 5',
            'Teacher' => 'Maricel Santos',
            'Period' => 'Nov 1, 2026 – Nov 13, 2026',
        ]);
    });

    it('counts only the attempts and missions of the period, both days included', function () {
        ReportsFixture::create();
        $admin = User::factory()->admin()->create();

        $fromNov6 = app(ReportBuilder::class)->build(ReportType::StudentPerformance, $admin, ReportFilters::fromValidated(['from' => '2026-11-06']));
        $untilNov5 = app(ReportBuilder::class)->build(ReportType::StudentPerformance, $admin, ReportFilters::fromValidated(['to' => '2026-11-05']));

        expect(ReportsFixture::texts($fromNov6)[0])->toMatchArray(['quizzes_taken' => '1', 'average' => '80%', 'missions_completed' => '1', 'band' => 'Satisfactory'])
            ->and(ReportsFixture::texts($untilNov5)[0])->toMatchArray(['quizzes_taken' => '2', 'average' => '75%', 'lowest' => '60%', 'quizzes_passed' => '1', 'missions_completed' => '2', 'band' => 'Fairly Satisfactory'])
            ->and(ReportsFixture::texts($untilNov5)[1])->toMatchArray(['quizzes_taken' => '0', 'average' => '—', 'band' => 'No data'])
            ->and($untilNov5->rows[1]->value('band'))->toBe(PerformanceBand::NoData)
            ->and($fromNov6->filters['Period'])->toBe('From Nov 6, 2026');
    });

    it('tags inactive learner accounts', function () {
        $school = ReportsFixture::create();
        $school->ben->forceFill(['is_active' => false])->save();

        $report = app(ReportBuilder::class)->build(ReportType::StudentPerformance, $school->santos, new ReportFilters);

        expect($report->rows[1]->tags)->toBe([['label' => 'Inactive account', 'color' => 'slate', 'icon' => 'ban']])
            ->and($report->rows[0]->tags)->toBe([]);
    });
});

describe('quiz results', function () {
    it('summarizes every published and closed quiz of the active classrooms for administrators', function () {
        $school = ReportsFixture::create();

        $report = app(ReportBuilder::class)->build(ReportType::QuizResults, User::factory()->admin()->create(), new ReportFilters);

        expect(ReportsFixture::texts($report))->toBe([
            [
                'quiz' => 'Food Groups Quiz', 'subject' => 'Health', 'classrooms' => 'Health 6 – Mabini', 'teacher' => 'Lorna Reyes',
                'attempts' => '1', 'learners_attempted' => '1', 'learners_assigned' => '1', 'average' => '95%', 'pass_rate' => '100%',
                'highest' => '95%', 'lowest' => '95%', 'due' => 'Nov 20, 2026',
            ],
            [
                'quiz' => 'Healthy Plate Quiz', 'subject' => 'Health', 'classrooms' => 'Health 5 – Rizal', 'teacher' => 'Maricel Santos',
                'attempts' => '3', 'learners_attempted' => '2', 'learners_assigned' => '2', 'average' => '75%', 'pass_rate' => '50%',
                'highest' => '80%', 'lowest' => '70%', 'due' => 'Nov 10, 2026',
            ],
            [
                'quiz' => 'Digestion Quiz', 'subject' => 'Science', 'classrooms' => 'Science 5 – Rizal', 'teacher' => 'Maricel Santos',
                'attempts' => '1', 'learners_attempted' => '1', 'learners_assigned' => '1', 'average' => '90%', 'pass_rate' => '100%',
                'highest' => '90%', 'lowest' => '90%', 'due' => 'Nov 5, 2026',
            ],
        ])
            ->and(ReportsFixture::summary($report))->toBe([
                'Quizzes' => '3',
                'Attempts' => '5',
                'Average score' => '83.8%',
                'Pass rate' => '75%',
            ])
            ->and($report->summary[1]->hint)->toBe('100% of assigned learners took part')
            ->and($report->rows[2]->tags)->toBe([['label' => 'Closed', 'color' => 'slate', 'icon' => 'lock']])
            ->and($report->rows[0]->url)->toBeNull();
    });

    it('shows teachers only their own quizzes, without the teacher column, linked to the results page', function () {
        $school = ReportsFixture::create();

        $report = app(ReportBuilder::class)->build(ReportType::QuizResults, $school->santos, new ReportFilters);

        expect(array_column(ReportsFixture::texts($report), 'quiz'))->toBe(['Healthy Plate Quiz', 'Digestion Quiz'])
            ->and(collect($report->columns)->pluck('key')->all())->not->toContain('teacher')
            ->and($report->rows[0]->url)->toBe(route('teacher.quizzes.results.show', $school->healthyPlate));
    });

    it('formats different due dates of the covered classrooms as a range', function () {
        $school = ReportsFixture::create();
        $school->health6->quizzes()->attach($school->healthyPlate, ['due_at' => CarbonImmutable::parse('2026-11-12 17:00')]);

        $report = app(ReportBuilder::class)->build(ReportType::QuizResults, User::factory()->admin()->create(), new ReportFilters(subjectId: $school->health->id));

        expect(ReportsFixture::texts($report)[1])->toMatchArray([
            'quiz' => 'Healthy Plate Quiz',
            'classrooms' => 'Health 5 – Rizal, Health 6 – Mabini',
            'learners_assigned' => '3',
            'due' => 'Nov 10, 2026 – Nov 12, 2026',
        ])->and($report->csvRows()[1][11])->toBe('2026-11-10 to 2026-11-12');
    });

    it('leaves out quizzes without attempts in the period', function () {
        ReportsFixture::create();

        $report = app(ReportBuilder::class)->build(ReportType::QuizResults, User::factory()->admin()->create(), ReportFilters::fromValidated(['from' => '2026-11-05', 'to' => '2026-11-13']));

        expect(array_column(ReportsFixture::texts($report), 'quiz'))->toBe(['Food Groups Quiz', 'Healthy Plate Quiz'])
            ->and(ReportsFixture::texts($report)[1])->toMatchArray(['attempts' => '2', 'average' => '75%']);
    });

    it('limits an archived classroom report to the quizzes assigned to it', function () {
        $school = ReportsFixture::create();

        $report = app(ReportBuilder::class)->build(ReportType::QuizResults, $school->santos, new ReportFilters(classroomId: $school->oldHealth5->id));

        expect(ReportsFixture::texts($report))->toHaveCount(1)
            ->and(ReportsFixture::texts($report)[0])->toMatchArray(['quiz' => 'Old Assessment', 'attempts' => '1', 'average' => '50%', 'pass_rate' => '0%']);
    });
});

describe('mission progress', function () {
    it('counts eligible learners and their progress on the missions of the covered subjects and grades', function () {
        $school = ReportsFixture::create();

        $report = app(ReportBuilder::class)->build(ReportType::MissionProgress, User::factory()->admin()->create(), new ReportFilters);

        expect(ReportsFixture::texts($report))->toBe([
            ['mission' => 'Rainbow Plate', 'subject' => 'Health', 'type' => 'Story mission', 'eligible' => '2', 'completed' => '1', 'in_progress' => '1', 'completion_rate' => '50%', 'points' => '100'],
            ['mission' => 'Body Builders', 'subject' => 'Health', 'type' => 'Weekly', 'eligible' => '1', 'completed' => '1', 'in_progress' => '0', 'completion_rate' => '100%', 'points' => '80'],
            ['mission' => 'Water Warrior', 'subject' => 'Science', 'type' => 'Challenge', 'eligible' => '1', 'completed' => '1', 'in_progress' => '0', 'completion_rate' => '100%', 'points' => '60'],
            ['mission' => 'Old Quest', 'subject' => 'Science', 'type' => 'Story mission', 'eligible' => '1', 'completed' => '1', 'in_progress' => '0', 'completion_rate' => '100%', 'points' => '40'],
        ])
            ->and($report->rows[3]->tags)->toBe([['label' => 'Inactive', 'color' => 'slate', 'icon' => 'eye-off']])
            ->and(ReportsFixture::summary($report))->toBe([
                'Missions' => '4',
                'Completions' => '4',
                'Completion rate' => '80%',
                'Points awarded' => '280',
            ])
            ->and(collect($report->breakdown['segments'])->pluck('value', 'label')->all())->toBe(['Completed' => 4, 'In progress' => 1, 'Not started' => 0]);
    });

    it('limits teachers to the missions of their classrooms\' subjects and grades', function () {
        $school = ReportsFixture::create();

        $santos = app(ReportBuilder::class)->build(ReportType::MissionProgress, $school->santos, new ReportFilters);
        $reyes = app(ReportBuilder::class)->build(ReportType::MissionProgress, $school->reyes, new ReportFilters);

        expect(array_column(ReportsFixture::texts($santos), 'mission'))->toBe(['Rainbow Plate', 'Water Warrior', 'Old Quest'])
            ->and($santos->rows[0]->url)->toBe(route('teacher.missions.show', $school->rainbowPlate))
            ->and(ReportsFixture::texts($reyes))->toBe([
                ['mission' => 'Body Builders', 'subject' => 'Health', 'type' => 'Weekly', 'eligible' => '1', 'completed' => '1', 'in_progress' => '0', 'completion_rate' => '100%', 'points' => '80'],
            ]);
    });

    it('does not count progress of learners outside the covered classrooms', function () {
        $school = ReportsFixture::create();

        $report = app(ReportBuilder::class)->build(ReportType::MissionProgress, $school->santos, new ReportFilters(classroomId: $school->science5->id));

        expect(ReportsFixture::texts($report))->toBe([
            ['mission' => 'Water Warrior', 'subject' => 'Science', 'type' => 'Challenge', 'eligible' => '1', 'completed' => '1', 'in_progress' => '0', 'completion_rate' => '100%', 'points' => '60'],
            ['mission' => 'Old Quest', 'subject' => 'Science', 'type' => 'Story mission', 'eligible' => '1', 'completed' => '1', 'in_progress' => '0', 'completion_rate' => '100%', 'points' => '40'],
        ]);
    });

    it('counts completions and missions in progress of the period', function () {
        ReportsFixture::create();

        $report = app(ReportBuilder::class)->build(ReportType::MissionProgress, User::factory()->admin()->create(), ReportFilters::fromValidated(['from' => '2026-11-05', 'to' => '2026-11-13']));

        expect(array_map(fn (array $row): array => [$row['mission'], $row['completed'], $row['in_progress'], $row['points']], ReportsFixture::texts($report)))->toBe([
            ['Rainbow Plate', '1', '1', '100'],
            ['Body Builders', '1', '0', '80'],
            ['Water Warrior', '0', '0', '0'],
            ['Old Quest', '0', '0', '0'],
        ])->and(collect($report->breakdown['segments'])->pluck('value', 'label')->all())->toBe(['Completed' => 2, 'In progress' => 1, 'No progress in the period' => 2]);
    });
});

describe('classroom summary', function () {
    it('summarizes every active classroom for administrators', function () {
        ReportsFixture::create();

        $report = app(ReportBuilder::class)->build(ReportType::ClassroomSummary, User::factory()->admin()->create(), new ReportFilters);

        expect(ReportsFixture::texts($report))->toBe([
            ['classroom' => 'Health 5 – Rizal', 'subject' => 'Health', 'teacher' => 'Maricel Santos', 'grade' => 'Grade 5', 'learners' => '2', 'average' => '75%', 'quiz_completion' => '100%', 'missions_completed' => '1', 'active_learners' => '1'],
            ['classroom' => 'Health 6 – Mabini', 'subject' => 'Health', 'teacher' => 'Lorna Reyes', 'grade' => 'Grade 6', 'learners' => '1', 'average' => '95%', 'quiz_completion' => '—', 'missions_completed' => '1', 'active_learners' => '0'],
            ['classroom' => 'Science 5 – Rizal', 'subject' => 'Science', 'teacher' => 'Maricel Santos', 'grade' => 'Grade 5', 'learners' => '1', 'average' => '90%', 'quiz_completion' => '100%', 'missions_completed' => '2', 'active_learners' => '1'],
        ])->and(ReportsFixture::summary($report))->toBe([
            'Classrooms' => '3',
            'Learners' => '3',
            'Average score' => '83.8%',
            'Quiz completion' => '100%',
        ])->and($report->summary[3]->hint)->toBe('3 of 3 due quizzes submitted');
    });

    it('counts a missing submission of a due quiz against the completion rate', function () {
        $school = ReportsFixture::create();
        User::factory()->student(GradeLevel::Five)->enrolledIn($school->health5)->create(['name' => 'Dan Lim']);

        $report = app(ReportBuilder::class)->build(ReportType::ClassroomSummary, $school->santos, new ReportFilters(classroomId: $school->health5->id));

        expect(ReportsFixture::texts($report)[0])->toMatchArray(['learners' => '3', 'quiz_completion' => '66.7%'])
            ->and($report->summary[3]->hint)->toBe('2 of 3 due quizzes submitted')
            ->and($report->rows[0]->url)->toBe(route('teacher.classrooms.show', $school->health5));
    });

    it('tags an archived classroom chosen in the filter', function () {
        $school = ReportsFixture::create();

        $report = app(ReportBuilder::class)->build(ReportType::ClassroomSummary, $school->santos, new ReportFilters(classroomId: $school->oldHealth5->id));

        expect(ReportsFixture::texts($report))->toBe([
            ['classroom' => 'Health 5 – Old', 'subject' => 'Health', 'teacher' => 'Maricel Santos', 'grade' => 'Grade 5', 'learners' => '1', 'average' => '50%', 'quiz_completion' => '100%', 'missions_completed' => '1', 'active_learners' => '1'],
        ])->and($report->rows[0]->tags)->toBe([['label' => 'Archived', 'color' => 'slate', 'icon' => 'archive']])
            ->and($report->summary[0]->hint)->toBe('1 archived classroom');
    });

    it('limits the averages, quiz completion and missions to the period', function () {
        ReportsFixture::create();

        $report = app(ReportBuilder::class)->build(ReportType::ClassroomSummary, User::factory()->admin()->create(), ReportFilters::fromValidated(['from' => '2026-11-05', 'to' => '2026-11-13']));

        expect(array_map(fn (array $row): array => [$row['classroom'], $row['average'], $row['quiz_completion'], $row['missions_completed']], ReportsFixture::texts($report)))->toBe([
            ['Health 5 – Rizal', '75%', '100%', '1'],
            ['Health 6 – Mabini', '95%', '—', '1'],
            ['Science 5 – Rizal', '—', '—', '0'],
        ]);
    });
});

describe('user accounts', function () {
    it('lists every account with its role, sign-in and status', function () {
        $this->travelTo(CarbonImmutable::parse('2026-06-01 08:00'));
        $admin = User::factory()->admin()->create(['name' => 'Josefina Ramos', 'email' => 'admin@school.test', 'last_login_at' => CarbonImmutable::parse('2026-11-12 07:30')]);
        $this->travelTo(CarbonImmutable::parse('2026-08-15 08:00'));
        $teacher = User::factory()->teacher()->inactive()->create(['name' => 'Ramon Gutierrez', 'email' => 'ramon@school.test', 'last_login_at' => null]);
        $learner = User::factory()->student()->create(['name' => 'Ana Cruz', 'username' => 'ana_cruz']);
        $this->travelTo(CarbonImmutable::parse(ReportsFixture::NOW));

        $report = app(ReportBuilder::class)->build(ReportType::UserAccounts, $admin, new ReportFilters);

        expect(ReportsFixture::texts($report))->toBe([
            ['name' => 'Ana Cruz', 'role' => 'Learner', 'login' => 'ana_cruz', 'status' => 'Active', 'last_login' => 'Never', 'created' => 'Aug 15, 2026'],
            ['name' => 'Josefina Ramos', 'role' => 'Administrator', 'login' => 'admin@school.test', 'status' => 'Active', 'last_login' => 'Nov 12, 2026 7:30 AM', 'created' => 'Jun 1, 2026'],
            ['name' => 'Ramon Gutierrez', 'role' => 'Teacher', 'login' => 'ramon@school.test', 'status' => 'Inactive', 'last_login' => 'Never', 'created' => 'Aug 15, 2026'],
        ])->and(ReportsFixture::summary($report))->toBe([
            'Accounts' => '3',
            'Active' => '2',
            'Inactive' => '1',
            'Never signed in' => '1',
        ])->and(array_column($report->rows->all(), 'url'))->toBe([
            route('admin.students.show', $learner),
            route('admin.users.edit', $admin),
            route('admin.teachers.show', $teacher),
        ])->and($report->csvRows()[1])->toBe(['Josefina Ramos', 'Administrator', 'admin@school.test', 'Active', '2026-11-12 07:30:00', '2026-06-01'])
            ->and($report->filters)->toBe(['Accounts' => 'All roles']);
    });

    it('filters accounts by role, status and creation date', function () {
        $this->travelTo(CarbonImmutable::parse('2026-06-01 08:00'));
        $admin = User::factory()->admin()->create(['name' => 'Josefina Ramos']);
        User::factory()->parent()->create(['name' => 'Old Parent']);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00'));
        User::factory()->parent()->create(['name' => 'New Parent']);
        User::factory()->parent()->inactive()->create(['name' => 'Inactive Parent']);
        User::factory()->teacher()->create(['name' => 'New Teacher']);
        $this->travelTo(CarbonImmutable::parse(ReportsFixture::NOW));

        $report = app(ReportBuilder::class)->build(ReportType::UserAccounts, $admin, ReportFilters::fromValidated(['role' => 'parent', 'status' => 'active', 'from' => '2026-11-01']));

        expect(array_column(ReportsFixture::texts($report), 'name'))->toBe(['New Parent'])
            ->and($report->filters)->toBe(['Accounts' => 'Parent / Guardian', 'Status' => 'Active', 'Period' => 'From Nov 1, 2026'])
            ->and(collect($report->breakdown['segments'])->firstWhere('label', UserRole::Parent->label())['value'])->toBe(1);
    });
});

it('refuses to build a report the viewer cannot generate', function () {
    $teacher = User::factory()->teaching()->create();

    app(ReportBuilder::class)->build(ReportType::UserAccounts, $teacher, new ReportFilters);
})->throws(AuthorizationException::class);

it('builds empty reports for a teacher without subjects', function (ReportType $type) {
    $report = app(ReportBuilder::class)->build($type, User::factory()->teacher()->create(), new ReportFilters);

    expect($report->isEmpty())->toBeTrue()
        ->and($report->summary)->toHaveCount(4);
})->with(array_values(array_filter(ReportType::cases(), fn (ReportType $type): bool => $type->availableToTeachers())));
