<?php

use App\Enums\GradeLevel;
use App\Enums\PerformanceBand;
use App\Models\ActivityProgress;
use App\Models\Classroom;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Reward;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use App\Services\LearnerProgressService;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\LearnerProgressFixture;

beforeEach(function () {
    $this->lazyLoadingWasPrevented = Model::preventsLazyLoading();
    Model::preventLazyLoading();
});

afterEach(function () {
    Model::preventLazyLoading($this->lazyLoadingWasPrevented);
});

/**
 * Create a submitted attempt with an exact percentage.
 */
function learnerProgressAttempt(Quiz $quiz, User $student, float $percentage, bool $passed, string $submittedAt): QuizAttempt
{
    return QuizAttempt::factory()->for($quiz)->for($student, 'student')->create([
        'score' => (int) round($percentage),
        'max_score' => 100,
        'percentage' => $percentage,
        'passed' => $passed,
        'submitted_at' => $submittedAt,
    ]);
}

/**
 * Create a Grade 5 learner with the given number of subjects, each with a classroom, a quiz with two attempts,
 * a completed mission, a completed activity and a reward.
 */
function learnerProgressHistory(int $subjects): User
{
    $student = User::factory()->student(GradeLevel::Five)->create();

    foreach (range(1, $subjects) as $index) {
        $subject = Subject::factory()->create();
        $student->enrolledClassrooms()->attach(Classroom::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]));

        $quiz = Quiz::factory()->published()->for($subject)->create();
        learnerProgressAttempt($quiz, $student, 60, false, now()->subDays($index)->toDateTimeString());
        learnerProgressAttempt($quiz, $student, 90, true, now()->subHours($index)->toDateTimeString());

        MissionProgress::factory()->completed()->for(Mission::factory()->for($subject))->for($student, 'student')->create();

        $module = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);
        ActivityProgress::factory()->completed()->for(LearningActivity::factory()->for($module, 'module'), 'activity')->for($student, 'student')->create();

        $student->rewards()->attach(Reward::factory()->create(), ['awarded_at' => now()->subDays($index)]);
    }

    return $student;
}

/**
 * Count the queries one summary of a freshly loaded learner runs.
 */
function learnerProgressQueryCount(User $student): int
{
    $student = User::query()->findOrFail($student->getKey());
    app(Settings::class)->all();

    DB::flushQueryLog();
    DB::enableQueryLog();

    app(LearnerProgressService::class)->summarize($student);

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('summarizes the learner’s whole profile', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $fixture = LearnerProgressFixture::create();

    $summary = app(LearnerProgressService::class)->summarize($fixture->student);

    expect($summary->student->is($fixture->student))->toBeTrue();
    expect($summary->student->relationLoaded('studentProfile'))->toBeTrue();
    expect($summary->classrooms->modelKeys())->toBe([$fixture->healthClassroom->id, $fixture->scienceClassroom->id]);
    expect($summary->classrooms->every(fn (Classroom $classroom): bool => $classroom->relationLoaded('subject')))->toBeTrue();
    expect($summary->totalPoints)->toBe(1240);
    expect($summary->level)->toBe(3);
    expect($summary->maxLevel)->toBe(50);
    expect($summary->pointsIntoLevel)->toBe(240);
    expect($summary->pointsForNextLevel)->toBe(500);
    expect($summary->levelPercent)->toBe(48.0);
    expect($summary->levelProgress)->toBe(['level' => 3, 'into' => 240, 'needed' => 500, 'percent' => 48.0]);
    expect($summary->quizzesTaken)->toBe(4);
    expect($summary->quizzesPassed)->toBe(2);
    expect($summary->averageScore)->toBe(73.8);
    expect($summary->band)->toBe(PerformanceBand::DidNotMeetExpectations);
    expect($summary->missionsCompleted)->toBe(3);
    expect($summary->missionsInProgress)->toBe(1);
    expect($summary->activitiesCompleted)->toBe(2);
    expect($summary->activitiesTotal)->toBe(4);
    expect($summary->rewardsEarned)->toBe(2);
    expect($summary->lastActiveAt->toDateTimeString())->toBe('2026-11-12 18:00:00');
    expect($summary->lastSyncedAt->toDateTimeString())->toBe('2026-11-12 18:05:00');
});

