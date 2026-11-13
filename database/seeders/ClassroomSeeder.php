<?php

namespace Database\Seeders;

use App\Enums\GradeLevel;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\Support\BulkInsert;
use Database\Seeders\Support\Catalog\PeopleCatalog;
use Database\Seeders\Support\Catalog\SchoolCatalog;
use Database\Seeders\Support\DemoClock;
use Database\Seeders\Support\DemoRandom;
use Illuminate\Database\Seeder;

/**
 * Seeds the classrooms: every section of the active school year has classrooms for two to four subjects, and last
 * year's Health 5 – Sampaguita class is archived.
 */
class ClassroomSeeder extends Seeder
{
    /**
     * What each subject covers in each grade, for the classroom descriptions.
     *
     * @var array<string, array<int, string>>
     */
    private const TOPICS = [
        'HEALTH' => [5 => 'Go, Grow and Glow foods, Pinggang Pinoy, healthy snacks and hydration', 6 => 'vitamins and minerals, food labels, nutritional disorders and food safety'],
        'SCIENCE' => [5 => 'the plants we eat, the digestive system and food preservation', 6 => 'food chains, body systems and the school vegetable garden'],
        'EPP-HE' => [5 => 'kitchen safety, healthy breakfasts and baon packing', 6 => 'family meal planning, smart shopping and healthy cooking'],
        'PE' => [5 => 'energy balance and active play through Filipino games', 6 => 'fitness, food as fuel and an active lifestyle plan'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $random = DemoRandom::stream('classrooms');
        $schoolYearIds = SchoolYear::query()->pluck('id', 'name');
        $subjectIds = Subject::query()->pluck('id', 'code');
        $teacherIds = User::query()->toBase()->pluck('id', 'email');
        $issuedCodes = Classroom::query()->pluck('class_code')->all();
        $rows = [];

        foreach (PeopleCatalog::classrooms() as $index => $classroom) {
            $grade = PeopleCatalog::SECTIONS[$classroom['section']]['grade'];
            $createdAt = DemoClock::format(DemoClock::workTime($random, DemoClock::at('2026-06-01') + intdiv($index, 3) * DemoClock::DAY));

            $rows[] = $this->classroom(
                $random, $issuedCodes, $schoolYearIds[SchoolCatalog::ACTIVE_SCHOOL_YEAR], $subjectIds[$classroom['subject']],
                $teacherIds[PeopleCatalog::teacherEmail($classroom['teacher'])], $classroom['subject'], $grade, $classroom['section'],
                PeopleCatalog::roomFor($classroom['subject'], $classroom['section']), $classroom['mode']->value, $createdAt, null,
            );
        }

        $archived = PeopleCatalog::archivedClassroom();

        $rows[] = $this->classroom(
            $random, $issuedCodes, $schoolYearIds[SchoolCatalog::PREVIOUS_SCHOOL_YEAR], $subjectIds[$archived['subject']],
            $teacherIds[PeopleCatalog::teacherEmail($archived['teacher'])], $archived['subject'], $archived['grade'], $archived['section'],
            $archived['room'], $archived['mode']->value, $archived['created_on'].' 15:20:00', $archived['archived_at'],
        );

        BulkInsert::models(Classroom::class, $rows);
    }

    /**
     * Build a classroom row with a class code no other classroom uses.
     *
     * @param  list<string>  $issuedCodes
     * @return array<string, mixed>
     */
    private function classroom(
        DemoRandom $random,
        array &$issuedCodes,
        int $schoolYearId,
        int $subjectId,
        int $teacherId,
        string $subjectCode,
        int $grade,
        string $section,
        string $room,
        string $learningMode,
        string $createdAt,
        ?string $archivedAt,
    ): array {
        $subject = SchoolCatalog::subjects()[$subjectCode];

        return [
            'school_year_id' => $schoolYearId,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'name' => "{$subject['short_name']} {$grade} – {$section}",
            'grade_level' => $grade,
            'section' => $section,
            'room' => $room,
            'description' => $subject['name'].' for '.GradeLevel::from($grade)->label()." – {$section}: ".self::TOPICS[$subjectCode][$grade].'.',
            'class_code' => $issuedCodes[] = $this->classCode($random, $issuedCodes),
            'learning_mode' => $learningMode,
            'archived_at' => $archivedAt,
            'created_at' => $createdAt,
            'updated_at' => $archivedAt ?? $createdAt,
        ];
    }

    /**
     * Draw a class code from the class code alphabet that is not used yet.
     *
     * @param  list<string>  $issuedCodes
     */
    private function classCode(DemoRandom $random, array $issuedCodes): string
    {
        $alphabet = Classroom::CLASS_CODE_ALPHABET;

        do {
            $code = '';

            for ($position = 0; $position < Classroom::CLASS_CODE_LENGTH; $position++) {
                $code .= $alphabet[$random->int(0, strlen($alphabet) - 1)];
            }
        } while (in_array($code, $issuedCodes, true));

        return $code;
    }
}
