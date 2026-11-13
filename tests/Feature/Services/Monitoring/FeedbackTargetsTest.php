<?php

use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use App\Services\Monitoring\FeedbackTargets;
use Carbon\CarbonImmutable;

it('offers the learners of the teacher’s active classrooms with their classrooms', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $sampaguita = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['name' => 'Health 5 – Sampaguita']);
    $ilangIlang = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['name' => 'Health 5 – Ilang-Ilang']);
    $archived = Classroom::factory()->for($teacher, 'teacher')->for($subject)->archived()->create(['name' => 'Last Year']);
    $ben = User::factory()->student()->enrolledIn($sampaguita, $ilangIlang, $archived)->create(['name' => 'Ben Reyes']);
    $ana = User::factory()->student()->enrolledIn($sampaguita)->create(['name' => 'Ana Cruz']);
    User::factory()->student()->enrolledIn($archived)->create(['name' => 'Archived Only']);
    User::factory()->student()->enrolledIn(Classroom::factory()->create())->create(['name' => 'Other Teacher']);

    $options = app(FeedbackTargets::class)->learnerOptions($teacher);

    expect($options->all())->toBe([
        $ana->id => 'Ana Cruz · Health 5 – Sampaguita',
        $ben->id => 'Ben Reyes · Health 5 – Ilang-Ilang, Health 5 – Sampaguita',
    ]);
});

it('allows the teacher’s classrooms of the learner, archived ones included', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $active = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create();
    $archived = Classroom::factory()->for($teacher, 'teacher')->for($subject)->archived()->create();
    $withoutLearner = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create();
    $foreign = Classroom::factory()->create();
    $student = User::factory()->student()->enrolledIn($active, $archived, $foreign)->create();
    $targets = app(FeedbackTargets::class);

    expect($targets->classrooms($teacher, $student)->modelKeys())->toEqualCanonicalizing([$active->id, $archived->id])
        ->and($targets->allowsClassroom($teacher, $student, $active->id))->toBeTrue()
        ->and($targets->allowsClassroom($teacher, $student, $archived->id))->toBeTrue()
        ->and($targets->allowsClassroom($teacher, $student, $withoutLearner->id))->toBeFalse()
        ->and($targets->allowsClassroom($teacher, $student, $foreign->id))->toBeFalse();
});

it('allows only the learner’s submitted attempts at the teacher’s quizzes in assigned subjects', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $student = User::factory()->student()->create();
    $own = QuizAttempt::factory()->for(Quiz::factory()->published()->for($teacher, 'teacher')->for($subject))->for($student, 'student')->create();
    $unsubmitted = QuizAttempt::factory()->inProgress()->for(Quiz::factory()->published()->for($teacher, 'teacher')->for($subject))->for($student, 'student')->create();
    $colleague = QuizAttempt::factory()->for(Quiz::factory()->published()->for($subject))->for($student, 'student')->create();
    $revoked = QuizAttempt::factory()->for(Quiz::factory()->published()->for($teacher, 'teacher')->revokedSubject())->for($student, 'student')->create();
    $otherLearner = QuizAttempt::factory()->for(Quiz::factory()->published()->for($teacher, 'teacher')->for($subject))->create();
    $targets = app(FeedbackTargets::class);
    $teacher->refresh();

    expect($targets->attempts($teacher, $student)->modelKeys())->toBe([$own->id])
        ->and($targets->allowsAttempt($teacher, $student, $own->id))->toBeTrue()
        ->and($targets->allowsAttempt($teacher, $student, $unsubmitted->id))->toBeFalse()
        ->and($targets->allowsAttempt($teacher, $student, $colleague->id))->toBeFalse()
        ->and($targets->allowsAttempt($teacher, $student, $revoked->id))->toBeFalse()
        ->and($targets->allowsAttempt($teacher, $student, $otherLearner->id))->toBeFalse();
});

it('offers the 30 latest attempts, newest first', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $student = User::factory()->student()->create();
    $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['max_attempts' => null]);
    $attempts = collect(range(1, 31))->map(fn (int $number): QuizAttempt => QuizAttempt::factory()->for($quiz)->for($student, 'student')
        ->create(['attempt_number' => $number, 'submitted_at' => now()->subDays(40 - $number)]));

    $offered = app(FeedbackTargets::class)->attempts($teacher, $student);

    expect($offered)->toHaveCount(FeedbackTargets::ATTEMPT_CHOICES)
        ->and($offered->first()->id)->toBe($attempts->last()->id)
        ->and($offered->modelKeys())->not->toContain($attempts->first()->id);
});

it('labels an attempt with its quiz, number, score and date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    $attempt = QuizAttempt::factory()->for(Quiz::factory()->published()->create(['title' => 'Healthy Plate Quiz']))->create([
        'attempt_number' => 2, 'percentage' => 66.67, 'submitted_at' => '2026-11-03 08:00:00',
    ]);

    expect(FeedbackTargets::attemptLabel($attempt->load('quiz')))->toBe('Healthy Plate Quiz · Attempt 2 · 66.7% · Nov 3, 2026');
});