it('lists eight weekly averages of the submitted attempts ending with the current ISO week', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $fixture = LearnerProgressFixture::create();

    $summary = app(LearnerProgressService::class)->summarize($fixture->student);

    expect(array_map(fn (array $week): array => [
        'label' => $week['label'],
        'start' => $week['start']->toDateTimeString(),
        'average' => $week['average'],
        'attempts' => $week['attempts'],
    ], $summary->weeklyScores))->toBe([
        ['label' => 'Sep 21', 'start' => '2026-09-21 00:00:00', 'average' => 50.0, 'attempts' => 1],
        ['label' => 'Sep 28', 'start' => '2026-09-28 00:00:00', 'average' => null, 'attempts' => 0],
        ['label' => 'Oct 5', 'start' => '2026-10-05 00:00:00', 'average' => null, 'attempts' => 0],
        ['label' => 'Oct 12', 'start' => '2026-10-12 00:00:00', 'average' => null, 'attempts' => 0],
        ['label' => 'Oct 19', 'start' => '2026-10-19 00:00:00', 'average' => 60.0, 'attempts' => 1],
        ['label' => 'Oct 26', 'start' => '2026-10-26 00:00:00', 'average' => null, 'attempts' => 0],
        ['label' => 'Nov 2', 'start' => '2026-11-02 00:00:00', 'average' => 95.0, 'attempts' => 1],
        ['label' => 'Nov 9', 'start' => '2026-11-09 00:00:00', 'average' => 75.0, 'attempts' => 2],
    ]);
    expect($summary->weeklyScores[0]['start'])->toBeInstanceOf(CarbonImmutable::class);
});

it('puts attempts into ISO weeks that start on Monday at midnight', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00'));
    $student = User::factory()->student()->create();
    $quiz = Quiz::factory()->published()->create();
    learnerProgressAttempt($quiz, $student, 80, true, '2026-11-09 00:00:00');
    learnerProgressAttempt($quiz, $student, 70, false, '2026-11-08 23:59:59');
    learnerProgressAttempt($quiz, $student, 60, false, '2026-09-21 00:00:00');
    learnerProgressAttempt($quiz, $student, 50, false, '2026-09-20 23:59:59');

    $weeks = app(LearnerProgressService::class)->summarize($student)->weeklyScores;

    expect(array_column($weeks, 'attempts'))->toBe([1, 0, 0, 0, 0, 0, 1, 1]);
    expect(array_column($weeks, 'average'))->toBe([60.0, null, null, null, null, null, 70.0, 80.0]);
});

it('breaks progress down by the subjects of the learner’s active classrooms', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $fixture = LearnerProgressFixture::create();

    $breakdown = app(LearnerProgressService::class)->summarize($fixture->student)->subjectBreakdown;

    expect(array_map(fn (array $row): array => ['subject' => $row['subject']->name] + array_slice($row, 1), $breakdown))->toBe([
        ['subject' => 'Health', 'averageScore' => 75.0, 'quizzesTaken' => 2, 'missionsCompleted' => 1, 'activitiesCompleted' => 1, 'activitiesTotal' => 2],
        ['subject' => 'Science', 'averageScore' => 95.0, 'quizzesTaken' => 1, 'missionsCompleted' => 1, 'activitiesCompleted' => 1, 'activitiesTotal' => 2],
    ]);
    expect($breakdown[0]['subject'])->toBeInstanceOf(Subject::class);
});

