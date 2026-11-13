<?php

use App\Models\Classroom;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;

it('filters active subjects', function () {
    $activeSubject = Subject::factory()->create();
    Subject::factory()->inactive()->create();

    expect(Subject::query()->active()->pluck('id')->all())->toBe([$activeSubject->id]);
});

it('lists the teachers it is assigned to with the assigning administrator', function () {
    $subject = Subject::factory()->create();
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->create();
    $teacher->assignedSubjects()->attach($subject, ['assigned_by' => $admin->id]);

    $assignedTeacher = $subject->teachers()->sole();

    expect($assignedTeacher->is($teacher))->toBeTrue();
    expect($assignedTeacher->pivot->assigned_by)->toBe($admin->id);
});

it('reaches the records of the subject through each has-many relation', function (string $relation, Closure $createRecordFor) {
    $subject = Subject::factory()->create();
    $record = $createRecordFor($subject);
    $createRecordFor(Subject::factory()->create());

    expect($subject->{$relation}()->sole()->is($record))->toBeTrue();
})->with([
    'classrooms' => ['classrooms', fn (Subject $subject) => Classroom::factory()->for($subject)->create()],
    'learning modules' => ['learningModules', fn (Subject $subject) => LearningModule::factory()->for($subject)->create()],
    'missions' => ['missions', fn (Subject $subject) => Mission::factory()->for($subject)->create()],
    'quizzes' => ['quizzes', fn (Subject $subject) => Quiz::factory()->for($subject)->create()],
    'question bank items' => ['questionBankItems', fn (Subject $subject) => QuestionBankItem::factory()->for($subject)->create()],
    'lesson plans' => ['lessonPlans', fn (Subject $subject) => LessonPlan::factory()->for($subject)->create()],
]);
