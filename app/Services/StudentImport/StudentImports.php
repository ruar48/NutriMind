<?php

namespace App\Services\StudentImport;

use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

/**
 * The learner CSV import workflow shared by the administrator and teacher portals: check an upload, preview a pending
 * import, and commit it once.
 */
class StudentImports
{
    /**
     * How long a commit may hold its lock, in seconds (generous: large imports hash many passwords).
     */
    private const LOCK_SECONDS = 900;

    public function __construct(
        private PendingImports $pending,
        private CsvParser $parser,
        private StudentImportPreviewer $previewer,
        private StudentImporter $importer,
    ) {}

    /**
     * Get what keeps an uploaded file from being previewed, most important first (empty when it can be).
     *
     * @param  bool  $gradeFromClassroom  Whether a classroom supplies the grade level.
     * @return list<string>
     */
    public function problemsWith(UploadedFile $file, bool $gradeFromClassroom): array
    {
        $contents = $file->get();

        if (! is_string($contents)) {
            return ['The file could not be read. Upload it again.'];
        }

        try {
            return ImportColumns::problems($this->parser->parse($contents), $gradeFromClassroom);
        } catch (InvalidImportFile $exception) {
            return [$exception->getMessage()];
        }
    }

    /**
     * Find the user's pending import for the classroom by token; null when it is unknown, someone else's, or for
     * another classroom.
     */
    public function findForClassroom(string $id, User $user, Classroom $classroom): ?PendingImport
    {
        $pending = $this->pending->find($id, $user);

        return $pending !== null && $pending->classroomId === $classroom->id ? $pending : null;
    }

    /**
     * Get the classroom an administrator chose for the pending import (with subject and teacher), or null for none.
     *
     * @throws ImportUnavailable when the classroom was archived or deleted after the upload (the import is discarded).
     */
    public function chosenClassroom(PendingImport $pending): ?Classroom
    {
        if ($pending->classroomId === null) {
            return null;
        }

        $classroom = Classroom::query()->active()->with(['subject', 'teacher', 'schoolYear'])->find($pending->classroomId);

        if ($classroom === null) {
            $this->pending->discard($pending);

            throw ImportUnavailable::classroomUnavailable();
        }

        return $classroom;
    }

    /**
     * Re-read and validate the pending import's file against the current database.
     *
     * @throws ImportUnavailable when the file is gone or no longer readable (the import is discarded).
     */
    public function preview(PendingImport $pending, ?Classroom $classroom): ImportPreview
    {
        $contents = $this->pending->contents($pending);

        if ($contents === null) {
            $this->pending->discard($pending);

            throw ImportUnavailable::fileMissing();
        }

        try {
            $csv = $this->parser->parse($contents);
        } catch (InvalidImportFile) {
            $this->pending->discard($pending);

            throw ImportUnavailable::unreadable();
        }

        if (ImportColumns::problems($csv, $classroom !== null) !== []) {
            $this->pending->discard($pending);

            throw ImportUnavailable::unreadable();
        }

        return $this->previewer->preview($csv, $classroom);
    }

    /**
     * Re-validate and import the pending import's valid rows once, then delete the file, forget the token and keep the
     * generated sign-in details for one download.
     *
     * @throws ImportNotPending when another request is committing the same import, or already did (the file is gone).
     * @throws ImportUnavailable when the file is no longer readable.
     * @throws ImportNotCommitted when nothing can be imported or the data changed while importing.
     */
    public function commit(PendingImport $pending, ?Classroom $classroom, User $user): ImportResult
    {
        $lock = Cache::lock('student-import:'.$pending->id, self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw new ImportNotPending;
        }

        try {
            if (! $this->pending->hasFile($pending)) {
                $this->pending->discard($pending);

                throw new ImportNotPending;
            }

            $preview = $this->preview($pending, $classroom);

            if (! $preview->hasImportableRows()) {
                throw ImportNotCommitted::nothingToImport();
            }

            try {
                $result = $this->importer->import($preview, $classroom);
            } catch (UniqueConstraintViolationException) {
                throw ImportNotCommitted::conflict();
            }

            $this->pending->discard($pending);
            $this->pending->putCredentials($user, $result->credentials);

            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * Get the classrooms an administrator can import into (not archived), grouped for a select:
     * "Grade 5 · SY 2026–2027" => [id => "Health 5 – Sampaguita · Maricel D. Santos"].
     *
     * @return array<string, array<int, string>>
     */
    public function classroomOptions(): array
    {
        $options = [];

        $classrooms = Classroom::query()
            ->active()
            ->with(['teacher:id,name', 'schoolYear:id,name,starts_on'])
            ->orderByDesc(SchoolYear::query()->select('starts_on')->whereColumn('school_years.id', 'classrooms.school_year_id'))
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        foreach ($classrooms as $classroom) {
            $group = $classroom->grade_level->label().' · SY '.$classroom->schoolYear->name;
            $options[$group][$classroom->id] = $classroom->name.' · '.$classroom->teacher->name;
        }

        return $options;
    }
}