it('lists the five latest submitted attempts with their quiz and subject', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $fixture = LearnerProgressFixture::create();

    $attempts = app(LearnerProgressService::class)->summarize($fixture->student)->recentAttempts;

    expect($attempts->modelKeys())->toBe([
        $fixture->attempts['healthySnacks']->id,
        $fixture->attempts['healthyPlateBest']->id,
        $fixture->attempts['digestion']->id,
        $fixture->attempts['healthyPlateFirst']->id,
        $fixture->attempts['fractionsBest']->id,
    ]);
    expect($attempts->every(fn (QuizAttempt $attempt): bool => $attempt->relationLoaded('quiz') && $attempt->quiz->relationLoaded('subject')))->toBeTrue();
});

it('lists the five most recently updated mission progress records with their mission and subject', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $fixture = LearnerProgressFixture::create();

    $missions = app(LearnerProgressService::class)->summarize($fixture->student)->recentMissions;

    expect($missions->modelKeys())->toBe([
        $fixture->missions['snackSwap']->id,
        $fixture->missions['rainbowPlate']->id,
        $fixture->missions['waterWarrior']->id,
        $fixture->missions['labelReader']->id,
        $fixture->missions['marketExplorer']->id,
    ]);
    expect($missions->every(fn (MissionProgress $progress): bool => $progress->relationLoaded('mission') && $progress->mission->relationLoaded('subject')))->toBeTrue();
});

it('lists the earned rewards latest award first', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $fixture = LearnerProgressFixture::create();

    $rewards = app(LearnerProgressService::class)->summarize($fixture->student)->rewards;

    expect($rewards->modelKeys())->toBe([$fixture->rewards['hydrationHero']->id, $fixture->rewards['breakfastChampion']->id]);
    expect($rewards->first()->pivot->awarded_at->toDateTimeString())->toBe('2026-11-10 12:00:00');
});

it('limits quiz, mission and activity figures to the given subjects', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $fixture = LearnerProgressFixture::create();

    $summary = app(LearnerProgressService::class)->summarize($fixture->student, [$fixture->health->id]);

    expect($summary->classrooms->modelKeys())->toBe([$fixture->healthClassroom->id]);
    expect($summary->quizzesTaken)->toBe(2);
    expect($summary->quizzesPassed)->toBe(1);
    expect($summary->averageScore)->toBe(75.0);
    expect($summary->band)->toBe(PerformanceBand::FairlySatisfactory);
    expect($summary->missionsCompleted)->toBe(1);
    expect($summary->missionsInProgress)->toBe(1);
    expect($summary->activitiesCompleted)->toBe(1);
    expect($summary->activitiesTotal)->toBe(2);
    expect(array_column($summary->weeklyScores, 'attempts'))->toBe([0, 0, 0, 0, 1, 0, 0, 2]);
    expect(array_map(fn (array $row): string => $row['subject']->name, $summary->subjectBreakdown))->toBe(['Health']);
    expect($summary->recentAttempts->modelKeys())->toBe([
        $fixture->attempts['healthySnacks']->id,
        $fixture->attempts['healthyPlateBest']->id,
        $fixture->attempts['healthyPlateFirst']->id,
    ]);
    expect($summary->recentMissions->modelKeys())->toBe([$fixture->missions['snackSwap']->id, $fixture->missions['rainbowPlate']->id]);
});

it('keeps points, level, rewards and activity times for the whole profile when limited to subjects', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $fixture = LearnerProgressFixture::create();

    $summary = app(LearnerProgressService::class)->summarize($fixture->student, [$fixture->health->id]);

    expect($summary->totalPoints)->toBe(1240);
    expect($summary->level)->toBe(3);
    expect($summary->rewardsEarned)->toBe(2);
    expect($summary->rewards)->toHaveCount(2);
    expect($summary->lastActiveAt->toDateTimeString())->toBe('2026-11-12 18:00:00');
    expect($summary->lastSyncedAt->toDateTimeString())->toBe('2026-11-12 18:05:00');
});

