<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Services\Settings;
use Database\Seeders\Support\BulkInsert;
use Database\Seeders\Support\Catalog\SchoolCatalog;
use Database\Seeders\Support\DemoClock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * Seeds the school profile, the academic defaults, the school years with their quarters and the subjects.
 */
class SchoolSetupSeeder extends Seeder
{
    /**
     * The academic and forum settings stored with their configured default values.
     */
    private const DEFAULT_SETTING_KEYS = [
        'default_passing_score', 'default_max_attempts', 'default_learning_mode',
        'points_per_level', 'max_level', 'forum_enabled', 'forum_requires_approval',
    ];

    /**
     * Run the database seeds.
     */
    public function run(Settings $settings): void
    {
        $clock = DemoClock::start();

        $settings->setMany([
            ...SchoolCatalog::profile(),
            ...Arr::only(config('nutrimind.settings'), self::DEFAULT_SETTING_KEYS),
        ]);

        $this->seedSchoolYears($clock);
        $this->seedSubjects();
    }

    /**
     * Seed the school years and their quarters; the active quarter is the active year's quarter that started last.
     */
    private function seedSchoolYears(DemoClock $clock): void
    {
        $schoolYears = SchoolCatalog::schoolYears();
        $today = DemoClock::date($clock->now);

        BulkInsert::models(SchoolYear::class, array_map(fn (array $schoolYear): array => [
            'name' => $schoolYear['name'],
            'starts_on' => $schoolYear['starts_on'],
            'ends_on' => $schoolYear['ends_on'],
            'is_active' => $schoolYear['is_active'],
            'created_at' => $this->setUpMoment($schoolYear['starts_on']),
            'updated_at' => $this->setUpMoment($schoolYear['starts_on']),
        ], $schoolYears));

        $schoolYearIds = SchoolYear::query()->pluck('id', 'name');
        $terms = [];

        foreach ($schoolYears as $schoolYear) {
            $activeSequence = $schoolYear['is_active'] ? $this->activeSequence($schoolYear['quarters'], $today) : null;

            foreach ($schoolYear['quarters'] as $index => [$startsOn, $endsOn]) {
                $terms[] = [
                    'school_year_id' => $schoolYearIds[$schoolYear['name']],
                    'name' => SchoolCatalog::QUARTER_NAMES[$index],
                    'sequence' => $index + 1,
                    'starts_on' => $startsOn,
                    'ends_on' => $endsOn,
                    'is_active' => $activeSequence === $index + 1,
                    'created_at' => $this->setUpMoment($schoolYear['starts_on']),
                    'updated_at' => $this->setUpMoment($schoolYear['starts_on']),
                ];
            }
        }

        BulkInsert::models(AcademicTerm::class, $terms);
    }

    /**
     * Get the sequence of the quarter that started last on or before today (the first quarter before the year starts).
     *
     * @param  list<array{0: string, 1: string}>  $quarters
     */
    private function activeSequence(array $quarters, string $today): int
    {
        $sequence = 1;

        foreach ($quarters as $index => [$startsOn]) {
            if ($startsOn <= $today) {
                $sequence = $index + 1;
            }
        }

        return $sequence;
    }

    /**
     * Seed the subjects.
     */
    private function seedSubjects(): void
    {
        $createdAt = '2025-05-12 09:30:00';
        $rows = [];

        foreach (SchoolCatalog::subjects() as $code => $subject) {
            $rows[] = [
                'name' => $subject['name'],
                'code' => $code,
                'description' => $subject['description'],
                'color' => $subject['color'],
                'icon' => $subject['icon'],
                'is_active' => true,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        BulkInsert::models(Subject::class, $rows);
    }

    /**
     * Get the moment the administrator set up a school year: six weeks before it starts.
     */
    private function setUpMoment(string $startsOn): string
    {
        return DemoClock::format(DemoClock::at($startsOn.' 10:00:00') - 42 * DemoClock::DAY);
    }
}
