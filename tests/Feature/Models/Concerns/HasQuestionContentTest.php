<?php

use App\Enums\QuestionType;
use App\Models\QuestionBankItem;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\Storage;

dataset('question models', [
    'quiz question' => QuizQuestion::class,
    'question bank item' => QuestionBankItem::class,
]);

it('accepts only the correct choice index of a multiple choice question', function (string $model, ?string $answer, bool $isCorrect) {
    $question = new $model([
        'type' => QuestionType::MultipleChoice,
        'choices' => ['Candy', 'Soft drinks', 'Fish', 'Potato chips'],
        'correct_answer' => '2',
    ]);

    expect($question->isCorrectAnswer($answer))->toBe($isCorrect);
})->with('question models')->with([
    'correct index' => ['2', true],
    'correct index with spaces' => [' 2 ', true],
    'other index' => ['1', false],
    'choice text instead of index' => ['Fish', false],
    'missing answer' => [null, false],
    'empty answer' => ['', false],
]);

it('accepts only the correct index of a true or false question', function (string $model, string $answer, bool $isCorrect) {
    $question = new $model([
        'type' => QuestionType::TrueFalse,
        'choices' => ['True', 'False'],
        'correct_answer' => '1',
    ]);

    expect($question->isCorrectAnswer($answer))->toBe($isCorrect);
})->with('question models')->with([
    'false' => ['1', true],
    'true' => ['0', false],
]);

it('accepts any listed identification answer regardless of letter case and spacing', function (string $model, ?string $answer, bool $isCorrect) {
    $question = new $model([
        'type' => QuestionType::Identification,
        'choices' => null,
        'correct_answer' => 'Vitamin C| Vit C ',
    ]);

    expect($question->isCorrectAnswer($answer))->toBe($isCorrect);
})->with('question models')->with([
    'exact first answer' => ['Vitamin C', true],
    'lowercase' => ['vitamin c', true],
    'surrounding and repeated spaces' => ['  VITAMIN   C ', true],
    'second accepted answer' => ['vit c', true],
    'unlisted spelling' => ['vit. c', false],
    'wrong answer' => ['Vitamin D', false],
    'missing answer' => [null, false],
    'blank answer' => ['   ', false],
]);

it('lists the accepted identification answers trimmed and without empty entries', function (string $model) {
    $question = new $model([
        'type' => QuestionType::Identification,
        'correct_answer' => ' Vitamin C | |Vit   C ',
    ]);

    expect($question->acceptedAnswers())->toBe(['Vitamin C', 'Vit C']);
})->with('question models');

it('describes the correct answer as display text', function (string $model, array $attributes, string $correctAnswerText) {
    $question = new $model($attributes);

    expect($question->correctAnswerText())->toBe($correctAnswerText);
})->with('question models')->with([
    'multiple choice' => [['type' => QuestionType::MultipleChoice, 'choices' => ['Candy', 'Fish', 'Soda'], 'correct_answer' => '1'], 'Fish'],
    'true or false' => [['type' => QuestionType::TrueFalse, 'choices' => ['True', 'False'], 'correct_answer' => '0'], 'True'],
    'identification' => [['type' => QuestionType::Identification, 'correct_answer' => 'Go foods|Go food'], 'Go foods / Go food'],
    'choice index out of range' => [['type' => QuestionType::MultipleChoice, 'choices' => ['Candy', 'Fish'], 'correct_answer' => '4'], ''],
]);

it('describes a learner answer as display text', function (string $model, array $attributes, ?string $answer, string $answerText) {
    $question = new $model($attributes);

    expect($question->answerText($answer))->toBe($answerText);
})->with('question models')->with([
    'chosen choice' => [['type' => QuestionType::MultipleChoice, 'choices' => ['Candy', 'Fish'], 'correct_answer' => '1'], '0', 'Candy'],
    'true or false choice' => [['type' => QuestionType::TrueFalse, 'choices' => ['True', 'False'], 'correct_answer' => '0'], '1', 'False'],
    'index without a choice' => [['type' => QuestionType::MultipleChoice, 'choices' => ['Candy', 'Fish'], 'correct_answer' => '1'], '7', '7'],
    'typed answer' => [['type' => QuestionType::Identification, 'correct_answer' => 'Calcium'], '  calcium   powder ', 'calcium powder'],
    'no answer' => [['type' => QuestionType::Identification, 'correct_answer' => 'Calcium'], null, 'No answer'],
    'blank answer' => [['type' => QuestionType::MultipleChoice, 'choices' => ['Candy', 'Fish'], 'correct_answer' => '1'], '  ', 'No answer'],
]);

