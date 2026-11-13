<?php

use App\Enums\GradeLevel;
use App\Models\LearningModule;
use App\Models\QuestionBankItem;

it('creates a private, unreviewed and unused item by default', function () {
    $item = QuestionBankItem::factory()->create()->fresh();

    expect($item->is_shared)->toBeFalse();
    expect($item->reviewed_at)->toBeNull();
    expect($item->times_used)->toBe(0);
});

it('creates shared and reviewed items', function () {
    expect(QuestionBankItem::factory()->shared()->create()->is_shared)->toBeTrue();
    expect(QuestionBankItem::factory()->reviewed()->create()->reviewed_at)->not->toBeNull();
});

it('takes the subject and grade level of its learning module', function () {
    $module = LearningModule::factory()->create(['grade_level' => GradeLevel::Five]);

    $item = QuestionBankItem::factory()->for($module, 'module')->create();

    expect($item->subject_id)->toBe($module->subject_id);
    expect($item->grade_level)->toBe(GradeLevel::Five);
});
