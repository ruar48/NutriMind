<?php

namespace App\Services\StudentImport;

use App\Models\Classroom;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Validates every row of a learner CSV file against the file and the database and decides what importing it does.
 *
 * A row creates a learner, enrolls the existing learner with the same LRN in the classroom, is skipped (already
 * enrolled, or an existing learner while no classroom is chosen), or has errors. Guardian emails link an existing
 * parent account, or create one (once per email address) and link it. The database is read with a fixed number of
 * queries, however many rows the file has.
 */
class StudentImportPreviewer
{
    public function __construct(private RowValidator $validator) {}

    /**
     * Build the preview of the parsed file for the classroom being imported into (none for an administrator import
     * without a classroom).
     */
    public function preview(ParsedCsv $csv, ?Classroom $classroom): ImportPreview
    {
        $usesNameParts = ImportColumns::usesNameParts($csv);

        $validated = array_map(
            fn (array $row): ValidatedRow => $this->validator->validate($row['number'], $row['values'], $classroom, $usesNameParts),
            $csv->rows,
        );

        $learnersByLrn = $this->learnersByLrn($validated);
        $enrolledIds = $this->enrolledLearnerIds($classroom, $learnersByLrn);
        $usernameOwners = $this->usernameOwners($validated);
        $accountsByEmail = $this->accountsByEmail($validated);
        $existingLinks = $this->existingLinks($accountsByEmail, $learnersByLrn);

        $firstRowByLrn = [];
        $firstRowByUsername = [];
        $newParentRows = [];
        $rows = [];

        foreach ($validated as $row) {
            $errors = $row->errors;
            $notes = [];
            $usernameKey = $row->username !== null ? Str::lower($row->username) : null;

            if ($row->lrn !== null) {
                if (isset($firstRowByLrn[$row->lrn])) {
                    $errors[] = 'LRN already appears in row '.$firstRowByLrn[$row->lrn].'.';
                } else {
                    $firstRowByLrn[$row->lrn] = $row->number;
                }
            }

            if ($usernameKey !== null) {
                if (isset($firstRowByUsername[$usernameKey])) {
                    $errors[] = 'Username already appears in row '.$firstRowByUsername[$usernameKey].'.';
                } else {
                    $firstRowByUsername[$usernameKey] = $row->number;
                }
            }

            $existing = $row->lrn !== null ? ($learnersByLrn[$row->lrn] ?? null) : null;
            $usernameOwnerId = $usernameKey !== null ? ($usernameOwners[$usernameKey] ?? null) : null;

            if ($existing === null) {
                $status = ImportRowStatus::Create;

                if ($usernameOwnerId !== null) {
                    $errors[] = 'Username is already taken.';
                }
            } else {
                $notes[] = $this->matchNote($existing);

                if ($usernameOwnerId !== null && $usernameOwnerId !== $existing->id) {
                    $errors[] = 'This username belongs to another account, not to the learner with this LRN.';
                }

                if ($classroom === null) {
                    $status = ImportRowStatus::Skip;
                    $notes[] = 'A learner with this LRN already exists. Choose a classroom to enroll existing learners.';
                } elseif (isset($enrolledIds[$existing->id])) {
                    $status = ImportRowStatus::Skip;
                    $notes[] = 'Already enrolled in this classroom.';
                } else {
                    $status = ImportRowStatus::Enroll;
                    $errors = [...$errors, ...$this->enrollmentErrors($existing, $classroom)];
                }
            }

            $guardian = null;

            if ($status->isImportable() && $row->guardianEmail !== null) {
                $account = $accountsByEmail[$row->guardianEmail] ?? null;

                if ($account !== null && ! $account->isParent()) {
                    $errors[] = 'This email belongs to a non-parent account.';
                } elseif ($account !== null) {
                    $alreadyLinked = $existing !== null && isset($existingLinks[$account->id.':'.$existing->id]);
                    $guardian = new GuardianPlan(
                        action: $alreadyLinked ? GuardianAction::AlreadyLinked : GuardianAction::LinkExisting,
                        email: $row->guardianEmail,
                        relationship: $row->guardianRelationship,
                        parent: $account,
                    );
                    $notes[] = $alreadyLinked
                        ? 'Already linked to the parent account of '.$account->name.'.'
                        : 'Links the existing parent account of '.$account->name.'.';

                    if (! $account->is_active) {
                        $notes[] = 'That parent account is deactivated.';
                    }
                } elseif (isset($newParentRows[$row->guardianEmail])) {
                    $guardian = new GuardianPlan(
                        action: GuardianAction::LinkCreated,
                        email: $row->guardianEmail,
                        relationship: $row->guardianRelationship,
                        sourceRow: $newParentRows[$row->guardianEmail],
                    );
                    $notes[] = 'Links the parent account created for row '.$newParentRows[$row->guardianEmail].'.';
                } elseif ($row->guardianName === null) {
                    $errors[] = 'Guardian name is required to create a parent account.';
                } else {
                    $guardian = new GuardianPlan(
                        action: GuardianAction::CreateAccount,
                        email: $row->guardianEmail,
                        relationship: $row->guardianRelationship,
                        name: $row->guardianName,
                    );
                    $notes[] = 'Creates a parent account for '.$row->guardianEmail.'.';
                }
            } elseif ($status->isImportable() && $row->guardianName !== null) {
                $notes[] = 'Add a guardian email to link a parent account.';
            } elseif ($status === ImportRowStatus::Skip && $row->guardianEmail !== null) {
                $notes[] = 'Guardian details are not used because this row is skipped.';
            }

            if ($errors !== []) {
                $rows[] = new PreviewRow($row, ImportRowStatus::Error, errors: $errors, existingStudent: $existing);

                continue;
            }

            if ($guardian?->action === GuardianAction::CreateAccount) {
                $newParentRows[$guardian->email] = $row->number;
            }

            $rows[] = new PreviewRow($row, $status, notes: $notes, existingStudent: $existing, guardian: $guardian);
        }

        return new ImportPreview($rows, $usesNameParts);
    }