it('letters the choices from A', function (string $model, int $index, string $letter) {
    expect((new $model)->choiceLetter($index))->toBe($letter);
})->with('question models')->with([
    'first choice' => [0, 'A'],
    'second choice' => [1, 'B'],
    'sixth choice' => [5, 'F'],
]);

it('builds a privacy-friendly embed URL only for YouTube and Vimeo links', function (string $model, ?string $videoUrl, ?string $embedUrl) {
    $question = new $model(['video_url' => $videoUrl]);

    expect($question->videoEmbedUrl())->toBe($embedUrl);
})->with('question models')->with([
    'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'youtube watch with extra parameters' => ['https://youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=42s', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'youtube mobile watch' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'youtube over http' => ['http://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'youtu.be short link' => ['https://youtu.be/dQw4w9WgXcQ?si=Xk2', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'youtube shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'youtube embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'vimeo' => ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871'],
    'vimeo with www' => ['https://www.vimeo.com/76979871', 'https://player.vimeo.com/video/76979871'],
    'unknown host' => ['https://example.com/watch?v=dQw4w9WgXcQ', null],
    'look-alike host' => ['https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ', null],
    'host hidden behind user info' => ['https://youtube.com@evil.test/watch?v=dQw4w9WgXcQ', null],
    'unlisted youtube path' => ['https://www.youtube.com/live/dQw4w9WgXcQ', null],
    'youtube watch without an id' => ['https://www.youtube.com/watch?list=PL123', null],
    'malformed youtube id' => ['https://youtu.be/dQw4w9"><script>', null],
    'vimeo page that is not a video' => ['https://vimeo.com/channels/staffpicks', null],
    'javascript link' => ['javascript:alert(1)', null],
    'not a url' => ['watch this video', null],
    'malformed url' => ['https:///youtu.be/dQw4w9WgXcQ', null],
    'no link' => [null, null],
]);

it('offers the video link for a plain external link only when it is an http or https URL', function (string $model, ?string $videoUrl, ?string $linkUrl) {
    $question = new $model(['video_url' => $videoUrl]);

    expect($question->videoLinkUrl())->toBe($linkUrl);
})->with('question models')->with([
    'https link' => ['https://example.com/videos/healthy-plate', 'https://example.com/videos/healthy-plate'],
    'http link with spaces' => [' http://example.com/v/1 ', 'http://example.com/v/1'],
    'javascript link' => ['javascript:alert(1)', null],
    'data link' => ['data:text/html;base64,PHNjcmlwdD4=', null],
    'ftp link' => ['ftp://example.com/video.mp4', null],
    'no link' => [null, null],
]);

it('serves uploaded images and videos from the public disk', function (string $model) {
    Storage::fake('public');
    $question = new $model([
        'image_path' => 'quiz-media/images/plate.png',
        'video_path' => 'quiz-media/videos/plate.mp4',
    ]);

    expect($question->imageUrl())->toBe('/storage/quiz-media/images/plate.png');
    expect($question->videoFileUrl())->toBe('/storage/quiz-media/videos/plate.mp4');
})->with('question models');

it('has no media URLs when nothing was uploaded', function (string $model) {
    $question = new $model;

    expect($question->imageUrl())->toBeNull();
    expect($question->videoFileUrl())->toBeNull();
})->with('question models');

it('reports whether the question has an image, a video file or a video link', function (string $model, array $media, bool $hasMedia) {
    expect((new $model($media))->hasMedia())->toBe($hasMedia);
})->with('question models')->with([
    'no media' => [[], false],
    'image' => [['image_path' => 'quiz-media/images/plate.png'], true],
    'video file' => [['video_path' => 'quiz-media/videos/plate.mp4'], true],
    'video link' => [['video_url' => 'https://youtu.be/dQw4w9WgXcQ'], true],
]);

it('casts the type to the enum and keeps the choices as a list', function (string $model) {
    $question = $model::factory()->create([
        'type' => QuestionType::TrueFalse,
        'choices' => ['True', 'False'],
        'correct_answer' => '0',
    ]);

    $storedQuestion = $question->fresh();

    expect($storedQuestion->type)->toBe(QuestionType::TrueFalse);
    expect($storedQuestion->choices)->toBe(['True', 'False']);
})->with('question models');
