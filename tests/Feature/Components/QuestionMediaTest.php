<?php

use App\Models\QuestionBankItem;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('renders an uploaded image with alt text from the prompt, lazy loading and a bounded height', function () {
    $question = QuizQuestion::factory()->make([
        'prompt' => 'Which vegetable is shown in the picture?',
        'image_path' => 'quiz-media/images/carrot.png',
    ]);

    $html = (string) $this->blade('<x-question.media :question="$question" />', ['question' => $question]);

    expect($html)
        ->toContain('data-question-media="full"')
        ->toMatch('#<img\s+src="/storage/quiz-media/images/carrot\.png"\s+alt="Illustration: Which vegetable is shown in the picture\?"\s+loading="lazy"\s+decoding="async"#')
        ->toMatch('#<img[^>]*class="[^"]*\bmax-h-72\b[^"]*\bobject-contain\b#')
        ->toMatch('#<img[^>]*class="[^"]*\brounded-xl border\b#')
        ->not->toContain('<video')
        ->not->toContain('<iframe');
});

it('limits the image alt text to the first 80 characters of the prompt', function () {
    $question = QuizQuestion::factory()->make([
        'prompt' => 'Look at the picture of the bahay kubo vegetables and name the one that is rich in vitamin A for good eyesight.',
        'image_path' => 'quiz-media/images/bahay-kubo.jpg',
    ]);

    $this->blade('<x-question.media :question="$question" />', ['question' => $question])
        ->assertSee('alt="Illustration: Look at the picture of the bahay kubo vegetables and name the one that is rich i…"', false);
});

it('embeds a recognized video link in a privacy-friendly 16:9 iframe', function (string $videoUrl, string $embedUrl) {
    $question = QuizQuestion::factory()->make(['video_url' => $videoUrl]);

    $html = (string) $this->blade('<x-question.media :question="$question" />', ['question' => $question]);

    expect($html)
        ->toContain('<iframe')
        ->toContain('src="'.$embedUrl.'"')
        ->toContain('title="Question video"')
        ->toContain('loading="lazy"')
        ->toContain('allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"')
        ->toContain('referrerpolicy="strict-origin-when-cross-origin"')
        ->toMatch('#<iframe[^>]*\sallowfullscreen\s#')
        ->toMatch('#<div class="relative aspect-video\b#')
        ->not->toContain('Watch video')
        ->not->toContain('<video');
})->with([
    'YouTube watch link' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'YouTube short link' => ['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'Vimeo link' => ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871'],
]);

it('plays an uploaded video file with the media type of its extension', function (string $videoPath, string $mediaType) {
    $question = QuizQuestion::factory()->make(['video_path' => $videoPath]);

    $html = (string) $this->blade('<x-question.media :question="$question" />', ['question' => $question]);

    expect($html)
        ->toMatch('#<video\s+controls\s+preload="metadata"\s+playsinline\b#')
        ->toMatch('#<source src="/storage/'.preg_quote($videoPath, '#').'"\s+type="'.preg_quote($mediaType, '#').'"\s*>#')
        ->not->toContain('<iframe')
        ->not->toContain('Watch video');
})->with([
    'mp4' => ['quiz-media/videos/handwashing.mp4', 'video/mp4'],
    'webm' => ['quiz-media/videos/handwashing.webm', 'video/webm'],
    'mov' => ['quiz-media/videos/handwashing.MOV', 'video/quicktime'],
]);

it('leaves the source type to the browser for an unrecognized video extension', function () {
    $question = QuizQuestion::factory()->make(['video_path' => 'quiz-media/videos/handwashing.ogv']);

    $html = (string) $this->blade('<x-question.media :question="$question" />', ['question' => $question]);

    expect($html)
        ->toMatch('#<source src="/storage/quiz-media/videos/handwashing\.ogv"\s*>#')
        ->not->toContain('type="video/');
});

