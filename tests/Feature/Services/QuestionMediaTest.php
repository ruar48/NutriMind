<?php

use App\Enums\QuestionType;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Services\QuestionMedia;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('formats size limits for people', function (int $kilobytes, string $label) {
    expect(QuestionMedia::formatKilobytes($kilobytes))->toBe($label);
})->with([
    [500, '500 KB'],
    [1024, '1 MB'],
    [1536, '1.5 MB'],
    [2048, '2 MB'],
    [5120, '5 MB'],
]);

it('uses the configured limits, capped by the PHP upload limit', function () {
    $phpLimit = (int) floor(UploadedFile::getMaxFilesize() / 1024);
    config(['nutrimind.uploads.image_max_kb' => 300, 'nutrimind.uploads.video_max_kb' => $phpLimit + 1000]);
    $media = app(QuestionMedia::class);

    expect($media->imageMaxKilobytes())->toBe(min(300, $phpLimit))
        ->and($media->videoMaxKilobytes())->toBe($phpLimit)
        ->and($media->videoLimitLabel())->toBe(QuestionMedia::formatKilobytes($phpLimit));
});

it('stores new uploads under generated names on the public disk', function () {
    Storage::fake('public');
    $question = QuizQuestion::factory()->make(['quiz_id' => Quiz::factory()->create()->id]);

    $changed = app(QuestionMedia::class)->save($question, [
        'image' => UploadedFile::fake()->image('My Plate.png'),
        'video' => UploadedFile::fake()->create('Lesson One.webm', 200, 'video/webm'),
    ]);

    expect($changed)->toBeTrue()
        ->and($question->exists)->toBeTrue()
        ->and($question->image_path)->toMatch('#^quiz-media/images/[A-Za-z0-9]{40}\.png$#')
        ->and($question->video_path)->toMatch('#^quiz-media/videos/[A-Za-z0-9]{40}\.webm$#');
    Storage::disk('public')->assertExists([$question->image_path, $question->video_path]);
});

it('replaces and removes files, deleting the old ones', function () {
    Storage::fake('public');
    Storage::disk('public')->put('quiz-media/images/old.png', 'image');
    Storage::disk('public')->put('quiz-media/videos/old.mp4', 'video');
    $question = QuizQuestion::factory()->create(['image_path' => 'quiz-media/images/old.png', 'video_path' => 'quiz-media/videos/old.mp4']);

    app(QuestionMedia::class)->save($question, [
        'image' => UploadedFile::fake()->image('new.jpg'),
        'remove_video' => true,
    ]);

    expect($question->refresh()->image_path)->not->toBe('quiz-media/images/old.png')
        ->and($question->video_path)->toBeNull();
    Storage::disk('public')->assertExists($question->image_path);
    Storage::disk('public')->assertMissing(['quiz-media/images/old.png', 'quiz-media/videos/old.mp4']);
});

it('reports when nothing about the question changed', function () {
    $question = QuizQuestion::factory()->create();

    expect(app(QuestionMedia::class)->save($question, ['remove_image' => false]))->toBeFalse();
});

it('discards a new upload when saving the question fails', function () {
    Storage::fake('public');
    $question = QuizQuestion::factory()->make(['quiz_id' => 999999]);

    expect(fn () => app(QuestionMedia::class)->save($question, ['image' => UploadedFile::fake()->image('plate.png')]))
        ->toThrow(QueryException::class);

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('keeps the old files and discards the new ones when the transaction rolls back', function () {
    Storage::fake('public');
    Storage::disk('public')->put('quiz-media/images/old.png', 'image');
    $question = QuizQuestion::factory()->create(['image_path' => 'quiz-media/images/old.png']);

    try {
        DB::transaction(function () use ($question): void {
            app(QuestionMedia::class)->save($question, ['image' => UploadedFile::fake()->image('new.png')]);

            throw new RuntimeException('Something else failed.');
        });
    } catch (RuntimeException) {
    }

    expect(Storage::disk('public')->allFiles())->toBe(['quiz-media/images/old.png']);
});

it('copies files to new paths and skips missing ones', function () {
    Storage::fake('public');
    Storage::disk('public')->put('quiz-media/images/source.webp', 'image');
    $item = QuestionBankItem::factory()->create(['image_path' => 'quiz-media/images/source.webp', 'video_path' => 'quiz-media/videos/gone.mp4']);

    $copies = app(QuestionMedia::class)->copyFiles($item);

    expect($copies['video_path'])->toBeNull()
        ->and($copies['image_path'])->toMatch('#^quiz-media/images/[A-Za-z0-9]{40}\.webp$#');
    Storage::disk('public')->assertExists([$copies['image_path'], 'quiz-media/images/source.webp']);
});

it('gives a question’s content with media copies for a new record', function () {
    Storage::fake('public');
    Storage::disk('public')->put('quiz-media/images/source.png', 'image');
    $question = QuizQuestion::factory()->trueFalse()->create([
        'prompt' => 'Fruits are Glow foods.',
        'correct_answer' => '0',
        'explanation' => 'They give vitamins.',
        'image_path' => 'quiz-media/images/source.png',
        'video_url' => 'https://vimeo.com/76979871',
    ]);

    $content = app(QuestionMedia::class)->duplicateContent($question);

    expect($content)->toMatchArray([
        'type' => QuestionType::TrueFalse,
        'prompt' => 'Fruits are Glow foods.',
        'choices' => ['True', 'False'],
        'correct_answer' => '0',
        'explanation' => 'They give vitamins.',
        'video_url' => 'https://vimeo.com/76979871',
        'video_path' => null,
    ])->and($content['image_path'])->not->toBe('quiz-media/images/source.png');
});

it('deletes only files that no question or bank item still uses', function () {
    Storage::fake('public');
    Storage::disk('public')->put('quiz-media/images/unused.png', 'image');
    Storage::disk('public')->put('quiz-media/images/question.png', 'image');
    Storage::disk('public')->put('quiz-media/videos/bank.mp4', 'video');
    QuizQuestion::factory()->create(['image_path' => 'quiz-media/images/question.png']);
    QuestionBankItem::factory()->create(['video_path' => 'quiz-media/videos/bank.mp4']);

    app(QuestionMedia::class)->delete('quiz-media/images/unused.png', 'quiz-media/images/question.png', 'quiz-media/videos/bank.mp4', null, '');

    Storage::disk('public')->assertMissing('quiz-media/images/unused.png');
    Storage::disk('public')->assertExists(['quiz-media/images/question.png', 'quiz-media/videos/bank.mp4']);
});

it('waits for the transaction to commit before deleting files', function () {
    Storage::fake('public');
    Storage::disk('public')->put('quiz-media/images/kept.png', 'image');

    try {
        DB::transaction(function (): void {
            app(QuestionMedia::class)->delete('quiz-media/images/kept.png');

            throw new RuntimeException('Rolled back.');
        });
    } catch (RuntimeException) {
    }

    Storage::disk('public')->assertExists('quiz-media/images/kept.png');
});

it('lists the media paths of several questions once', function () {
    $questions = [
        new QuizQuestion(['image_path' => 'quiz-media/images/a.png', 'video_path' => null]),
        new QuizQuestion(['image_path' => 'quiz-media/images/a.png', 'video_path' => 'quiz-media/videos/b.mp4']),
        new QuizQuestion(['image_path' => null, 'video_path' => null]),
    ];

    expect(app(QuestionMedia::class)->pathsOf($questions))->toBe(['quiz-media/images/a.png', 'quiz-media/videos/b.mp4']);
});
