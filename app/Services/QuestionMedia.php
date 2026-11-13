<?php

namespace App\Services;

use App\Models\QuestionBankItem;
use App\Models\QuizQuestion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Throwable;

/**
 * Stores, copies and deletes the images and videos of quiz questions and question bank items.
 *
 * Files live on the public disk under quiz-media/images and quiz-media/videos with generated names. Every record
 * owns its files (copies get new paths), and a file is deleted only after the change is committed and once no quiz
 * question or bank item refers to it any more.
 */
class QuestionMedia
{
    /**
     * The disk that holds question media.
     */
    public const DISK = 'public';

    /**
     * The directory of uploaded question images.
     */
    public const IMAGE_DIRECTORY = 'quiz-media/images';

    /**
     * The directory of uploaded question videos.
     */
    public const VIDEO_DIRECTORY = 'quiz-media/videos';

    /**
     * The accepted image file types.
     *
     * @var list<string>
     */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * The accepted video file types.
     *
     * @var list<string>
     */
    public const VIDEO_EXTENSIONS = ['mp4', 'webm'];

    /**
     * Get the largest accepted image, in kilobytes (the configured limit, capped by PHP's upload limit).
     */
    public function imageMaxKilobytes(): int
    {
        return $this->cappedByUploadLimit((int) config('nutrimind.uploads.image_max_kb'));
    }

    /**
     * Get the largest accepted video, in kilobytes (the configured limit, capped by PHP's upload limit).
     */
    public function videoMaxKilobytes(): int
    {
        return $this->cappedByUploadLimit((int) config('nutrimind.uploads.video_max_kb'));
    }

    /**
     * Get the validation rule for a question image.
     */
    public function imageRule(): File
    {
        return File::image()
            ->types(self::IMAGE_EXTENSIONS)
            ->extensions(self::IMAGE_EXTENSIONS)
            ->max($this->imageMaxKilobytes());
    }

    /**
     * Get the validation rule for a question video file.
     */
    public function videoRule(): File
    {
        return File::types(self::VIDEO_EXTENSIONS)
            ->extensions(self::VIDEO_EXTENSIONS)
            ->max($this->videoMaxKilobytes());
    }

    /**
     * Get the human-readable image limit, e.g. "2 MB".
     */
    public function imageLimitLabel(): string
    {
        return self::formatKilobytes($this->imageMaxKilobytes());
    }

    /**
     * Get the human-readable video limit, e.g. "5 MB".
     */
    public function videoLimitLabel(): string
    {
        return self::formatKilobytes($this->videoMaxKilobytes());
    }

    /**
     * Format a size in kilobytes for people: "500 KB", "2 MB", "1.5 MB".
     */
    public static function formatKilobytes(int $kilobytes): string
    {
        if ($kilobytes < 1024) {
            return number_format($kilobytes).' KB';
        }

        $megabytes = round($kilobytes / 1024, 1);

        return number_format($megabytes, floor($megabytes) === $megabytes ? 0 : 1).' MB';
    }

    /**
     * Save the question with its uploaded media.
     *
     * A new upload replaces the current file and a remove flag clears it. New files are discarded when saving fails
     * or the surrounding transaction rolls back; replaced and removed files are deleted after the change commits.
     *
     * @param  array{image?: ?UploadedFile, remove_image?: bool, video?: ?UploadedFile, remove_video?: bool}  $input
     * @return bool Whether the question is new or anything about it changed.
     */
    public function save(QuizQuestion|QuestionBankItem $question, array $input): bool
    {
        $stored = [];
        $replaced = [];

        try {
            foreach (['image' => self::IMAGE_DIRECTORY, 'video' => self::VIDEO_DIRECTORY] as $kind => $directory) {
                $column = $kind.'_path';
                $upload = $input[$kind] ?? null;

                if ($upload instanceof UploadedFile) {
                    $replaced[] = $question->getOriginal($column);
                    $question->setAttribute($column, $stored[] = $this->store($upload, $directory));
                } elseif (($input['remove_'.$kind] ?? false) === true) {
                    $replaced[] = $question->getOriginal($column);
                    $question->setAttribute($column, null);
                }
            }

            $changed = ! $question->exists || $question->isDirty();
            $question->save();
        } catch (Throwable $exception) {
            $this->disk()->delete($stored);

            throw $exception;
        }

        $this->discardOnRollBack($stored);
        $this->delete(...$replaced);

        return $changed;
    }

