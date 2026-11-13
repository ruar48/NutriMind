<?php

use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

use function Pest\Laravel\freezeTime;

it('lets a teacher access their own classrooms in assigned subjects', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create();

    $accessibleIds = Classroom::query()->accessibleBy($teacher)->pluck('id')->all();

    expect($accessibleIds)->toBe([$classroom->id]);
});

it('excludes classrooms whose subject was revoked from the teacher', function () {
    $keptSubject = Subject::factory()->create();
    $revokedSubject = Subject::factory()->create();
    $teacher = User::factory()->teaching($keptSubject, $revokedSubject)->create();
    $keptClassroom = Classroom::factory()->for($teacher, 'teacher')->for($keptSubject)->create();
    Classroom::factory()->for($teacher, 'teacher')->for($revokedSubject)->create();
    $teacher->assignedSubjects()->detach($revokedSubject);

    $accessibleIds = Classroom::query()->accessibleBy($teacher->refresh())->pluck('id')->all();

    expect($accessibleIds)->toBe([$keptClassroom->id]);
});

it('excludes classrooms of other teachers in the same subject', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    Classroom::factory()->for(User::factory()->teaching($subject), 'teacher')->for($subject)->create();

    $accessibleIds = Classroom::query()->accessibleBy($teacher)->pluck('id')->all();

    expect($accessibleIds)->toBeEmpty();
});

it('combines with the learner enrollments to find classrooms a teacher may view', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create();
    $student = User::factory()->student()->enrolledIn($classroom, Classroom::factory()->create())->create();

    $sharedClassroomIds = $student->enrolledClassrooms()->accessibleBy($teacher)->pluck('classrooms.id')->all();

    expect($sharedClassroomIds)->toBe([$classroom->id]);
});

it('separates active classrooms from archived ones', function () {
    $activeClassroom = Classroom::factory()->create();
    $archivedClassroom = Classroom::factory()->archived()->create();

    expect(Classroom::query()->active()->pluck('id')->all())->toBe([$activeClassroom->id]);
    expect(Classroom::query()->archived()->pluck('id')->all())->toBe([$archivedClassroom->id]);
    expect($activeClassroom->isArchived())->toBeFalse();
    expect($archivedClassroom->isArchived())->toBeTrue();
});

it('generates a six-character class code from the unambiguous alphabet when created without one', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();

    $classroom = $teacher->classrooms()->create([
        'school_year_id' => SchoolYear::factory()->create()->id,
        'subject_id' => $subject->id,
        'name' => 'Grade 5 – Rizal',
        'grade_level' => 5,
        'section' => 'Rizal',
    ]);

    expect($classroom->class_code)->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/');
    $this->assertDatabaseHas('classrooms', ['id' => $classroom->id, 'class_code' => $classroom->class_code]);
});

it('keeps a class code that is given explicitly', function () {
    $classroom = Classroom::factory()->create(['class_code' => 'GREEN5']);

    expect($classroom->class_code)->toBe('GREEN5');
});

it('generates class codes only from the alphabet even when the random source contains other characters', function () {
    Str::createRandomStringsUsingSequence(['a0O1I-K7m9Q2xR8z']);

    expect(Classroom::generateClassCode())->toBe('K79Q2R');
});

it('skips class codes that another classroom already uses', function () {
    Classroom::factory()->create(['class_code' => 'ABC234']);
    Str::createRandomStringsUsingSequence(['ABC234', 'XYZ789']);

    expect(Classroom::generateClassCode())->toBe('XYZ789');
});

it('skips the class codes it is told to avoid', function () {
    Str::createRandomStringsUsingSequence(['ABC234', 'XYZ789']);

    expect(Classroom::generateClassCode(['ABC234']))->toBe('XYZ789');
});

it('still finds an unused class code when the random string source keeps repeating a used one', function () {
    Classroom::factory()->create(['class_code' => 'ABC234']);
    Str::createRandomStringsUsing(fn (): string => 'ABC234');

    $code = Classroom::generateClassCode();

    expect($code)->not->toBe('ABC234')->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/');
});

it('still builds a full class code when the random string source has too few alphabet characters', function () {
    Str::createRandomStringsUsing(fn (): string => 'aaaa1');

    expect(Classroom::generateClassCode())->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/');
});

it('does not let the class code be mass assigned', function () {
    $classroom = new Classroom(['name' => 'Grade 5 – Rizal', 'class_code' => 'HACKED']);

    expect($classroom->class_code)->toBeNull();
});

it('records when each learner was enrolled', function () {
    freezeTime();
    $classroom = Classroom::factory()->create();
    $student = User::factory()->student()->create();

    $classroom->students()->attach($student);
    $enrolledAt = $classroom->students()->sole()->pivot->enrolled_at;

    expect($enrolledAt)->toBeInstanceOf(Carbon::class);
    expect($enrolledAt->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('keeps an enrollment date that is given explicitly', function () {
    $classroom = Classroom::factory()->create();
    $student = User::factory()->student()->create();

    $classroom->students()->attach($student, ['enrolled_at' => '2026-06-16 07:30:00']);

    expect($student->enrolledClassrooms()->sole()->pivot->enrolled_at->toDateTimeString())->toBe('2026-06-16 07:30:00');
});

it('records the assignment and availability window of each quiz', function () {
    freezeTime();
    $classroom = Classroom::factory()->create();
    $quiz = Quiz::factory()->published()->create();

    $classroom->quizzes()->attach($quiz, ['available_from' => '2026-11-16 08:00:00', 'due_at' => '2026-11-20 17:00:00']);
    $assignment = $quiz->classrooms()->sole()->pivot;

    expect($assignment->assigned_at->toDateTimeString())->toBe(now()->toDateTimeString());
    expect($assignment->available_from->toDateTimeString())->toBe('2026-11-16 08:00:00');
    expect($assignment->due_at->toDateTimeString())->toBe('2026-11-20 17:00:00');
});

it('reaches its school year, subject, teacher and quiz attempts', function () {
    $classroom = Classroom::factory()->create();
    $attempt = QuizAttempt::factory()->for($classroom)->create();

    expect($classroom->schoolYear->is($classroom->schoolYear()->sole()))->toBeTrue();
    expect($classroom->subject->id)->toBe($classroom->subject_id);
    expect($classroom->teacher->id)->toBe($classroom->teacher_id);
    expect($classroom->quizAttempts()->sole()->is($attempt))->toBeTrue();
});
