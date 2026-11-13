<?php

namespace Database\Seeders;

use App\Enums\GradeLevel;
use App\Enums\GuardianRelationship;
use App\Enums\Sex;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\Support\BulkInsert;
use Database\Seeders\Support\Catalog\PeopleCatalog;
use Database\Seeders\Support\Catalog\SchoolCatalog;
use Database\Seeders\Support\DemoClock;
use Database\Seeders\Support\DemoPassword;
use Database\Seeders\Support\DemoRandom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the learners, their profiles and enrollments, and their families' parent and guardian accounts.
 *
 * Every learner belongs to one section and is enrolled in every classroom of that section. Brothers and sisters
 * share a family name and a guardian account. Most families have a guardian account, some have two, and some none.
 */
class LearnerSeeder extends Seeder
{
    /**
     * The number of families with one learner in Grade 5 and another in Grade 6 (besides the demo family).
     */
    private const SIBLING_FAMILIES = 27;

    /**
     * The number of one-learner families with a guardian account; the other one-learner families have none.
     */
    private const SINGLE_FAMILIES_WITH_ACCOUNTS = 56;

    /**
     * The number of families where both the mother and the father have an account (the last one is deactivated).
     */
    private const TWO_GUARDIAN_FAMILIES = 6;

    /**
     * The share of learners who transferred from another school (their LRN starts with that school's ID).
     */
    private const TRANSFEREE_SHARE = 0.1;

    /**
     * Philippine mobile number prefixes.
     */
    private const MOBILE_PREFIXES = ['0905', '0906', '0915', '0916', '0917', '0918', '0919', '0920', '0927', '0928', '0939', '0945', '0947', '0956', '0966', '0977', '0995', '0998'];

    /**
     * The seeded random stream of this seeder.
     */
    private DemoRandom $random;

    /**
     * The anchored clock of this run.
     */
    private DemoClock $clock;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->random = DemoRandom::stream('learners');
        $this->clock = DemoClock::start();

        $families = $this->nameFamilies($this->families());

        $learners = $this->learners($families);
        BulkInsert::models(User::class, array_column($learners, 'user'));

        $learnerIds = User::query()->ofRole(UserRole::Student)->toBase()->pluck('id', 'username');

