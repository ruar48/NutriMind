<?php

namespace App\Services\StudentImport;

use Carbon\CarbonImmutable;

/**
 * An uploaded learner file waiting for its preview to be committed (remembered in the uploader's session).
 */
final readonly class PendingImport
{
    /**
     * @param  string  $id  The import token (a UUID) used in the preview and commit URLs.
     * @param  int  $userId  The account that uploaded the file.
     * @param  int|null  $classroomId  The classroom the learners are imported into (none for an administrator import without one).
     * @param  string  $originalName  The uploaded file's name, for display.
     * @param  string  $path  The stored file, relative to the private disk.
     */
    public function __construct(
        public string $id,
        public int $userId,
        public ?int $classroomId,
        public string $originalName,
        public string $path,
        public CarbonImmutable $uploadedAt,
    ) {}

    /**
     * Get the session representation.
     *
     * @return array{user_id: int, classroom_id: int|null, original_name: string, path: string, uploaded_at: int}
     */
    public function toSession(): array
    {
        return [
            'user_id' => $this->userId,
            'classroom_id' => $this->classroomId,
            'original_name' => $this->originalName,
            'path' => $this->path,
            'uploaded_at' => $this->uploadedAt->getTimestamp(),
        ];
    }

    /**
     * Rebuild a pending import from its session representation; null when the stored value is malformed.
     */
    public static function fromSession(string $id, mixed $stored): ?self
    {
        if (! is_array($stored)
            || ! is_int($stored['user_id'] ?? null)
            || ! is_string($stored['path'] ?? null)
            || ! is_string($stored['original_name'] ?? null)
            || ! is_int($stored['uploaded_at'] ?? null)) {
            return null;
        }

        $classroomId = $stored['classroom_id'] ?? null;

        return new self(
            id: $id,
            userId: $stored['user_id'],
            classroomId: is_int($classroomId) ? $classroomId : null,
            originalName: $stored['original_name'],
            path: $stored['path'],
            uploadedAt: CarbonImmutable::createFromTimestamp($stored['uploaded_at'], config('app.timezone')),
        );
    }
}