it('reports no quiz, mission or activity progress when limited to no subjects', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $fixture = LearnerProgressFixture::create();

    $summary = app(LearnerProgressService::class)->summarize($fixture->student, []);

    expect($summary->classrooms)->toBeEmpty();
    expect($summary->quizzesTaken)->toBe(0);
    expect($summary->averageScore)->toBeNull();
    expect($summary->band)->toBe(PerformanceBand::NoData);
    expect($summary->missionsCompleted)->toBe(0);
    expect($summary->missionsInProgress)->toBe(0);
    expect($summary->activitiesTotal)->toBe(0);
    expect($summary->subjectBreakdown)->toBe([]);
    expect($summary->recentAttempts)->toBeEmpty();
    expect($summary->recentMissions)->toBeEmpty();
    expect($summary->totalPoints)->toBe(1240);
});

it('summarizes a learner without any progress yet', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $student = User::factory()->studentWithoutProfile()->create();
    StudentProfile::factory()->for($student, 'user')->create([
        'grade_level' => GradeLevel::Six,
        'total_points' => 0,
        'level' => 1,
        'last_active_at' => null,
        'last_synced_at' => null,
    ]);

    $summary = app(LearnerProgressService::class)->summarize($student);

    expect($summary->classrooms)->toBeEmpty();
    expect($summary->totalPoints)->toBe(0);
    expect($summary->levelProgress)->toBe(['level' => 1, 'into' => 0, 'needed' => 500, 'percent' => 0.0]);
    expect($summary->quizzesTaken)->toBe(0);
    expect($summary->quizzesPassed)->toBe(0);
    expect($summary->averageScore)->toBeNull();
    expect($summary->band)->toBe(PerformanceBand::NoData);
    expect($summary->missionsCompleted)->toBe(0);
    expect($summary->missionsInProgress)->toBe(0);
    expect($summary->activitiesCompleted)->toBe(0);
    expect($summary->activitiesTotal)->toBe(0);
    expect($summary->rewardsEarned)->toBe(0);
    expect($summary->lastActiveAt)->toBeNull();
    expect($summary->lastSyncedAt)->toBeNull();
    expect($summary->weeklyScores)->toHaveCount(8);
    expect(array_column($summary->weeklyScores, 'average'))->each->toBeNull();
    expect(array_column($summary->weeklyScores, 'attempts'))->each->toBe(0);
    expect($summary->subjectBreakdown)->toBe([]);
    expect($summary->recentAttempts)->toBeEmpty();
    expect($summary->recentMissions)->toBeEmpty();
    expect($summary->rewards)->toBeEmpty();
});

it('summarizes an account that has no learner profile as level 1 without playable activities', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $subject = Subject::factory()->create();
    $classroom = Classroom::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);
    $student = User::factory()->studentWithoutProfile()->enrolledIn($classroom)->create();
    LearningActivity::factory()->for(LearningModule::factory()->for($subject)->state(['grade_level' => GradeLevel::Five]), 'module')->create();

    $summary = app(LearnerProgressService::class)->summarize($student);

    expect($summary->student->studentProfile)->toBeNull();
    expect($summary->totalPoints)->toBe(0);
    expect($summary->level)->toBe(1);
    expect($summary->activitiesTotal)->toBe(0);
    expect($summary->subjectBreakdown[0]['activitiesTotal'])->toBe(0);
    expect($summary->lastActiveAt)->toBeNull();
});

it('scores each quiz by its best attempt', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $student = User::factory()->student()->create();
    $quiz = Quiz::factory()->published()->create();
    learnerProgressAttempt($quiz, $student, 60, false, '2026-11-02 09:00:00');
    learnerProgressAttempt($quiz, $student, 85, true, '2026-11-03 09:00:00');
    learnerProgressAttempt($quiz, $student, 70, false, '2026-11-04 09:00:00');

    $summary = app(LearnerProgressService::class)->summarize($student);

    expect($summary->quizzesTaken)->toBe(1);
    expect($summary->quizzesPassed)->toBe(1);
    expect($summary->averageScore)->toBe(85.0);
    expect($summary->band)->toBe(PerformanceBand::VerySatisfactory);
});

