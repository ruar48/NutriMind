<?php

use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use App\Services\Analytics\TeacherOverview;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
});

it('lists the teacher’s non-archived accessible classrooms with their active learners', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $rosal = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['name' => 'Rosal']);
    $mabini = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['name' => 'Mabini']);
    Classroom::factory()->for($teacher, 'teacher')->for($subject)->archived()->create(['name' => 'Archived']);
    Classroom::factory()->for($teacher, 'teacher')->revokedSubject()->create(['name' => 'Revoked']);
    Classroom::factory()->for($subject)->create(['name' => 'Someone else']);
    User::factory()->student()->enrolledIn($rosal)->count(2)->create();
    User::factory()->student()->inactive()->enrolledIn($rosal)->create();
    $teacher->refresh();

    $classrooms = app(TeacherOverview::class)->classrooms($teacher);

    expect($classrooms->modelKeys())->toBe([$mabini->id, $rosal->id])
        ->and($classrooms->pluck('students_count', 'id')->all())->toBe([$mabini->id => 0, $rosal->id => 2])
        ->and($classrooms->first()->relationLoaded('subject'))->toBeTrue();
});

it('counts the assigned learner–quiz pairs that learners can take and those already submitted', function () {
    $classroom = Classroom::factory()->create();
    $other = Classroom::factory()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create();
    [$ana, $ben] = User::factory()->student()->enrolledIn($classroom)->count(2)->create();
    User::factory()->student()->inactive()->enrolledIn($classroom)->create();
    $open = Quiz::factory()->published()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create();
    $closed = Quiz::factory()->closed()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create();
    $classroom->quizzes()->attach($open);
    $classroom->quizzes()->attach($closed, ['available_from' => now()->subDays(3)]);
    $classroom->quizzes()->attach(Quiz::factory()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create());
    $classroom->quizzes()->attach(Quiz::factory()->published()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create(), ['available_from' => now()->addDay()]);
    QuizAttempt::factory()->for($open)->for($ana, 'student')->for($classroom)->create();
    QuizAttempt::factory()->for($open)->for($ana, 'student')->for($classroom)->create();
    QuizAttempt::factory()->inProgress()->for($closed)->for($ben, 'student')->for($classroom)->create();

    $completion = app(TeacherOverview::class)->quizCompletion([$classroom->id, $other->id]);

    expect($completion->all())->toBe([
        $classroom->id => ['assigned' => 4, 'submitted' => 1, 'rate' => 25.0],
    ])->and(app(TeacherOverview::class)->quizCompletion([]))->toBeEmpty();
});

it('lists the published quizzes due in the next seven days with how many learners submitted', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create();
    [$ana] = User::factory()->student()->enrolledIn($classroom)->count(3)->create();
    $soon = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['title' => 'Soon']);
    $later = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['title' => 'Later']);
    $classroom->quizzes()->attach($soon, ['due_at' => now()->addDays(2)]);
    $classroom->quizzes()->attach($later, ['due_at' => now()->addDays(6)]);
    $classroom->quizzes()->attach(Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(), ['due_at' => now()->subHour()]);
    $classroom->quizzes()->attach(Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(), ['due_at' => now()->addDays(9)]);
    $classroom->quizzes()->attach(Quiz::factory()->for($teacher, 'teacher')->for($subject)->create(), ['due_at' => now()->addDay()]);
    QuizAttempt::factory()->for($soon)->for($ana, 'student')->for($classroom)->count(2)->create();
    $overview = app(TeacherOverview::class);
    $teacher->refresh();

    $due = $overview->dueSoon($overview->classrooms($teacher));

    expect(array_map(fn (array $item): array => [$item['quiz']->id, $item['classroom']->id, $item['dueAt']->toDateTimeString(), $item['submitted'], $item['enrolled']], $due))->toBe([
        [$soon->id, $classroom->id, '2026-11-15 10:00:00', 1, 3],
        [$later->id, $classroom->id, '2026-11-19 10:00:00', 0, 3],
    ]);
});

it('counts the distinct published quizzes still due this week', function () {
    $classroom = Classroom::factory()->create();
    $other = Classroom::factory()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create();
    $quiz = Quiz::factory()->published()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create();
    $classroom->quizzes()->attach($quiz, ['due_at' => '2026-11-15 23:00:00']);
    $other->quizzes()->attach($quiz, ['due_at' => '2026-11-14 12:00:00']);
    $classroom->quizzes()->attach(Quiz::factory()->published()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create(), ['due_at' => '2026-11-16 08:00:00']);
    $classroom->quizzes()->attach(Quiz::factory()->published()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create(), ['due_at' => '2026-11-13 09:00:00']);

    expect(app(TeacherOverview::class)->quizzesDueThisWeek([$classroom->id, $other->id]))->toBe(1)
        ->and(app(TeacherOverview::class)->quizzesDueThisWeek([]))->toBe(0);
});

it('lists the latest submitted attempts of active learners in the classrooms', function () {
    $classroom = Classroom::factory()->create();
    $student = User::factory()->student()->enrolledIn($classroom)->create();
    $older = QuizAttempt::factory()->for($student, 'student')->for($classroom)->create(['submitted_at' => now()->subDay()]);
    $newer = QuizAttempt::factory()->for($student, 'student')->for($classroom)->create(['submitted_at' => now()->subHour()]);
    QuizAttempt::factory()->inProgress()->for($student, 'student')->for($classroom)->create();
    QuizAttempt::factory()->for(User::factory()->student()->inactive()->enrolledIn($classroom), 'student')->for($classroom)->create(['submitted_at' => now()]);
    QuizAttempt::factory()->for(Classroom::factory())->create(['submitted_at' => now()]);

    $attempts = app(TeacherOverview::class)->recentSubmissions([$classroom->id]);

    expect($attempts->modelKeys())->toBe([$newer->id, $older->id])
        ->and($attempts->first()->relationLoaded('student'))->toBeTrue()
        ->and($attempts->first()->relationLoaded('quiz'))->toBeTrue()
        ->and(app(TeacherOverview::class)->recentSubmissions([$classroom->id], 1)->modelKeys())->toBe([$newer->id]);
});
