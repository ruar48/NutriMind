<?php

namespace App\Services\StudentImport;

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Services\StudentAccounts;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Commits a previewed learner import in one database transaction: creates learner accounts, enrolls existing
 * learners, and creates or links parent accounts. Any failure rolls everything back.
 */
class StudentImporter
{
    /**
     * The characters of generated parent passwords (no 0/O, 1/l/I look-alikes).
     */
    public const PARENT_PASSWORD_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    /**
     * The seconds of PHP execution time an import gets before its rows are counted.
     */
    private const BASE_SECONDS = 60;

    /**
     * The extra seconds of PHP execution time per imported row (password hashing dominates).
     */
    private const SECONDS_PER_ROW = 1;

    public function __construct(private StudentAccounts $accounts) {}

    /**
     * Import the Create and Enroll rows of the preview into the classroom (none for an administrator import without one).
     */
    public function import(ImportPreview $preview, ?Classroom $classroom): ImportResult
    {
        $this->extendTimeLimit($preview->importableCount());

        return DB::transaction(function () use ($preview, $classroom): ImportResult {
            $learners = [];
            $credentials = [];
            $enrolled = 0;

            foreach ($this->inCreationOrder($preview->rowsWith(ImportRowStatus::Create)) as $row) {
                $account = $this->accounts->create([
                    'name' => $row->data->name,
                    'grade_level' => $row->data->grade,
                    'lrn' => $row->data->lrn,
                    'sex' => $row->data->sex,
                    'birthdate' => $row->data->birthdate,
                    'username' => $row->data->username ?? $this->generatedUsername($row->data, $preview->usesNameParts),
                ], $classroom);

                $learners[$row->number()] = $account->user;
                $credentials[$row->number()][] = [
                    'name' => $account->user->name,
                    'username' => $account->user->username,
                    'password' => $account->plainPassword,
                    'role' => UserRole::Student,
                ];
            }

            foreach ($preview->rowsWith(ImportRowStatus::Enroll) as $row) {
                if ($classroom === null || $row->existingStudent === null) {
                    throw new LogicException('An Enroll row needs a classroom and the matched learner.');
                }

                $learners[$row->number()] = $row->existingStudent;

                if ($this->accounts->enroll($row->existingStudent, $classroom)) {
                    $enrolled++;
                }
            }

            $parentsByEmail = [];
            $linked = 0;

            foreach ($preview->rows as $row) {
                $plan = $row->guardian;

                if ($plan === null || ! $plan->action->createsLink() || ! isset($learners[$row->number()])) {
                    continue;
                }

                $credentials[$row->number()] ??= [];

                $parent = match ($plan->action) {
                    GuardianAction::CreateAccount => $parentsByEmail[$plan->email] = $this->createParent($plan, $credentials[$row->number()]),
                    GuardianAction::LinkCreated => $parentsByEmail[$plan->email] ?? throw new LogicException('The parent account of row '.$plan->sourceRow.' was not created.'),
                    GuardianAction::LinkExisting => $plan->parent ?? throw new LogicException('A LinkExisting plan needs the parent account.'),
                };

                $parent->linkedStudents()->attach($learners[$row->number()]->id, ['relationship' => $plan->relationship]);
                $linked++;
            }

            ksort($credentials);

            return new ImportResult(
                created: $preview->count(ImportRowStatus::Create),
                enrolled: $enrolled,
                skipped: $preview->count(ImportRowStatus::Skip),
                failed: $preview->count(ImportRowStatus::Error),
                parentsCreated: count($parentsByEmail),
                guardiansLinked: $linked,
                credentials: array_merge(...array_values($credentials)),
            );
        });
    }

    /**
     * Generate a readable but strong parent password: three groups of four characters, e.g. "Kp7m-Xw3q-9tRb".
     */
    public function generateParentPassword(): string
    {
        $alphabet = self::PARENT_PASSWORD_ALPHABET;

        do {
            $characters = '';

            for ($index = 0; $index < 12; $index++) {
                $characters .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (preg_match('/\d/', $characters) !== 1 || preg_match('/[A-Za-z]/', $characters) !== 1);

        return implode('-', str_split($characters, 4));
    }

    /**
     * Order the rows to create: rows with a username from the file first, so generated usernames never take one of them.
     *
     * @param  list<PreviewRow>  $rows
     * @return list<PreviewRow>
     */
    private function inCreationOrder(array $rows): array
    {
        usort($rows, fn (PreviewRow $first, PreviewRow $second): int => [$first->data->username === null, $first->number()]
            <=> [$second->data->username === null, $second->number()]);

        return $rows;
    }

    /**
     * Generate a username from the surname column when the file has one ("Santos Reyes, Ma. Teresa" → msantosreyes);
     * otherwise leave it to StudentAccounts, which reads the surname from the full name.
     */
    private function generatedUsername(ValidatedRow $row, bool $usesNameParts): ?string
    {
        if (! $usesNameParts || $row->firstName === null || $row->lastName === null) {
            return null;
        }

        return $this->accounts->generateUsername(
            str_replace(',', ' ', $row->lastName).', '.str_replace(',', ' ', $row->firstName),
        );
    }

    /**
     * Create the parent account of a CreateAccount plan and add its sign-in details after its learner's.
     *
     * @param  list<array{name: string, username: string, password: string, role: UserRole}>  $credentials
     */
    private function createParent(GuardianPlan $plan, array &$credentials): User
    {
        $password = $this->generateParentPassword();

        $parent = new User([
            'name' => $plan->name,
            'email' => $plan->email,
            'password' => $password,
        ]);
        $parent->role = UserRole::Parent;
        $parent->save();

        $credentials[] = [
            'name' => $parent->name,
            'username' => $parent->email,
            'password' => $password,
            'role' => UserRole::Parent,
        ];

        return $parent;
    }

    /**
     * Give large imports enough PHP execution time; every new account hashes a password.
     */
    private function extendTimeLimit(int $rows): void
    {
        $current = (int) ini_get('max_execution_time');
        $needed = self::BASE_SECONDS + $rows * self::SECONDS_PER_ROW;

        if ($current !== 0 && $current < $needed && function_exists('set_time_limit')) {
            set_time_limit($needed);
        }
    }
}
