<?php

namespace App\Services\StudentImport;

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Keeps uploaded learner files between upload, preview and commit, and the one-time sign-in details download.
 *
 * Files live on the private "local" disk under imports/{uuid}.csv; the session remembers each upload under
 * imports.{uuid} (uploader, classroom, original file name, path). Only the uploader can use a token. After a commit,
 * the generated sign-in details stay in the session, encrypted, until they are downloaded once.
 */
class PendingImports
{
    /**
     * The private disk that holds uploaded files.
     */
    public const DISK = 'local';

    /**
     * The folder of uploaded files on the disk.
     */
    public const DIRECTORY = 'imports';

    /**
     * The session key that holds pending imports, keyed by token.
     */
    public const SESSION_KEY = 'imports';

    /**
     * The session key that holds the encrypted sign-in details of the last committed import.
     */
    public const CREDENTIALS_SESSION_KEY = 'import_credentials';

    /**
     * How long an uploaded file is kept when its import is never committed.
     */
    public const STALE_AFTER_HOURS = 24;

    /**
     * The message shown when there are no sign-in details (left) to download.
     */
    public const NO_CREDENTIALS_MESSAGE = 'There are no sign-in details to download. They can be downloaded once, right after an import.';

    /**
     * The longest original file name kept for display.
     */
    private const ORIGINAL_NAME_MAX_LENGTH = 150;

    public function __construct(
        private Session $session,
        private FilesystemFactory $filesystems,
        private Encrypter $encrypter,
    ) {}

    /**
     * Store an uploaded file and remember it for the user; replaces the user's earlier upload for the same classroom.
     */
    public function store(UploadedFile $file, User $user, ?Classroom $classroom): PendingImport
    {
        $this->pruneStale();

        foreach ($this->all() as $pending) {
            if ($pending->userId === $user->id && $pending->classroomId === $classroom?->id) {
                $this->discard($pending);
            }
        }

        $id = (string) Str::uuid();
        $path = $this->disk()->putFileAs(self::DIRECTORY, $file, $id.'.csv');

        if ($path === false) {
            throw new RuntimeException('The uploaded learner file could not be stored.');
        }

        $pending = new PendingImport(
            id: $id,
            userId: $user->id,
            classroomId: $classroom?->id,
            originalName: $this->displayName($file),
            path: $path,
            uploadedAt: CarbonImmutable::now(),
        );

        $this->session->put(self::SESSION_KEY.'.'.$id, $pending->toSession());

        return $pending;
    }

    /**
     * Find the user's pending import by token; null for unknown, malformed or someone else's tokens.
     */
    public function find(string $id, User $user): ?PendingImport
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $pending = PendingImport::fromSession($id, $this->session->get(self::SESSION_KEY.'.'.$id));

        return $pending !== null && $pending->userId === $user->id ? $pending : null;
    }

    /**
     * Read the stored file; null when it no longer exists.
     */
    public function contents(PendingImport $pending): ?string
    {
        $disk = $this->disk();

        return $disk->exists($pending->path) ? $disk->get($pending->path) : null;
    }

    /**
     * Determine whether the stored file still exists.
     */
    public function hasFile(PendingImport $pending): bool
    {
        return $this->disk()->exists($pending->path);
    }

    /**
     * Delete the stored file and forget the token.
     */
    public function discard(PendingImport $pending): void
    {
        $this->disk()->delete($pending->path);
        $this->session->forget(self::SESSION_KEY.'.'.$pending->id);
    }

    /**
     * Keep the sign-in details of a committed import for one download by the user (replacing earlier ones).
     *
     * @param  list<array{name: string, username: string, password: string, role: UserRole}>  $credentials
     */
    public function putCredentials(User $user, array $credentials): void
    {
        if ($credentials === []) {
            $this->session->forget(self::CREDENTIALS_SESSION_KEY);

            return;
        }

        $this->session->put(self::CREDENTIALS_SESSION_KEY, $this->encrypter->encrypt([
            'user_id' => $user->id,
            'accounts' => array_map(fn (array $account): array => [
                'name' => $account['name'],
                'username' => $account['username'],
                'password' => $account['password'],
                'role' => $account['role']->value,
            ], $credentials),
        ]));
    }

    /**
     * Take the stored sign-in details out of the session; null when there are none for the user.
     *
     * @return list<array{name: string, username: string, password: string, role: UserRole}>|null
     */
    public function pullCredentials(User $user): ?array
    {
        $stored = $this->session->pull(self::CREDENTIALS_SESSION_KEY);

        if (! is_string($stored)) {
            return null;
        }

        try {
            $payload = $this->encrypter->decrypt($stored);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload) || ($payload['user_id'] ?? null) !== $user->id || ! is_array($payload['accounts'] ?? null)) {
            return null;
        }

        return array_map(fn (array $account): array => [
            'name' => (string) $account['name'],
            'username' => (string) $account['username'],
            'password' => (string) $account['password'],
            'role' => UserRole::from($account['role']),
        ], array_values($payload['accounts']));
    }

    /**
     * Get every pending import remembered in the session.
     *
     * @return list<PendingImport>
     */
    private function all(): array
    {
        $stored = $this->session->get(self::SESSION_KEY, []);
        $pending = [];

        foreach (is_array($stored) ? $stored : [] as $id => $value) {
            $import = is_string($id) ? PendingImport::fromSession($id, $value) : null;

            if ($import !== null) {
                $pending[] = $import;
            }
        }

        return $pending;
    }

    /**
     * Delete uploaded files (anyone's) and forget this session's tokens older than STALE_AFTER_HOURS.
     */
    private function pruneStale(): void
    {
        $disk = $this->disk();
        $cutoff = CarbonImmutable::now()->subHours(self::STALE_AFTER_HOURS);

        foreach ($disk->files(self::DIRECTORY) as $path) {
            if ($disk->lastModified($path) < $cutoff->getTimestamp()) {
                $disk->delete($path);
            }
        }

        foreach ($this->all() as $pending) {
            if ($pending->uploadedAt->lessThan($cutoff)) {
                $this->discard($pending);
            }
        }
    }

    /**
     * Get the uploaded file's name without any folder, shortened for display.
     */
    private function displayName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', mb_scrub($file->getClientOriginalName(), 'UTF-8')));

        return Str::limit($name !== '' ? $name : 'learners.csv', self::ORIGINAL_NAME_MAX_LENGTH);
    }

    /**
     * Get the private disk.
     */
    private function disk(): Filesystem
    {
        return $this->filesystems->disk(self::DISK);
    }
}