        $this->seedProfiles($learners, $learnerIds->all());
        $this->seedEnrollments($learners, $learnerIds->all());
        $this->seedGuardians($families, $learners, $learnerIds->all());
    }

    /**
     * Plan the families: who the children are, in which sections, and which guardians have accounts.
     *
     * @return list<array{surname: string|null, children: list<array{first_name: string|null, sex: Sex|null, section: string, kind: string}>, guardians: list<array{relationship: GuardianRelationship, kind: string, active: bool}>}>
     */
    private function families(): array
    {
        $capacity = array_map(fn (array $section): int => $section['size'], PeopleCatalog::SECTIONS);

        $demo = PeopleCatalog::DEMO_FAMILY;
        $families = [[
            'surname' => $demo['surname'],
            'children' => array_map(fn (array $child): array => [
                'first_name' => $child['first_name'], 'sex' => Sex::from($child['sex']), 'section' => $child['section'], 'kind' => 'demo',
            ], $demo['children']),
            'guardians' => [['relationship' => GuardianRelationship::from($demo['guardian']['relationship']), 'kind' => 'demo', 'active' => true]],
        ]];

        foreach ($demo['children'] as $child) {
            $capacity[$child['section']]--;
        }

        foreach (PeopleCatalog::LATE_ENROLLEES as $enrollee) {
            $capacity[$enrollee['section']]--;
            $families[] = [
                'surname' => $enrollee['surname'],
                'children' => [['first_name' => $enrollee['first_name'], 'sex' => Sex::from($enrollee['sex']), 'section' => $enrollee['section'], 'kind' => 'late']],
                'guardians' => $enrollee['guardian'] === null ? [] : [['relationship' => GuardianRelationship::Mother, 'kind' => 'late', 'active' => true]],
            ];
        }

        $regularFamilies = [];

        for ($pair = 0; $pair < self::SIBLING_FAMILIES; $pair++) {
            $regularFamilies[] = ['surname' => null, 'children' => [
                $this->child($this->takeSeat($capacity, GradeLevel::Five)),
                $this->child($this->takeSeat($capacity, GradeLevel::Six)),
            ], 'guardians' => [$this->primaryGuardian()]];
        }

        $singles = [];

        foreach ($capacity as $section => $seats) {
            for ($seat = 0; $seat < $seats; $seat++) {
                $singles[] = ['surname' => null, 'children' => [$this->child($section)], 'guardians' => []];
            }
        }

        foreach ($this->random->shuffle($singles) as $index => $single) {
            if ($index < self::SINGLE_FAMILIES_WITH_ACCOUNTS) {
                $single['guardians'] = [$this->primaryGuardian()];
            }

            $regularFamilies[] = $single;
        }

        $twoGuardianFamilies = $this->random->shuffle(array_keys(array_filter(
            $regularFamilies,
            fn (array $family): bool => ($family['guardians'][0]['relationship'] ?? null) === GuardianRelationship::Mother,
        )));

        foreach (array_slice($twoGuardianFamilies, 0, self::TWO_GUARDIAN_FAMILIES) as $position => $familyIndex) {
            $regularFamilies[$familyIndex]['guardians'][] = [
                'relationship' => GuardianRelationship::Father,
                'kind' => 'regular',
                'active' => $position < self::TWO_GUARDIAN_FAMILIES - 1,
            ];
        }

        return [...$families, ...$regularFamilies];
    }

    /**
     * Take a free seat in a section of the grade, favoring the sections with the most free seats.
     *
     * @param  array<string, int>  $capacity
     */
    private function takeSeat(array &$capacity, GradeLevel $grade): string
    {
        $weights = [];

        foreach ($capacity as $section => $seats) {
            if ($seats > 0 && PeopleCatalog::SECTIONS[$section]['grade'] === $grade->value) {
                $weights[$section] = $seats;
            }
        }

        $section = (string) $this->random->weighted($weights);
        $capacity[$section]--;

        return $section;
    }

    /**
     * Plan a child in a section; the name is chosen when the family is named.
     *
     * @return array{first_name: null, sex: null, section: string, kind: string}
     */
    private function child(string $section): array
    {
        return ['first_name' => null, 'sex' => null, 'section' => $section, 'kind' => 'regular'];
    }

    /**
     * Plan the primary guardian of a regular family.
     *
     * @return array{relationship: GuardianRelationship, kind: string, active: bool}
     */
    private function primaryGuardian(): array
    {
        return [
            'relationship' => GuardianRelationship::from((string) $this->random->weighted(PeopleCatalog::RELATIONSHIP_WEIGHTS)),
            'kind' => 'regular',
            'active' => true,
        ];
    }

    /**
     * Give each regular family a family name and each child a first name, keeping every learner's name unique.
     *
     * @param  list<array{surname: string|null, children: list<array{first_name: string|null, sex: Sex|null, section: string, kind: string}>, guardians: list<array{relationship: GuardianRelationship, kind: string, active: bool}>}>  $families
     * @return list<array{surname: string, children: list<array{first_name: string, sex: Sex, section: string, kind: string}>, guardians: list<array{relationship: GuardianRelationship, kind: string, active: bool}>}>
     */
    private function nameFamilies(array $families): array
    {
        $reservedSurnames = [PeopleCatalog::DEMO_FAMILY['surname'], ...array_column(PeopleCatalog::LATE_ENROLLEES, 'surname')];
        $usedNames = [];

        foreach ($families as &$family) {
            $family['surname'] ??= $this->random->pick(array_values(array_diff(PeopleCatalog::SURNAMES, $reservedSurnames)));

            foreach ($family['children'] as &$child) {
                if ($child['first_name'] === null) {
                    $child['sex'] = $this->random->chance(0.5) ? Sex::Female : Sex::Male;

                    do {
                        $child['first_name'] = $this->random->pick($child['sex'] === Sex::Female ? PeopleCatalog::GIRL_NAMES : PeopleCatalog::BOY_NAMES);
                    } while (isset($usedNames[$child['first_name'].' '.$family['surname']]));
                }

                $usedNames[$child['first_name'].' '.$family['surname']] = true;
            }

            unset($child);
        }

        unset($family);

        return $families;
    }

    /**
     * Build every learner's account, profile and enrollment details.
     *
     * @param  list<array{surname: string, children: list<array{first_name: string, sex: Sex, section: string, kind: string}>, guardians: list<array{relationship: GuardianRelationship, kind: string, active: bool}>}>  $families
     * @return list<array{family: int, section: string, kind: string, user: array<string, mixed>, profile: array<string, mixed>, enrolled_at: int}>
     */
    private function learners(array $families): array
    {
        $usedUsernames = [];
        $usedLrns = [];
        $learners = [];

        foreach ($families as $familyIndex => $family) {
            foreach ($family['children'] as $child) {
                $grade = GradeLevel::from(PeopleCatalog::SECTIONS[$child['section']]['grade']);
                $createdAt = $this->accountCreatedAt($child);

                $username = $baseUsername = PeopleCatalog::usernameFor($child['first_name'], $family['surname']);

                for ($suffix = 2; isset($usedUsernames[$username]); $suffix++) {
                    $username = $baseUsername.$suffix;
                }

                $usedUsernames[$username] = true;

                $learners[] = [
                    'family' => $familyIndex,
                    'section' => $child['section'],
                    'kind' => $child['kind'],
                    'enrolled_at' => $child['kind'] === 'late'
                        ? $createdAt + DemoClock::HOUR
                        : DemoClock::at('2026-06-15 07:30:00') + $this->random->int(0, 3) * DemoClock::DAY + $this->random->int(0, 90) * 60,
                    'user' => [
                        'name' => $child['first_name'].' '.$family['surname'],
                        'username' => $username,
                        'email' => null,
                        'role' => UserRole::Student->value,
                        'email_verified_at' => null,
                        'password' => DemoPassword::hash(),
                        'phone' => null,
                        'is_active' => true,
                        'last_login_at' => null,
                        'remember_token' => null,
                        'created_at' => DemoClock::format($createdAt),
                        'updated_at' => DemoClock::format($createdAt),
                    ],
                    'profile' => [
                        'lrn' => $this->lrn($usedLrns, $grade, $child['kind'] === 'late' || $this->random->chance(self::TRANSFEREE_SHARE)),
                        'grade_level' => $grade->value,
                        'sex' => $child['sex']->value,
                        'birthdate' => $this->birthdate($grade),
                        'created_at' => DemoClock::format($createdAt),
                    ],
                ];
            }
        }

        return $learners;
    }

    /**
     * Get when a learner's account was created: last year for the section that used NutriMind last year, this week
     * for late enrollees, and during the enrollment week for everyone else.
     *
     * @param  array{section: string, kind: string}  $child
     */
    private function accountCreatedAt(array $child): int
    {
        return match (true) {
            $child['kind'] === 'late' => DemoClock::workTime($this->random, $this->clock->startOfToday() - 4 * DemoClock::DAY),
            $child['section'] === PeopleCatalog::PREVIOUS_YEAR_SECTION => DemoClock::workTime($this->random, DemoClock::at('2025-06-09') + $this->random->int(0, 4) * DemoClock::DAY),
            default => DemoClock::workTime($this->random, DemoClock::at('2026-06-08') + $this->random->int(0, 4) * DemoClock::DAY),
        };
    }

    /**
     * Issue a unique LRN: the six-digit ID of the school that first enrolled the learner, the two-digit year the
     * learner entered Kindergarten and a four-digit sequence number.
     *
     * @param  array<string, true>  $usedLrns
     */
    private function lrn(array &$usedLrns, GradeLevel $grade, bool $isTransferee): string
    {
        $schoolId = $isTransferee ? $this->random->pick(PeopleCatalog::TRANSFEREE_SCHOOL_IDS) : SchoolCatalog::SCHOOL_ID;
        $entryYear = $grade === GradeLevel::Five ? '21' : '20';

        do {
            $lrn = $schoolId.$entryYear.str_pad((string) $this->random->int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (isset($usedLrns[$lrn]));

        $usedLrns[$lrn] = true;

        return $lrn;
    }

    /**
     * Pick a birthdate that matches the grade: Grade 5 learners are 10 years old and Grade 6 learners 11.
     */
    private function birthdate(GradeLevel $grade): string
    {
        $age = $grade === GradeLevel::Five ? 10 : 11;
        $daysAfterBirthday = $this->random->int(0, 360);

        return DemoClock::date((int) strtotime("-{$age} years -{$daysAfterBirthday} days", $this->clock->startOfToday()));
    }

    /**
     * Seed the learner profiles; points, levels and sync times are filled in by the progress seeder.
     *
     * @param  list<array{user: array<string, mixed>, profile: array<string, mixed>}>  $learners
     * @param  array<string, int>  $learnerIds
     */
    private function seedProfiles(array $learners, array $learnerIds): void
    {
        BulkInsert::models(StudentProfile::class, array_map(fn (array $learner): array => [
            ...$learner['profile'],
            'user_id' => $learnerIds[$learner['user']['username']],
            'total_points' => 0,
            'level' => 1,
            'last_active_at' => null,
            'last_synced_at' => null,
            'updated_at' => $learner['profile']['created_at'],
        ], $learners));
    }

    /**
     * Enroll every learner in every classroom of their section, and last year's class in the archived classroom.
     *
     * @param  list<array{section: string, enrolled_at: int, user: array<string, mixed>}>  $learners
     * @param  array<string, int>  $learnerIds
     */
    private function seedEnrollments(array $learners, array $learnerIds): void
    {
        $classroomIdsBySection = Classroom::query()
            ->active()
            ->whereBelongsTo(SchoolYear::query()->where('name', SchoolCatalog::ACTIVE_SCHOOL_YEAR)->sole())
            ->orderBy('id')
            ->get(['id', 'section'])
            ->groupBy('section')
            ->map(fn ($classrooms): array => $classrooms->modelKeys());

        $archivedClassroomId = Classroom::query()->archived()->sole(['id'])->getKey();
        $rows = [];

        foreach ($learners as $learner) {
            $studentId = $learnerIds[$learner['user']['username']];

            foreach ($classroomIdsBySection[$learner['section']] as $classroomId) {
                $rows[] = $this->enrollment($classroomId, $studentId, $learner['enrolled_at']);
            }

            if ($learner['section'] === PeopleCatalog::PREVIOUS_YEAR_SECTION) {
                $rows[] = $this->enrollment(
                    $archivedClassroomId,
                    $studentId,
                    DemoClock::at(PeopleCatalog::archivedClassroom()['enrolled_on'].' 08:00:00') + $this->random->int(0, 2 * 24 * 60) * 60,
                );
            }
        }

        BulkInsert::pivot((new Classroom)->students(), $rows);
    }

    /**
     * Build an enrollment row.
     *
     * @return array<string, mixed>
     */
    private function enrollment(int $classroomId, int $studentId, int $enrolledAt): array
    {
        return [
            'classroom_id' => $classroomId,
            'student_id' => $studentId,
            'enrolled_at' => DemoClock::format($enrolledAt),
            'created_at' => DemoClock::format($enrolledAt),
            'updated_at' => DemoClock::format($enrolledAt),
        ];
    }

    /**
     * Seed the parent and guardian accounts and link them to their family's learners.
     *
     * @param  list<array{surname: string, guardians: list<array{relationship: GuardianRelationship, kind: string, active: bool}>}>  $families
     * @param  list<array{family: int, user: array<string, mixed>}>  $learners
     * @param  array<string, int>  $learnerIds
     */
    private function seedGuardians(array $families, array $learners, array $learnerIds): void
    {
        $usedNames = [];
        $usedEmails = [];
        $accounts = [];

        foreach ($families as $familyIndex => $family) {
            foreach ($family['guardians'] as $guardian) {
                [$firstName, $surname] = $this->guardianName($guardian, $family['surname'], $usedNames);
                $createdAt = $this->guardianCreatedAt($guardian['kind']);

                $accounts[] = [
                    'family' => $familyIndex,
                    'relationship' => $guardian['relationship'],
                    'user' => $this->guardianAccount($guardian, $firstName, $surname, $createdAt, $usedEmails),
                ];
            }
        }

        BulkInsert::models(User::class, array_column($accounts, 'user'));

        $guardianIds = User::query()->ofRole(UserRole::Parent)->toBase()->pluck('id', 'email');
        $links = [];

        foreach ($accounts as $account) {
            $linkedAt = DemoClock::format(DemoClock::at($account['user']['created_at']) + 15 * 60);

            foreach ($learners as $learner) {
                if ($learner['family'] === $account['family']) {
                    $links[] = [
                        'guardian_id' => $guardianIds[$account['user']['email']],
                        'student_id' => $learnerIds[$learner['user']['username']],
                        'relationship' => $account['relationship']->value,
                        'created_at' => $linkedAt,
                        'updated_at' => $linkedAt,
                    ];
                }
            }
        }

        BulkInsert::pivot((new User)->linkedStudents(), $links);
    }

    /**
     * Pick a guardian's first and family name for the relationship, keeping guardian names unique.
     *
     * @param  array{relationship: GuardianRelationship, kind: string}  $guardian
     * @param  array<string, true>  $usedNames
     * @return array{0: string, 1: string}
     */
    private function guardianName(array $guardian, string $familySurname, array &$usedNames): array
    {
        if ($guardian['kind'] === 'demo') {
            return [PeopleCatalog::DEMO_FAMILY['guardian']['first_name'], $familySurname];
        }

        if ($guardian['kind'] === 'late') {
            $enrollee = collect(PeopleCatalog::LATE_ENROLLEES)->firstWhere('surname', $familySurname);

            return [$enrollee['guardian'], $familySurname];
        }

        $otherSurname = fn (): string => $this->random->pick(PeopleCatalog::SURNAMES);

        do {
            [$firstName, $surname] = match ($guardian['relationship']) {
                GuardianRelationship::Mother => [$this->random->pick(PeopleCatalog::WOMEN_NAMES), $familySurname],
                GuardianRelationship::Father => [$this->random->pick(PeopleCatalog::MEN_NAMES), $familySurname],
                GuardianRelationship::Grandparent => [$this->random->pick($this->random->chance(0.6) ? PeopleCatalog::GRANDMOTHER_NAMES : PeopleCatalog::GRANDFATHER_NAMES), $familySurname],
                GuardianRelationship::Sibling => [$this->random->pick(PeopleCatalog::OLDER_SIBLING_NAMES), $familySurname],
                GuardianRelationship::Relative => [$this->random->pick($this->random->chance(0.5) ? PeopleCatalog::WOMEN_NAMES : PeopleCatalog::MEN_NAMES), $this->random->chance(0.5) ? $familySurname : $otherSurname()],
                default => [$this->random->pick($this->random->chance(0.6) ? PeopleCatalog::WOMEN_NAMES : PeopleCatalog::MEN_NAMES), $otherSurname()],
            };
        } while (isset($usedNames[$firstName.' '.$surname]));

        $usedNames[$firstName.' '.$surname] = true;

        return [$firstName, $surname];
    }

    /**
     * Get when a guardian account was created: the demo parent and most parents after the June orientation, and
     * the late enrollee's mother this week.
     */
    private function guardianCreatedAt(string $kind): int
    {
        return match ($kind) {
            'demo' => DemoClock::at('2026-06-22 18:30:00'),
            'late' => DemoClock::workTime($this->random, $this->clock->startOfToday() - 3 * DemoClock::DAY),
            default => DemoClock::workTime($this->random, DemoClock::at('2026-06-20') + $this->random->int(0, 82) * DemoClock::DAY),
        };
    }

    /**
     * Build a guardian's account row.
     *
     * @param  array{kind: string, active: bool}  $guardian
     * @param  array<string, true>  $usedEmails
     * @return array<string, mixed>
     */
    private function guardianAccount(array $guardian, string $firstName, string $surname, int $createdAt, array &$usedEmails): array
    {
        $demo = PeopleCatalog::DEMO_FAMILY['guardian'];

        if ($guardian['kind'] === 'demo') {
            $email = $demo['email'];
        } else {
            $local = Str::slug($firstName, '').'.'.Str::slug($surname, '');
            $email = "{$local}@example.com";

            for ($suffix = 2; isset($usedEmails[$email]); $suffix++) {
                $email = "{$local}{$suffix}@example.com";
            }
        }

        $usedEmails[$email] = true;
        $deactivatedAt = $guardian['active'] ? null : DemoClock::at('2026-10-05 10:00:00');

        return [
            'name' => "{$firstName} {$surname}",
            'username' => null,
            'email' => $email,
            'role' => UserRole::Parent->value,
            'email_verified_at' => DemoClock::format($createdAt + $this->random->int(5, 30 * 60) * 60),
            'password' => DemoPassword::hash(),
            'phone' => $guardian['kind'] === 'demo' ? $demo['phone'] : $this->random->pick(self::MOBILE_PREFIXES).str_pad((string) $this->random->int(0, 9999999), 7, '0', STR_PAD_LEFT),
            'is_active' => $guardian['active'],
            'last_login_at' => $this->guardianLastLogin($guardian, $createdAt, $deactivatedAt),
            'remember_token' => null,
            'created_at' => DemoClock::format($createdAt),
            'updated_at' => DemoClock::format($deactivatedAt ?? $createdAt),
        ];
    }

    /**
     * Get a guardian's last sign-in: recent for most, weeks ago for some, never for a few.
     */
    private function guardianLastLogin(array $guardian, int $createdAt, ?int $deactivatedAt): ?string
    {
        if ($deactivatedAt !== null) {
            return DemoClock::format($deactivatedAt - 23 * DemoClock::DAY);
        }

        $lastLogin = match (true) {
            $guardian['kind'] === 'demo' => $this->clock->now - 20 * DemoClock::HOUR,
            $guardian['kind'] === 'late' => $this->clock->now - $this->random->int(2, 30) * DemoClock::HOUR,
            default => match ($this->random->weighted(['recent' => 0.65, 'weeks' => 0.2, 'never' => 0.15])) {
                'recent' => $this->clock->now - (int) ($this->random->between(0.05, 10.0) * DemoClock::DAY),
                'weeks' => $this->clock->now - (int) ($this->random->between(11.0, 60.0) * DemoClock::DAY),
                default => null,
            },
        };

        return $lastLogin === null ? null : DemoClock::format(max($lastLogin, $createdAt + DemoClock::HOUR));
    }
}
