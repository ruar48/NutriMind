<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\Support\BulkInsert;
use Database\Seeders\Support\Catalog\PeopleCatalog;
use Database\Seeders\Support\DemoClock;
use Database\Seeders\Support\DemoPassword;
use Illuminate\Database\Seeder;

/**
 * Seeds the administrator, the teachers and the subject assignments.
 *
 * One active teacher has no subject yet, to show subject-based access, and one retired teacher is deactivated.
 */
class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clock = DemoClock::start();
        $admin = PeopleCatalog::ADMIN;

        $rows = [$this->account(
            $admin['name'], $admin['email'], $admin['phone'], UserRole::Admin,
            $admin['created_on'].' 08:10:00', DemoClock::format($clock->now - DemoClock::HOUR), null,
        )];

        foreach (PeopleCatalog::teachers() as $teacher) {
            $rows[] = $this->account(
                $teacher['name'], $teacher['email'], $teacher['phone'], UserRole::Teacher,
                $teacher['created_on'].' 09:00:00',
                is_string($teacher['last_login']) ? $teacher['last_login'] : DemoClock::format($clock->daysAgo($teacher['last_login'])),
                $teacher['deactivated_at'],
            );
        }

        BulkInsert::models(User::class, $rows);

        $this->assignSubjects();
    }

    /**
     * Build the row of a staff account; a deactivated account was last updated when it was deactivated.
     *
     * @return array<string, mixed>
     */
    private function account(
        string $name,
        string $email,
        string $phone,
        UserRole $role,
        string $createdAt,
        string $lastLoginAt,
        ?string $deactivatedAt,
    ): array {
        return [
            'name' => $name,
            'username' => null,
            'email' => $email,
            'role' => $role->value,
            'email_verified_at' => DemoClock::format(DemoClock::at($createdAt) + 2 * DemoClock::HOUR),
            'password' => DemoPassword::hash(),
            'phone' => $phone,
            'is_active' => $deactivatedAt === null,
            'last_login_at' => $lastLoginAt,
            'remember_token' => null,
            'created_at' => $createdAt,
            'updated_at' => $deactivatedAt ?? $createdAt,
        ];
    }

    /**
     * Assign each teacher's subjects, recorded as assigned by the administrator.
     */
    private function assignSubjects(): void
    {
        $userIds = User::query()->toBase()->pluck('id', 'email');
        $subjectIds = Subject::query()->pluck('id', 'code');
        $assignedAt = DemoClock::at(PeopleCatalog::SUBJECT_ASSIGNMENT_DAY.' 10:00:00');
        $rows = [];

        foreach (PeopleCatalog::teachers() as $teacher) {
            foreach ($teacher['subjects'] as $code) {
                $moment = DemoClock::format($assignedAt + count($rows) * 180);

                $rows[] = [
                    'subject_id' => $subjectIds[$code],
                    'teacher_id' => $userIds[$teacher['email']],
                    'assigned_by' => $userIds[PeopleCatalog::ADMIN['email']],
                    'created_at' => $moment,
                    'updated_at' => $moment,
                ];
            }
        }

        BulkInsert::pivot((new User)->assignedSubjects(), $rows);
    }
}