    /**
     * Copy the image and video files of a question to new paths for another record.
     *
     * A missing source file is skipped (null). Copies are discarded when the surrounding transaction rolls back.
     *
     * @return array{image_path: ?string, video_path: ?string}
     */
    public function copyFiles(QuizQuestion|QuestionBankItem $question): array
    {
        return [
            'image_path' => $this->copyInto($question->image_path, self::IMAGE_DIRECTORY),
            'video_path' => $this->copyInto($question->video_path, self::VIDEO_DIRECTORY),
        ];
    }

    /**
     * Get the media file paths of the given questions.
     *
     * @param  iterable<QuizQuestion|QuestionBankItem>  $questions
     * @return list<string>
     */
    public function pathsOf(iterable $questions): array
    {
        $paths = [];

        foreach ($questions as $question) {
            array_push($paths, ...array_filter([$question->image_path, $question->video_path], filled(...)));
        }

        return array_values(array_unique($paths));
    }

    /**
     * Delete media files once no quiz question or question bank item refers to them.
     *
     * Inside a transaction the files are deleted after it commits, so a rolled-back change keeps its files.
     */
    public function delete(?string ...$paths): void
    {
        $paths = array_values(array_unique(array_filter($paths, filled(...))));

        if ($paths === []) {
            return;
        }

        DB::afterCommit(function () use ($paths): void {
            $this->disk()->delete(array_values(array_diff($paths, $this->referencedPaths($paths))));
        });
    }

    /**
     * Store an uploaded file under a generated name.
     */
    private function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, self::DISK);

        if (! is_string($path)) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        return $path;
    }

    /**
     * Copy a stored file into the directory under a generated name, keeping its extension.
     */
    private function copyInto(?string $path, string $directory): ?string
    {
        if (blank($path) || ! $this->disk()->exists($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $copy = $directory.'/'.Str::random(40).($extension === '' ? '' : '.'.$extension);

        if (! $this->disk()->copy($path, $copy)) {
            throw new RuntimeException('The media file could not be copied.');
        }

        $this->discardOnRollBack([$copy]);

        return $copy;
    }

    /**
     * Delete the given new files if the surrounding transaction rolls back.
     *
     * @param  list<string>  $paths
     */
    private function discardOnRollBack(array $paths): void
    {
        if ($paths !== []) {
            DB::afterRollBack(fn () => $this->disk()->delete($paths));
        }
    }

    /**
     * Get the given paths that a quiz question or question bank item still refers to.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function referencedPaths(array $paths): array
    {
        $referenced = [];

        foreach ([QuizQuestion::query(), QuestionBankItem::query()] as $model) {
            $rows = $model->toBase()
                ->where(fn (Builder $query) => $query->whereIn('image_path', $paths)->orWhereIn('video_path', $paths))
                ->get(['image_path', 'video_path']);

            foreach ($rows as $row) {
                array_push($referenced, ...array_filter([$row->image_path, $row->video_path], filled(...)));
            }
        }

        return array_values(array_unique($referenced));
    }

    /**
     * Cap a size limit by PHP's upload and post limits.
     */
    private function cappedByUploadLimit(int $kilobytes): int
    {
        $phpLimit = (int) floor(SymfonyUploadedFile::getMaxFilesize() / 1024);

        return $phpLimit > 0 ? min($kilobytes, $phpLimit) : $kilobytes;
    }

    /**
     * Get the media disk.
     */
    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
