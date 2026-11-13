<?php

namespace App\Models\Concerns;

use App\Enums\QuestionType;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Question content shared by quiz questions and question bank items.
 *
 * Multiple choice and true/false questions store their correct answer as a zero-based choice
 * index ("0".."5"). Identification questions store every accepted answer separated by "|".
 */
trait HasQuestionContent
{
    /**
     * Register the casts every question model needs.
     */
    public function initializeHasQuestionContent(): void
    {
        $this->mergeCasts([
            'type' => QuestionType::class,
            'choices' => 'array',
        ]);
    }

    /**
     * Get the letter shown next to the choice at the zero-based index ("A", "B", …).
     */
    public function choiceLetter(int $index): string
    {
        return chr(ord('A') + $index);
    }

    /**
     * Get the human-readable answers that count as correct.
     *
     * Identification questions return each accepted answer; choice questions return the text of the correct choice.
     *
     * @return list<string>
     */
    public function acceptedAnswers(): array
    {
        if ($this->type === QuestionType::Identification) {
            return array_values(array_filter(
                array_map(fn (string $answer): string => Str::squish($answer), explode('|', (string) $this->correct_answer)),
                fn (string $answer): bool => $answer !== '',
            ));
        }

        $choice = $this->choiceAt($this->correct_answer);

        return $choice === null ? [] : [$choice];
    }

    /**
     * Get the correct answer as display text (accepted alternatives are joined with " / ").
     */
    public function correctAnswerText(): string
    {
        return implode(' / ', $this->acceptedAnswers());
    }

    /**
     * Determine whether a learner's answer is correct.
     *
     * Choice answers are compared as index strings; typed answers ignore letter case and extra whitespace.
     */
    public function isCorrectAnswer(?string $answer): bool
    {
        if (blank($answer)) {
            return false;
        }

        if ($this->type === QuestionType::Identification) {
            $typedAnswer = $this->normalizeTypedAnswer($answer);

            foreach ($this->acceptedAnswers() as $acceptedAnswer) {
                if ($this->normalizeTypedAnswer($acceptedAnswer) === $typedAnswer) {
                    return true;
                }
            }

            return false;
        }

        return trim($answer) === trim((string) $this->correct_answer);
    }

    /**
     * Get a learner's stored answer as display text.
     */
    public function answerText(?string $answer): string
    {
        if (blank($answer)) {
            return 'No answer';
        }

        if ($this->type === QuestionType::Identification) {
            return Str::squish($answer);
        }

        return $this->choiceAt($answer) ?? trim($answer);
    }

    /**
     * Get the public URL of the uploaded image, if any.
     */
    public function imageUrl(): ?string
    {
        return filled($this->image_path) ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * Get the public URL of the uploaded video file, if any.
     */
    public function videoFileUrl(): ?string
    {
        return filled($this->video_path) ? Storage::disk('public')->url($this->video_path) : null;
    }

    /**
     * Get a privacy-friendly embed URL for a YouTube or Vimeo link, or null for any other link.
     */
    public function videoEmbedUrl(): ?string
    {
        $url = $this->parsedVideoUrl();

        if ($url === null) {
            return null;
        }

        $host = preg_replace('/^(www|m)\./', '', strtolower($url['host']));
        $segments = array_values(array_filter(
            explode('/', $url['path'] ?? ''),
            fn (string $segment): bool => $segment !== '',
        ));

        if ($host === 'vimeo.com') {
            return count($segments) === 1 && preg_match('/^\d{1,12}$/', $segments[0]) === 1
                ? 'https://player.vimeo.com/video/'.$segments[0]
                : null;
        }

        $videoId = match (true) {
            $host === 'youtu.be' => $segments[0] ?? null,
            $host === 'youtube.com' && ($segments[0] ?? null) === 'watch' => $this->youTubeWatchId($url['query'] ?? ''),
            $host === 'youtube.com' && in_array($segments[0] ?? null, ['shorts', 'embed'], true) => $segments[1] ?? null,
            default => null,
        };

        return is_string($videoId) && preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1
            ? 'https://www.youtube-nocookie.com/embed/'.$videoId
            : null;
    }

    /**
     * Get the external video link when it is a well-formed http(s) URL, for rendering as a plain link.
     */
    public function videoLinkUrl(): ?string
    {
        return $this->parsedVideoUrl() === null ? null : trim((string) $this->video_url);
    }

    /**
     * Determine whether the question has an image, a video file or a video link.
     */
    public function hasMedia(): bool
    {
        return filled($this->image_path) || filled($this->video_path) || filled($this->video_url);
    }

    /**
     * Get the text of the choice at a stored index string, or null when it does not point at a choice.
     */
    private function choiceAt(?string $index): ?string
    {
        $index = trim((string) $index);

        if (! ctype_digit($index)) {
            return null;
        }

        $choices = $this->choices ?? [];

        return array_key_exists((int) $index, $choices) ? (string) $choices[(int) $index] : null;
    }

    /**
     * Normalize a typed answer for comparison.
     */
    private function normalizeTypedAnswer(string $answer): string
    {
        return Str::lower(Str::squish($answer));
    }

    /**
     * Parse the video link when it is an absolute http(s) URL.
     *
     * @return array{scheme: string, host: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string}|null
     */
    private function parsedVideoUrl(): ?array
    {
        $url = trim((string) $this->video_url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return in_array(strtolower($parts['scheme']), ['http', 'https'], true) ? $parts : null;
    }

    /**
     * Get the "v" parameter of a YouTube watch URL query string.
     */
    private function youTubeWatchId(string $query): ?string
    {
        parse_str($query, $parameters);

        return is_string($parameters['v'] ?? null) ? $parameters['v'] : null;
    }
}
