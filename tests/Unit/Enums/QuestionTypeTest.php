<?php

use App\Enums\QuestionType;

it('answers with stored choices only for multiple choice and true or false questions', function (QuestionType $type, bool $usesChoices) {
    expect($type->usesChoices())->toBe($usesChoices);
})->with([
    'multiple choice' => [QuestionType::MultipleChoice, true],
    'true or false' => [QuestionType::TrueFalse, true],
    'identification' => [QuestionType::Identification, false],
]);
