<?php

use App\Models\QuestionBankItem;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\User;

it('shows a teacher their own items in assigned subjects', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $ownItem = QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create();

    $visibleIds = QuestionBankItem::query()->visibleTo($teacher)->pluck('id')->all();

    expect($visibleIds)->toBe([$ownItem->id]);
});

it('shows a teacher the items other teachers shared in an assigned subject', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $sharedItem = QuestionBankItem::factory()->shared()->for($subject)->create();

    $visibleIds = QuestionBankItem::query()->visibleTo($teacher)->pluck('id')->all();

    expect($visibleIds)->toBe([$sharedItem->id]);
});

it('hides the unshared items of other teachers', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    QuestionBankItem::factory()->for($subject)->create();

    expect(QuestionBankItem::query()->visibleTo($teacher)->count())->toBe(0);
});

it('hides shared items of subjects the teacher is not assigned to', function () {
    $teacher = User::factory()->teaching()->create();
    QuestionBankItem::factory()->shared()->create();

    expect(QuestionBankItem::query()->visibleTo($teacher)->count())->toBe(0);
});

it('hides even the teacher\'s own items once their subject is revoked', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    QuestionBankItem::factory()->for($teacher, 'teacher')->for($subject)->create();
    $teacher->assignedSubjects()->detach($subject);

    expect(QuestionBankItem::query()->visibleTo($teacher->refresh())->count())->toBe(0);
});

it('keeps the other constraints of the query when scoping to visible items', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $reviewedItem = QuestionBankItem::factory()->reviewed()->for($teacher, 'teacher')->for($subject)->create();
    QuestionBankItem::factory()->shared()->for($subject)->create();

    $visibleReviewedIds = QuestionBankItem::query()->reviewed()->visibleTo($teacher)->pluck('id')->all();

    expect($visibleReviewedIds)->toBe([$reviewedItem->id]);
});

it('filters items a teacher marked as reviewed', function () {
    $reviewedItem = QuestionBankItem::factory()->reviewed()->create();
    QuestionBankItem::factory()->create();

    expect(QuestionBankItem::query()->reviewed()->pluck('id')->all())->toBe([$reviewedItem->id]);
});

it('reaches the quiz questions copied from the item', function () {
    $item = QuestionBankItem::factory()->create();
    $copy = QuizQuestion::factory()->for($item, 'bankItem')->create();

    expect($item->quizQuestions()->sole()->is($copy))->toBeTrue();
    expect($copy->bankItem->is($item))->toBeTrue();
});

it('keeps an item when its teacher account is deleted', function () {
    $item = QuestionBankItem::factory()->create();

    $item->teacher->delete();

    expect($item->fresh()->teacher_id)->toBeNull();
});