it('prefers the uploaded video file over a video link', function () {
    $question = QuizQuestion::factory()->make([
        'video_path' => 'quiz-media/videos/handwashing.mp4',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $html = (string) $this->blade('<x-question.media :question="$question" />', ['question' => $question]);

    expect($html)
        ->toContain('<video')
        ->not->toContain('<iframe')
        ->not->toContain('youtube');
});

it('links any other video site in a new tab', function () {
    $question = QuizQuestion::factory()->make(['video_url' => 'https://www.example.org/lessons/go-grow-glow']);

    $html = (string) $this->blade('<x-question.media :question="$question" />', ['question' => $question]);

    expect($html)
        ->toMatch('#<a href="https://www\.example\.org/lessons/go-grow-glow"[^>]*\starget="_blank"[^>]*\srel="noopener noreferrer"#')
        ->toContain('Watch video<span class="sr-only"> (opens in a new tab)</span>')
        ->toContain('Opens example.org')
        ->not->toContain('<iframe')
        ->not->toContain('<video');
});

it('shows the image together with the video', function () {
    $question = QuizQuestion::factory()->make([
        'image_path' => 'quiz-media/images/plate.png',
        'video_url' => 'https://vimeo.com/76979871',
    ]);

    $this->blade('<x-question.media :question="$question" />', ['question' => $question])
        ->assertSeeInOrder(['<img', 'src="/storage/quiz-media/images/plate.png"', '<iframe'], false);
});

it('renders nothing for a question without media', function () {
    $question = QuizQuestion::factory()->make();

    $html = (string) $this->blade('<x-question.media :question="$question" />', ['question' => $question]);

    expect(trim($html))->toBe('');
});

it('renders nothing for a video link that is not an http address', function (string $videoUrl) {
    $question = QuizQuestion::factory()->make(['video_url' => $videoUrl]);

    $html = (string) $this->blade('<x-question.media :question="$question" />', ['question' => $question]);

    expect(trim($html))->toBe('');
})->with([
    'javascript scheme' => ['javascript:alert(document.cookie)'],
    'data scheme' => ['data:text/html,<script>alert(1)</script>'],
    'relative path' => ['/videos/handwashing'],
]);

it('shows a thumbnail and a video chip instead of players in compact mode', function () {
    $question = QuizQuestion::factory()->make([
        'prompt' => 'Which vegetable is shown in the picture?',
        'image_path' => 'quiz-media/images/carrot.png',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $html = (string) $this->blade('<x-question.media :question="$question" compact />', ['question' => $question]);

    expect($html)
        ->toContain('data-question-media="compact"')
        ->toMatch('#<img\s+src="/storage/quiz-media/images/carrot\.png"\s+alt="Illustration: Which vegetable is shown in the picture\?"#')
        ->toMatch('#<img[^>]*class="h-16 w-16\b#')
        ->toMatch('#data-question-media-video[^>]*>\s*<svg[^>]*data-icon="video"[^>]*>.*?</svg>\s*Video\s*</span>#s')
        ->not->toContain('<iframe')
        ->not->toContain('<video');
});

it('shows only the video chip for a compact question with a video', function (array $video) {
    $question = QuizQuestion::factory()->make($video);

    $html = (string) $this->blade('<x-question.media :question="$question" :compact="true" />', ['question' => $question]);

    expect($html)
        ->toContain('data-question-media-video')
        ->not->toContain('<img')
        ->not->toContain('<video')
        ->not->toContain('<iframe')
        ->not->toContain('<a ');
})->with([
    'uploaded file' => [['video_path' => 'quiz-media/videos/handwashing.mp4']],
    'external link' => [['video_url' => 'https://www.example.org/lessons/go-grow-glow']],
]);

it('shows only the thumbnail for a compact question with an image', function () {
    $question = QuizQuestion::factory()->make(['image_path' => 'quiz-media/images/carrot.png']);

    $this->blade('<x-question.media :question="$question" compact />', ['question' => $question])
        ->assertSee('src="/storage/quiz-media/images/carrot.png"', false)
        ->assertDontSee('data-question-media-video', false);
});

it('renders the media of a question bank item', function () {
    $item = QuestionBankItem::factory()->make(['image_path' => 'quiz-media/images/pinggang-pinoy.png']);

    $this->blade('<x-question.media :question="$item" />', ['item' => $item])
        ->assertSee('src="/storage/quiz-media/images/pinggang-pinoy.png"', false);
});

it('merges extra attributes into the wrapper', function () {
    $question = QuizQuestion::factory()->make(['image_path' => 'quiz-media/images/carrot.png']);

    $html = (string) $this->blade('<x-question.media :question="$question" class="mt-4" data-testid="question-media" />', ['question' => $question]);

    expect($html)->toMatch('#<div class="[^"]*\bflex-col\b[^"]*\bmt-4\b[^"]*" data-testid="question-media" data-question-media="full">#');
});

it('escapes the prompt in the alt text and the video link', function () {
    $question = QuizQuestion::factory()->make([
        'prompt' => '"><script>alert("prompt")</script>',
        'image_path' => 'quiz-media/images/carrot.png',
        'video_url' => 'https://www.example.org/watch?title="><script>alert(1)</script>',
    ]);

    $html = (string) $this->blade('<x-question.media :question="$question" />', ['question' => $question]);

    expect($html)
        ->toContain('alt="Illustration: &quot;&gt;&lt;script&gt;alert(&quot;prompt&quot;)&lt;/script&gt;"')
        ->toContain('href="https://www.example.org/watch?title=&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"')
        ->not->toContain('<script>');
});
