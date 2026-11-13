<?php

use App\Enums\GradeLevel;
use App\Enums\PerformanceBand;
use App\Models\Classroom;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\User;
use App\Services\Analytics\AnalyticsPeriod;
use App\Services\Analytics\AnalyticsScope;
use App\Services\Analytics\LearningAnalytics;
use Carbon\CarbonImmutable;
use Tests\Feature\Services\Analytics\AnalyticsSchoolFixture;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(AnalyticsSchoolFixture::NOW));
});

describe('overview', function () {
    it('computes the school-wide headline figures with deltas against the previous 30 days', function () {
        AnalyticsSchoolFixture::create();

        $overview = app(LearningAnalytics::class)->overview(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect($overview)->toBe([
            'learners' => 4,
            'averageScore' => 75.0,
            'averageScoreDelta' => 17.5,
            'passRate' => 60.0,
            'passRateDelta' => 10.0,
            'results' => 5,
            'attempts' => 6,
            'attemptsDelta' => 4,
            'activeLearners' => 3,
            'activeLearnersDelta' => 1,
            'missionsCompleted' => 2,
            'missionsCompletedDelta' => 1,
            'activitiesCompleted' => 1,
            'activitiesCompletedDelta' => 1,
        ]);
    });

    it('limits a teacher to the learners and results of their own classrooms', function () {
        $school = AnalyticsSchoolFixture::create();

        $overview = app(LearningAnalytics::class)->overview(AnalyticsScope::forTeacher($school->teresa, AnalyticsPeriod::lastDays(30)));

        expect($overview)
            ->learners->toBe(3)
            ->averageScore->toBe(72.5)
            ->passRate->toBe(50.0)
            ->attempts->toBe(5)
            ->activeLearners->toBe(2)
            ->missionsCompleted->toBe(2);
    });

    it('narrows a teacher to one classroom', function () {
        $school = AnalyticsSchoolFixture::create();

        $overview = app(LearningAnalytics::class)->overview(
            AnalyticsScope::forTeacher($school->teresa, AnalyticsPeriod::lastDays(30), $school->science5->id),
        );

        expect($overview)->averageScore->toBe(65.0)->results->toBe(2)->attempts->toBe(2);
    });

    it('limits the school view to one grade', function () {
        AnalyticsSchoolFixture::create();

        $overview = app(LearningAnalytics::class)->overview(AnalyticsScope::school(AnalyticsPeriod::lastDays(30), grade: GradeLevel::Six));

        expect($overview)
            ->learners->toBe(1)
            ->averageScore->toBe(85.0)
            ->averageScoreDelta->toBe(10.0)
            ->attempts->toBe(1);
    });

    it('limits the school view to one subject', function () {
        $school = AnalyticsSchoolFixture::create();

        $overview = app(LearningAnalytics::class)->overview(AnalyticsScope::school(AnalyticsPeriod::lastDays(30), $school->science->id));

        expect($overview)
            ->learners->toBe(2)
            ->averageScore->toBe(65.0)
            ->missionsCompleted->toBe(1)
            ->activitiesCompleted->toBe(0);
    });

    it('returns empty figures without data', function () {
        $overview = app(LearningAnalytics::class)->overview(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect($overview)->toBe([
            'learners' => 0,
            'averageScore' => null,
            'averageScoreDelta' => null,
            'passRate' => null,
            'passRateDelta' => null,
            'results' => 0,
            'attempts' => 0,
            'attemptsDelta' => 0,
            'activeLearners' => 0,
            'activeLearnersDelta' => 0,
            'missionsCompleted' => 0,
            'missionsCompletedDelta' => 0,
            'activitiesCompleted' => 0,
            'activitiesCompletedDelta' => 0,
        ]);
    });

    it('covers nothing for a teacher without classrooms', function () {
        AnalyticsSchoolFixture::create();
        $teacher = User::factory()->teaching()->create();

        $overview = app(LearningAnalytics::class)->overview(AnalyticsScope::forTeacher($teacher, AnalyticsPeriod::lastDays(30)));

        expect($overview)->learners->toBe(0)->results->toBe(0)->attempts->toBe(0)->missionsCompleted->toBe(0);
    });
});

describe('weekly trend', function () {
    it('buckets the period into ISO weeks with the mean of every attempt and the active learners', function () {
        AnalyticsSchoolFixture::create();

        $weeks = app(LearningAnalytics::class)->weeklyTrend(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect(array_map(fn (array $week): array => [$week['label'], $week['averageScore'], $week['attempts'], $week['activeLearners']], $weeks))->toBe([
            ['Oct 12', null, 0, 1],
            ['Oct 19', 80.0, 1, 1],
            ['Oct 26', null, 0, 0],
            ['Nov 2', 50.0, 1, 1],
            ['Nov 9', 76.3, 4, 3],
        ])
            ->and($weeks[0]['start']->toDateTimeString())->toBe('2026-10-12 00:00:00');
    });

    it('puts an attempt on Sunday night and one on Monday morning in different weeks', function () {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();
        QuizAttempt::factory()->for($student, 'student')->for($classroom)->create(['percentage' => 60, 'submitted_at' => '2026-11-08 23:59:59']);
        QuizAttempt::factory()->for($student, 'student')->for($classroom)->create(['percentage' => 90, 'submitted_at' => '2026-11-09 00:00:00']);

        $weeks = app(LearningAnalytics::class)->weeklyTrend(AnalyticsScope::school(AnalyticsPeriod::lastWeeks(2)));

        expect(array_column($weeks, 'averageScore'))->toBe([60.0, 90.0]);
    });

    it('lists the days of the period with their attempt counts', function () {
        AnalyticsSchoolFixture::create();

        $days = app(LearningAnalytics::class)->dailyAttempts(AnalyticsScope::school(AnalyticsPeriod::lastDays(7)));

        expect(array_column($days, 'label'))->toBe(['Nov 7', 'Nov 8', 'Nov 9', 'Nov 10', 'Nov 11', 'Nov 12', 'Nov 13'])
            ->and(array_column($days, 'attempts'))->toBe([0, 0, 0, 1, 1, 1, 1]);
    });
});

describe('distributions', function () {
    it('counts the results per 10-point score bucket', function () {
        AnalyticsSchoolFixture::create();

        $buckets = app(LearningAnalytics::class)->scoreDistribution(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect(array_column($buckets, 'label'))->toBe(['0–9', '10–19', '20–29', '30–39', '40–49', '50–59', '60–69', '70–79', '80–89', '90–100'])
            ->and(array_column($buckets, 'value'))->toBe([0, 0, 0, 0, 0, 1, 0, 1, 2, 1]);
    });

    it('places results on bucket boundaries by their whole percentage', function (float $percentage, string $bucket) {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();
        QuizAttempt::factory()->for($student, 'student')->for($classroom)->create(['percentage' => $percentage, 'submitted_at' => now()]);

        $buckets = collect(app(LearningAnalytics::class)->scoreDistribution(AnalyticsScope::school(AnalyticsPeriod::lastDays(7))));

        expect($buckets->firstWhere('value', 1)['label'])->toBe($bucket);
    })->with([
        'zero' => [0.0, '0–9'],
        'just below ten' => [9.99, '0–9'],
        'ten' => [10.0, '10–19'],
        'just below ninety' => [89.99, '80–89'],
        'ninety' => [90.0, '90–100'],
        'perfect' => [100.0, '90–100'],
    ]);

    it('counts the learners per performance band of their average, learners without results as no data', function () {
        AnalyticsSchoolFixture::create();

        $bands = app(LearningAnalytics::class)->bandDistribution(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect($bands)->toBe([
            PerformanceBand::Outstanding->value => 0,
            PerformanceBand::VerySatisfactory->value => 2,
            PerformanceBand::Satisfactory->value => 0,
            PerformanceBand::FairlySatisfactory->value => 0,
            PerformanceBand::DidNotMeetExpectations->value => 1,
            PerformanceBand::NoData->value => 1,
        ]);
    });
});

describe('performance breakdowns', function () {
    it('averages the results per subject and grade', function () {
        $school = AnalyticsSchoolFixture::create();

        $subjects = app(LearningAnalytics::class)->subjectPerformance(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect(array_map(fn (array $row): array => [
            $row['subject']->name, $row['averageScore'], $row['passRate'], $row['results'], $row['learners'], $row['byGrade'],
        ], $subjects))->toBe([
            ['Health', 81.7, 66.7, 3, 3, [5 => 80.0, 6 => 85.0]],
            ['Science', 65.0, 50.0, 2, 2, [5 => 65.0, 6 => null]],
        ])->and($subjects[0]['subject']->is($school->health))->toBeTrue();
    });

    it('lists an active subject without results', function () {
        AnalyticsSchoolFixture::create();
        Subject::factory()->create(['name' => 'Mathematics']);
        Subject::factory()->inactive()->create(['name' => 'Arts']);

        $subjects = app(LearningAnalytics::class)->subjectPerformance(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect(array_map(fn (array $row): array => [$row['subject']->name, $row['averageScore']], $subjects))->toBe([
            ['Health', 81.7],
            ['Mathematics', null],
            ['Science', 65.0],
        ]);
    });

    it('averages the results per grade', function () {
        AnalyticsSchoolFixture::create();

        $grades = app(LearningAnalytics::class)->gradePerformance(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect(array_map(fn (array $row): array => [$row['grade'], $row['averageScore'], $row['passRate'], $row['learners']], $grades))->toBe([
            [GradeLevel::Five, 72.5, 50.0, 2],
            [GradeLevel::Six, 85.0, 100.0, 1],
        ]);
    });

    it('ranks the classrooms by average result', function () {
        $school = AnalyticsSchoolFixture::create();

        $ranking = app(LearningAnalytics::class)->classroomRanking(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect(array_map(fn (array $row): array => [$row['classroom']->id, $row['averageScore'], $row['learners']], $ranking))->toBe([
            [$school->health6->id, 85.0, 1],
            [$school->health5->id, 80.0, 2],
            [$school->science5->id, 65.0, 2],
        ])
            ->and($ranking[0]['classroom']->relationLoaded('teacher'))->toBeTrue()
            ->and(app(LearningAnalytics::class)->classroomRanking(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)), 2))->toHaveCount(2);
    });

    it('lists the module mastery weakest first', function () {
        $school = AnalyticsSchoolFixture::create();

        $modules = app(LearningAnalytics::class)->moduleMastery(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect(array_map(fn (array $row): array => [$row['module']->id, $row['averageScore'], $row['results']], $modules))->toBe([
            [$school->plateModule->id, 80.0, 2],
            [$school->snackModule->id, 85.0, 1],
        ]);
    });
});

describe('most-missed questions', function () {
    it('ranks questions by miss rate with their most chosen wrong answer, leaving out rarely answered ones', function () {
        $school = AnalyticsSchoolFixture::create();

        $questions = app(LearningAnalytics::class)->mostMissedQuestions(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect(array_map(fn (array $row): array => [
            $row['question']->id, $row['answered'], $row['missed'], $row['missRate'], $row['commonWrongAnswer'], $row['commonWrongCount'],
        ], $questions))->toBe([
            [$school->goFoodQuestion->id, 3, 2, 66.7, 'Fruit', 2],
            [$school->growFoodQuestion->id, 3, 1, 33.3, 'Candy', 1],
        ])->and($questions[0]['question']->relationLoaded('quiz'))->toBeTrue();
    });

    it('groups typed wrong answers ignoring case and extra spaces', function () {
        $classroom = Classroom::factory()->create();
        $question = QuizQuestion::factory()->identification()->create(['correct_answer' => 'Water']);

        foreach ([' Juice', 'juice ', 'JUICE', 'soda'] as $answer) {
            $attempt = QuizAttempt::factory()->for($question->quiz)->for(User::factory()->student()->enrolledIn($classroom), 'student')->for($classroom)->create(['submitted_at' => now()]);
            QuizAttemptAnswer::factory()->for($attempt, 'attempt')->for($question, 'question')->create(['answer' => $answer, 'is_correct' => false]);
        }

        $questions = app(LearningAnalytics::class)->mostMissedQuestions(AnalyticsScope::school(AnalyticsPeriod::lastDays(7)));

        expect($questions[0])->missRate->toBe(100.0)->commonWrongAnswer->toBe('Juice')->commonWrongCount->toBe(3);
    });

    it('lists nothing when every answer was right', function () {
        $classroom = Classroom::factory()->create();
        $question = QuizQuestion::factory()->create();

        foreach (range(1, 3) as $learner) {
            $attempt = QuizAttempt::factory()->for($question->quiz)->for(User::factory()->student()->enrolledIn($classroom), 'student')->for($classroom)->create(['submitted_at' => now()]);
            QuizAttemptAnswer::factory()->correct()->for($attempt, 'attempt')->for($question, 'question')->create();
        }

        expect(app(LearningAnalytics::class)->mostMissedQuestions(AnalyticsScope::school(AnalyticsPeriod::lastDays(7))))->toBe([]);
    });
});

describe('learners', function () {
    it('flags low averages first, then the learners who never played or have been away longest', function () {
        $school = AnalyticsSchoolFixture::create();

        $atRisk = app(LearningAnalytics::class)->atRiskLearners(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect($atRisk)->count->toBe(3)->lowScore->toBe(1)->inactive->toBe(2)
            ->and(array_map(fn (array $entry): array => [
                $entry['student']->id, $entry['averageScore'], $entry['band'], $entry['lowScore'], $entry['inactive'],
            ], $atRisk['learners']))->toBe([
                [$school->ben->id, 60.0, PerformanceBand::DidNotMeetExpectations, true, false],
                [$school->ella->id, null, PerformanceBand::NoData, false, true],
                [$school->carlo->id, 85.0, PerformanceBand::VerySatisfactory, false, true],
            ])
            ->and($atRisk['learners'][0]['student']->relationLoaded('studentProfile'))->toBeTrue();
    });

    it('counts a learner inactive from exactly seven days without game activity', function (string $lastActiveAt, bool $flagged) {
        $classroom = Classroom::factory()->create();
        $student = User::factory()->student()->enrolledIn($classroom)->create();
        $student->studentProfile->forceFill(['last_active_at' => $lastActiveAt])->save();

        $atRisk = app(LearningAnalytics::class)->atRiskLearners(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)), 5);

        expect($atRisk['count'])->toBe($flagged ? 1 : 0);
    })->with([
        'seven days ago' => ['2026-11-06 10:00:00', true],
        'just under seven days ago' => ['2026-11-06 10:00:01', false],
    ]);

    it('ranks the learners by points, ties sharing a rank, without learners who have no points or inactive accounts', function () {
        $school = AnalyticsSchoolFixture::create();

        $leaderboard = app(LearningAnalytics::class)->leaderboard(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect(array_map(fn (array $entry): array => [$entry['student']->id, $entry['rank'], $entry['points'], $entry['level']], $leaderboard))->toBe([
            [$school->ana->id, 1, 1240, 3],
            [$school->carlo->id, 1, 1240, 3],
            [$school->ben->id, 3, 600, 2],
        ]);
    });

    it('limits the leaderboard to a teacher’s learners', function () {
        $school = AnalyticsSchoolFixture::create();

        $leaderboard = app(LearningAnalytics::class)->leaderboard(AnalyticsScope::forTeacher($school->ramon, AnalyticsPeriod::lastDays(30)));

        expect(array_map(fn (array $entry): int => $entry['student']->id, $leaderboard))->toBe([$school->carlo->id]);
    });
});

describe('engagement', function () {
    it('counts attempts, mission and activity completions per weekday', function () {
        AnalyticsSchoolFixture::create();

        $weekdays = app(LearningAnalytics::class)->weekdayEngagement(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect($weekdays)->toBe([
            ['label' => 'Mon', 'attempts' => 1, 'missions' => 0, 'activities' => 1],
            ['label' => 'Tue', 'attempts' => 2, 'missions' => 0, 'activities' => 0],
            ['label' => 'Wed', 'attempts' => 1, 'missions' => 0, 'activities' => 0],
            ['label' => 'Thu', 'attempts' => 1, 'missions' => 1, 'activities' => 0],
            ['label' => 'Fri', 'attempts' => 1, 'missions' => 1, 'activities' => 0],
            ['label' => 'Sat', 'attempts' => 0, 'missions' => 0, 'activities' => 0],
            ['label' => 'Sun', 'attempts' => 0, 'missions' => 0, 'activities' => 0],
        ]);
    });

    it('measures the completion of missions and activities started in the period', function () {
        AnalyticsSchoolFixture::create();

        $completion = app(LearningAnalytics::class)->completion(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect($completion)->toBe([
            'missions' => ['started' => 2, 'completed' => 1, 'rate' => 50.0],
            'activities' => ['started' => 1, 'completed' => 1, 'rate' => 100.0],
        ]);
    });

    it('reports the sync status of the learners and the share of offline attempts', function () {
        AnalyticsSchoolFixture::create();

        $health = app(LearningAnalytics::class)->syncHealth(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect($health)->toBe([
            'learners' => 4,
            'upToDate' => 1,
            'overdue' => 1,
            'neverSynced' => 1,
            'notPlayed' => 1,
            'attempts' => 6,
            'offlineAttempts' => 1,
            'offlineShare' => 16.7,
        ]);
    });
});

describe('teacher activity', function () {
    it('counts each teacher’s quizzes and feedback in the period', function () {
        $school = AnalyticsSchoolFixture::create();

        $teachers = app(LearningAnalytics::class)->teacherActivity(AnalyticsScope::school(AnalyticsPeriod::lastDays(30)));

        expect($teachers->total())->toBe(2)
            ->and($teachers->getCollection()->map(fn (User $teacher): array => [
                $teacher->id, $teacher->active_classrooms_count, $teacher->quizzes_created_count, $teacher->quizzes_published_count, $teacher->feedback_sent_count,
            ])->all())->toBe([
                [$school->ramon->id, 1, 1, 1, 0],
                [$school->teresa->id, 2, 1, 2, 1],
            ]);
    });

    it('limits the teachers and their counts to a subject', function () {
        $school = AnalyticsSchoolFixture::create();

        $teachers = app(LearningAnalytics::class)->teacherActivity(AnalyticsScope::school(AnalyticsPeriod::lastDays(30), $school->science->id));

        expect($teachers->getCollection()->map(fn (User $teacher): array => [
            $teacher->id, $teacher->active_classrooms_count, $teacher->quizzes_created_count, $teacher->quizzes_published_count, $teacher->feedback_sent_count,
        ])->all())->toBe([
            [$school->teresa->id, 1, 1, 1, 0],
        ]);
    });
});