    /**
     * Describe the existing learner a row matches.
     */
    private function matchNote(User $learner): string
    {
        return $learner->username !== null
            ? 'Matches the existing learner '.$learner->name.' ('.$learner->username.').'
            : 'Matches the existing learner '.$learner->name.'.';
    }

    /**
     * Get what keeps an existing learner from being enrolled in the classroom.
     *
     * @return list<string>
     */
    private function enrollmentErrors(User $learner, Classroom $classroom): array
    {
        $errors = [];

        if (! $learner->is_active) {
            $errors[] = 'The learner with this LRN has a deactivated account.';
        }

        $grade = $learner->studentProfile?->grade_level;

        if ($grade !== null && $grade !== $classroom->grade_level) {
            $errors[] = 'The learner with this LRN is in '.$grade->label().', not '.$classroom->grade_level->label().' like this classroom.';
        }

        return $errors;
    }

    /**
     * Find the learners whose LRN appears in the file, keyed by LRN (studentProfile loaded).
     *
     * @param  list<ValidatedRow>  $rows
     * @return array<string, User>
     */
    private function learnersByLrn(array $rows): array
    {
        $lrns = array_values(array_unique(array_filter(array_map(fn (ValidatedRow $row): ?string => $row->lrn, $rows))));

        if ($lrns === []) {
            return [];
        }

        $learners = [];

        foreach (StudentProfile::query()->whereIn('lrn', $lrns)->with('user')->get() as $profile) {
            if ($profile->user === null) {
                continue;
            }

            $profile->user->setRelation('studentProfile', $profile);
            $learners[$profile->lrn] = $profile->user;
        }

        return $learners;
    }

    /**
     * Get the IDs of the matched learners already enrolled in the classroom, as array keys.
     *
     * @param  array<string, User>  $learnersByLrn
     * @return array<int, true>
     */
    private function enrolledLearnerIds(?Classroom $classroom, array $learnersByLrn): array
    {
        if ($classroom === null || $learnersByLrn === []) {
            return [];
        }

        return $classroom->students()
            ->whereIn('users.id', array_map(fn (User $learner): int => $learner->id, array_values($learnersByLrn)))
            ->pluck('users.id')
            ->mapWithKeys(fn (mixed $id): array => [(int) $id => true])
            ->all();
    }

    /**
     * Find who owns the usernames in the file (compared case-insensitively), as lowercase username → user ID.
     *
     * @param  list<ValidatedRow>  $rows
     * @return array<string, int>
     */
    private function usernameOwners(array $rows): array
    {
        $usernames = array_values(array_unique(array_filter(array_map(
            fn (ValidatedRow $row): ?string => $row->username !== null ? Str::lower($row->username) : null,
            $rows,
        ))));

        if ($usernames === []) {
            return [];
        }

        return User::query()
            ->whereIn(DB::raw('lower(username)'), $usernames)
            ->get(['id', 'username'])
            ->mapWithKeys(fn (User $user): array => [Str::lower($user->username) => $user->id])
            ->all();
    }

    /**
     * Find the accounts that use the guardian emails in the file (compared case-insensitively), keyed by lowercase email.
     *
     * @param  list<ValidatedRow>  $rows
     * @return array<string, User>
     */
    private function accountsByEmail(array $rows): array
    {
        $emails = array_values(array_unique(array_filter(array_map(fn (ValidatedRow $row): ?string => $row->guardianEmail, $rows))));

        if ($emails === []) {
            return [];
        }

        return User::query()
            ->whereIn(DB::raw('lower(email)'), $emails)
            ->get(['id', 'name', 'email', 'role', 'is_active'])
            ->mapWithKeys(fn (User $user): array => [Str::lower($user->email) => $user])
            ->all();
    }

    /**
     * Get the existing links between the matched parents and the matched learners, as "parentId:learnerId" keys.
     *
     * @param  array<string, User>  $accountsByEmail
     * @param  array<string, User>  $learnersByLrn
     * @return array<string, true>
     */
    private function existingLinks(array $accountsByEmail, array $learnersByLrn): array
    {
        $parentIds = array_values(array_map(
            fn (User $parent): int => $parent->id,
            array_filter($accountsByEmail, fn (User $account): bool => $account->isParent()),
        ));
        $learnerIds = array_values(array_map(fn (User $learner): int => $learner->id, $learnersByLrn));

        if ($parentIds === [] || $learnerIds === []) {
            return [];
        }

        return DB::table('guardian_student')
            ->whereIn('guardian_id', $parentIds)
            ->whereIn('student_id', $learnerIds)
            ->get(['guardian_id', 'student_id'])
            ->mapWithKeys(fn (object $link): array => [$link->guardian_id.':'.$link->student_id => true])
            ->all();
    }
}
