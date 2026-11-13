<?php

use App\Enums\GradeLevel;
use App\Enums\LearningMode;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

it('names the classroom after its grade and section', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Six, 'section' => 'Sampaguita']);

    expect($classroom->name)->toBe('Grade 6 – Sampaguita');
});

it('creates an active classroom on the automatic path with a class code', function () {
    $classroom = Classroom::factory()->create()->fresh();

    expect($classroom->isArchived())->toBeFalse();
    expect($classroom->learning_mode)->toBe(LearningMode::Automatic);
    expect($classroom->class_code)->toMatch('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/');
});

it('gives every classroom of a batch its own class code even when random strings repeat', function () {
    Str::createRandomStringsUsing(fn (): string => 'ABC234');

    $classrooms = Classroom::factory()->count(3)->create();

    expect($classrooms->pluck('class_code')->unique())->toHaveCount(3);
});

it('creates classrooms and enrollments while model events are disabled', function () {
    $student = Model::withoutEvents(
        fn () => User::factory()->student()->enrolledIn(Classroom::factory()->create())->create(),
    );

    expect($student->enrolledClassrooms()->sole()->class_code)->not->toBeNull();
    expect($student->enrolledClassrooms()->sole()->pivot->enrolled_at)->not->toBeNull();
});

it('creates archived classrooms', function () {
    expect(Classroom::factory()->archived()->create()->isArchived())->toBeTrue();
});
