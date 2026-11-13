<?php

use App\Enums\QuestionType;
use Database\Seeders\Support\Catalog\ContentCatalog;
use Database\Seeders\Support\Catalog\PeopleCatalog;
use Database\Seeders\Support\Catalog\QuestionCatalog;
use Illuminate\Support\Str;

it('has about eighty bank questions plus questions teachers wrote directly in quizzes', function () {
    $questions = collect(QuestionCatalog::all());

    expect($questions->where('bank', true))->toHaveCount(80);
    expect($questions->where('bank', false))->toHaveCount(28);
});

it('gives every question a unique prompt and an explanation', function () {
    $questions = QuestionCatalog::all();

    expect(array_unique(array_column($questions, 'prompt')))->toHaveCount(count($questions));
    expect(array_filter($questions, fn (array $question): bool => trim($question['explanation']) === ''))->toBeEmpty();
});

it('mixes question types and difficulties, mostly reviewed and many shared', function () {
    $bank = collect(QuestionCatalog::all())->where('bank', true);

    expect($bank->pluck('type')->unique()->values()->all())->toEqualCanonicalizing(QuestionType::cases());
    expect($bank->countBy('difficulty')->all())->toBe(['easy' => 26, 'medium' => 46, 'hard' => 8]);
    expect($bank->where('reviewed', true))->toHaveCount(73);
    expect($bank->where('shared', true))->toHaveCount(46);
    expect(collect(QuestionCatalog::all())->where('bank', false)->where('shared', true))->toBeEmpty();
});

it('ties every question to a module of a subject its author teaches', function () {
    $teachers = PeopleCatalog::teachers();

    foreach (QuestionCatalog::all() as $question) {
        $module = ContentCatalog::moduleByCode($question['module']);

        expect($teachers[$question['owner']]['subjects'])->toContain($module['subject']);
    }
});

it('stores the correct choice of a multiple choice question at the index of its answer key', function () {
    foreach (collect(QuestionCatalog::all())->where('type', QuestionType::MultipleChoice) as $question) {
        $content = QuestionCatalog::content($question);

        expect($question['choices'])->toHaveCount(4);
        expect(array_unique(array_map('mb_strtolower', $question['choices'])))->toHaveCount(4);
        expect($content['choices'])->toEqualCanonicalizing($question['choices']);
        expect($content['choices'][(int) $content['correct_answer']])->toBe($question['choices'][0]);
    }
});

it('arranges the choices the same way on every run and does not always put the answer first', function () {
    $multipleChoice = collect(QuestionCatalog::all())->where('type', QuestionType::MultipleChoice);
    $answerKeys = $multipleChoice->map(fn (array $question): string => QuestionCatalog::content($question)['correct_answer']);

    expect($multipleChoice->map(fn (array $question): array => QuestionCatalog::content($question))->all())
        ->toBe($multipleChoice->map(fn (array $question): array => QuestionCatalog::content($question))->all());
    expect($answerKeys->unique()->sort()->values()->all())->toBe(['0', '1', '2', '3']);
});

it('stores true or false questions with the True and False choices', function () {
    foreach (collect(QuestionCatalog::all())->where('type', QuestionType::TrueFalse) as $question) {
        $content = QuestionCatalog::content($question);

        expect($content['choices'])->toBe(['True', 'False']);
        expect($content['correct_answer'])->toBe($question['true_statement'] ? '0' : '1');
    }
});

it('stores the accepted answers of identification questions and keeps the typical wrong answers wrong', function () {
    foreach (collect(QuestionCatalog::all())->where('type', QuestionType::Identification) as $question) {
        $content = QuestionCatalog::content($question);
        $accepted = array_map(fn (string $answer): string => Str::lower(Str::squish($answer)), $question['accepted']);

        expect($content['choices'])->toBeNull();
        expect($content['correct_answer'])->toBe(implode('|', $question['accepted']));
        expect(mb_strlen($content['correct_answer']))->toBeLessThanOrEqual(500);
        expect($question['wrong'])->not->toBeEmpty();

        foreach ($question['wrong'] as $wrongAnswer) {
            expect($accepted)->not->toContain(Str::lower(Str::squish($wrongAnswer)));
        }
    }
});

it('awards more points for hard questions and typed answers', function (QuestionType $type, string $difficulty, int $points) {
    expect(QuestionCatalog::pointsFor(['type' => $type, 'difficulty' => $difficulty]))->toBe($points);
})->with([
    'easy true or false' => [QuestionType::TrueFalse, 'easy', 1],
    'hard true or false' => [QuestionType::TrueFalse, 'hard', 1],
    'medium multiple choice' => [QuestionType::MultipleChoice, 'medium', 1],
    'hard multiple choice' => [QuestionType::MultipleChoice, 'hard', 2],
    'medium identification' => [QuestionType::Identification, 'medium', 2],
    'hard identification' => [QuestionType::Identification, 'hard', 3],
]);

it('finds every question by its prompt', function () {
    $byPrompt = QuestionCatalog::byPrompt();

    foreach (QuestionCatalog::all() as $question) {
        expect($byPrompt[$question['prompt']])->toBe($question);
    }
});
