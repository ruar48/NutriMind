<?php

use App\Models\Classroom;
use App\Models\LessonPlan;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

dataset('subject-scoped factories', [
    'classroom' => fn () => Classroom::factory(),
    'quiz' => fn () => Quiz::factory(),
    'question bank item' => fn () => QuestionBankItem::factory(),
    'lesson plan' => fn () => LessonPlan::factory(),
]);

it('assigns the record subject to its teacher', function (Factory $factory) {
    $record = $factory->create();

    expect(User::query()->findOrFail($record->teacher_id)->teachesSubject($record->subject_id))->toBeTrue();
})->with('subject-scoped factories');

it('assigns the record subject to a given teacher without removing their other subjects', function (Factory $factory) {
    $existingSubject = Subject::factory()->create();
    $teacher = User::factory()->teaching($existingSubject)->create();

    $record = $factory->for($teacher, 'teacher')->create();

    expect($teacher->assignedSubjects()->pluck('subjects.id')->all())
        ->toEqualCanonicalizing([$existingSubject->id, $record->subject_id]);
})->with('subject-scoped factories');

it('does not assign a subject the teacher already teaches twice', function (Factory $factory) {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();

    $factory->for($teacher, 'teacher')->for($subject)->create();

    $this->assertDatabaseCount('subject_teacher', 1);
})->with('subject-scoped factories');

it('leaves the record subject unassigned for a revoked subject', function (Factory $factory) {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();

    $factory->revokedSubject()->for($teacher, 'teacher')->for($subject)->create();

    expect($teacher->teachesSubject($subject))->toBeFalse();
})->with('subject-scoped factories');

it('leaves a question bank item without a teacher unassigned to anyone', function () {
    QuestionBankItem::factory()->create(['teacher_id' => null]);

    $this->assertDatabaseCount('subject_teacher', 0);
});