it('counts a quiz as passed when a passing attempt ties the best percentage', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $student = User::factory()->student()->create();
    $quiz = Quiz::factory()->published()->create();
    learnerProgressAttempt($quiz, $student, 80, true, '2026-11-02 09:00:00');
    learnerProgressAttempt($quiz, $student, 80, false, '2026-11-03 09:00:00');

    expect(app(LearnerProgressService::class)->summarize($student)->quizzesPassed)->toBe(1);
});

it('does not count a quiz as passed when its best attempt failed', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $student = User::factory()->student()->create();
    $quiz = Quiz::factory()->published()->create();
    learnerProgressAttempt($quiz, $student, 72, false, '2026-11-02 09:00:00');

    expect(app(LearnerProgressService::class)->summarize($student)->quizzesPassed)->toBe(0);
});

it('picks the band from the average rounded to one decimal', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $student = User::factory()->student()->create();
    learnerProgressAttempt(Quiz::factory()->published()->create(), $student, 74.95, false, '2026-11-02 09:00:00');
    learnerProgressAttempt(Quiz::factory()->published()->create(), $student, 75.0, true, '2026-11-03 09:00:00');

    $summary = app(LearnerProgressService::class)->summarize($student);

    expect($summary->averageScore)->toBe(75.0);
    expect($summary->band)->toBe(PerformanceBand::FairlySatisfactory);
});

it('ignores attempts that were never submitted', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $student = User::factory()->student()->create();
    QuizAttempt::factory()->inProgress()->for($student, 'student')->create();

    $summary = app(LearnerProgressService::class)->summarize($student);

    expect($summary->quizzesTaken)->toBe(0);
    expect($summary->recentAttempts)->toBeEmpty();
    expect(array_sum(array_column($summary->weeklyScores, 'attempts')))->toBe(0);
});

it('reads the level from the current gamification settings', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    app(Settings::class)->setMany(['points_per_level' => 200, 'max_level' => 5]);
    $student = User::factory()->studentWithoutProfile()->create();
    StudentProfile::factory()->for($student, 'user')->create(['total_points' => 1240, 'level' => 3]);

    $summary = app(LearnerProgressService::class)->summarize($student);

    expect($summary->level)->toBe(5);
    expect($summary->maxLevel)->toBe(5);
    expect($summary->levelProgress)->toBe(['level' => 5, 'into' => 0, 'needed' => 0, 'percent' => 100.0]);
});

it('finds the subjects of the teacher’s classrooms the learner is in, archived classrooms included', function () {
    $health = Subject::factory()->create();
    $science = Subject::factory()->create();
    $math = Subject::factory()->create();
    $revoked = Subject::factory()->create();
    $teacher = User::factory()->teacher()->create();
    $healthClassroom = Classroom::factory()->for($teacher, 'teacher')->for($health)->create();
    $secondHealthClassroom = Classroom::factory()->for($teacher, 'teacher')->for($health)->create();
    Classroom::factory()->for($teacher, 'teacher')->for($science)->create();
    $archivedMathClassroom = Classroom::factory()->archived()->for($teacher, 'teacher')->for($math)->create();
    $revokedClassroom = Classroom::factory()->revokedSubject()->for($teacher, 'teacher')->for($revoked)->create();
    $otherTeachersClassroom = Classroom::factory()->create();
    $student = User::factory()->student()
        ->enrolledIn($healthClassroom, $secondHealthClassroom, $archivedMathClassroom, $revokedClassroom, $otherTeachersClassroom)
        ->create();

    $subjectIds = app(LearnerProgressService::class)->teacherSubjectIds($teacher->fresh(), $student);

    expect($subjectIds)->toBe([$health->id, $math->id]);
});

it('runs the same small number of queries however much history the learner has', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $shortHistory = learnerProgressHistory(1);
    $longHistory = learnerProgressHistory(6);

    $shortHistoryQueries = learnerProgressQueryCount($shortHistory);
    $longHistoryQueries = learnerProgressQueryCount($longHistory);

    expect($longHistoryQueries)->toBe($shortHistoryQueries);
    expect($longHistoryQueries)->toBeLessThanOrEqual(14);
});
