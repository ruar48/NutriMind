<?php

use App\Enums\GradeLevel;
use App\Enums\PerformanceBand;
use App\Models\Classroom;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use App\Services\Monitoring\LearnerRoster;
use App\Services\Monitoring\LearnerRosterRow;
use Illuminate\Database\Eloquent\Collection;

/**
 * Record a submitted attempt with an exact percentage in the classroom.
 */
function learnerRosterAttempt(Quiz $quiz, User $student, Classroom $classroom, float $percentage, bool $submitted = true): QuizAttempt
{
    return QuizAttempt::factory()->for($quiz)->for($student, 'student')->for($classroom)->create([
        'score' => (int) round($percentage),
        'max_score' => 100,
        'percentage' => $percentage,
        'passed' => $percentage >= 75,
        'submitted_at' => $submitted ? now() : null,
    ]);
}

it('averages the best percentage of each quiz submitted in the classrooms', function () {
    $classroom = Classroom::factory()->create();
    $student = User::factory()->student()->enrolledIn($classroom)->create();
    $plate = Quiz::factory()->published()->create();
    $snack = Quiz::factory()->published()->create();
    learnerRosterAttempt($plate, $student, $classroom, 50);
    learnerRosterAttempt($plate, $student, $classroom, 80);
    learnerRosterAttempt($snack, $student, $classroom, 65.5);
    learnerRosterAttempt($snack, $student, $classroom, 100, submitted: false);
    learnerRosterAttempt(Quiz::factory()->published()->create(), $student, Classroom::factory()->create(), 0);

    $scores = app(LearnerRoster::class)->scores([$classroom->id]);

    expect($scores->all())->toBe([$student->id => ['average' => 72.8, 'quizzes' => 2]]);
});

it('takes the band from the rounded average', function () {
    $classroom = Classroom::factory()->create();
    $student = User::factory()->student()->enrolledIn($classroom)->create(['name' => 'Ana Cruz']);
    learnerRosterAttempt(Quiz::factory()->published()->create(), $student, $classroom, 74.9);
    learnerRosterAttempt(Quiz::factory()->published()->create(), $student, $classroom, 75);

    $page = app(LearnerRoster::class)->build(new Collection([$classroom]), null, null, 'name', 'asc');

    $row = $page->rows->items()[0];
    expect($row)->toBeInstanceOf(LearnerRosterRow::class)
        ->and($row->averageScore)->toBe(75.0)
        ->and($row->band)->toBe(PerformanceBand::FairlySatisfactory)
        ->and($page->bandCounts['fairly_satisfactory'])->toBe(1);
});

it('counts missions only in the subjects of the learner’s classrooms in scope', function () {
    $health = Subject::factory()->create();
    $science = Subject::factory()->create();
    $teacher = User::factory()->teaching($health, $science)->create();
    $healthClass = Classroom::factory()->for($teacher, 'teacher')->for($health)->create();
    $scienceClass = Classroom::factory()->for($teacher, 'teacher')->for($science)->create();
    $ana = User::factory()->student()->enrolledIn($healthClass)->create(['name' => 'Ana Cruz']);
    $ben = User::factory()->student()->enrolledIn($healthClass, $scienceClass)->create(['name' => 'Ben Reyes']);
    foreach ([$ana, $ben] as $student) {
        MissionProgress::factory()->completed()->for(Mission::factory()->for($health)->create())->for($student, 'student')->create();
        MissionProgress::factory()->completed()->for(Mission::factory()->for($science)->create())->for($student, 'student')->create();
    }

    $page = app(LearnerRoster::class)->build(new Collection([$healthClass, $scienceClass]), null, null, 'name', 'asc');

    $missions = collect($page->rows->items())->mapWithKeys(fn (LearnerRosterRow $row): array => [$row->student->name => $row->missionsCompleted]);
    expect($missions->all())->toBe(['Ana Cruz' => 1, 'Ben Reyes' => 2]);
});

it('returns an empty page for no classrooms', function () {
    User::factory()->student()->enrolledIn(Classroom::factory()->create())->create();

    $page = app(LearnerRoster::class)->build(new Collection, null, null, 'name', 'asc');

    expect($page->learnerCount)->toBe(0)
        ->and($page->rows->total())->toBe(0)
        ->and($page->averageScore)->toBeNull()
        ->and(array_sum($page->bandCounts))->toBe(0);
});

it('lists the teacher’s active classrooms in assigned subjects with learner counts', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $active = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['name' => 'B Class', 'grade_level' => GradeLevel::Five]);
    Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['name' => 'A Class']);
    Classroom::factory()->for($teacher, 'teacher')->for($subject)->archived()->create(['name' => 'Archived Class']);
    Classroom::factory()->for($teacher, 'teacher')->revokedSubject()->create(['name' => 'Revoked Class']);
    User::factory()->count(2)->student()->enrolledIn($active)->create();

    $classrooms = app(LearnerRoster::class)->classroomsFor($teacher->refresh());

    expect($classrooms->pluck('name')->all())->toBe(['A Class', 'B Class'])
        ->and($classrooms->firstWhere('name', 'B Class')->students_count)->toBe(2);
});
