<?php

namespace Database\Seeders\Support\Catalog;

/**
 * The school profile, calendar and subjects of the demo school.
 */
final class SchoolCatalog
{
    /**
     * The DepEd School ID of the demo school (also the first six digits of the LRNs it issued).
     */
    public const SCHOOL_ID = '136742';

    /**
     * The names of the four quarters, in sequence order.
     */
    public const QUARTER_NAMES = ['1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter'];

    /**
     * The name of the active school year.
     */
    public const ACTIVE_SCHOOL_YEAR = '2026–2027';

    /**
     * The name of the previous school year.
     */
    public const PREVIOUS_SCHOOL_YEAR = '2025–2026';

    /**
     * Get the school profile and the announcement shown on every portal.
     *
     * @return array<string, string>
     */
    public static function profile(): array
    {
        return [
            'school_name' => 'Mabini Elementary School',
            'school_id' => self::SCHOOL_ID,
            'school_address' => 'Apolinario Mabini Street, Barangay San Roque, Marikina City, Metro Manila 1801',
            'school_head' => 'Dr. Erlinda M. Castillo',
            'school_email' => 'mabini.es@nutrimind.test',
            'school_phone' => '(02) 8941-2357',
            'announcement' => 'Healthy Baon Week runs from November 9 to 20! Second Quarter quizzes close on November 27, and our Parent-Teacher Conference is on Saturday, November 28.',
        ];
    }

    /**
     * Get the school years and their quarters (first and last day of each quarter).
     *
     * @return list<array{name: string, starts_on: string, ends_on: string, is_active: bool, quarters: list<array{0: string, 1: string}>}>
     */
    public static function schoolYears(): array
    {
        return [
            [
                'name' => self::PREVIOUS_SCHOOL_YEAR,
                'starts_on' => '2025-06-16',
                'ends_on' => '2026-03-31',
                'is_active' => false,
                'quarters' => [
                    ['2025-06-16', '2025-08-22'],
                    ['2025-08-25', '2025-11-07'],
                    ['2025-11-10', '2026-01-30'],
                    ['2026-02-02', '2026-03-31'],
                ],
            ],
            [
                'name' => self::ACTIVE_SCHOOL_YEAR,
                'starts_on' => '2026-06-15',
                'ends_on' => '2027-04-16',
                'is_active' => true,
                'quarters' => [
                    ['2026-06-15', '2026-08-28'],
                    ['2026-08-31', '2026-11-27'],
                    ['2026-11-30', '2027-02-12'],
                    ['2027-02-15', '2027-04-16'],
                ],
            ],
        ];
    }

    /**
     * Get the subjects, keyed by subject code.
     *
     * @return array<string, array{name: string, short_name: string, description: string, color: string, icon: string}>
     */
    public static function subjects(): array
    {
        return [
            'HEALTH' => [
                'name' => 'Health',
                'short_name' => 'Health',
                'description' => 'MAPEH Health: nutrition, personal health, food safety and healthy habits for Grade 5 and Grade 6 learners.',
                'color' => 'berry',
                'icon' => 'heart-pulse',
            ],
            'SCIENCE' => [
                'name' => 'Science',
                'short_name' => 'Science',
                'description' => 'Living things and their environment: plants we eat, the digestive system, food chains and school gardening.',
                'color' => 'teal',
                'icon' => 'leaf',
            ],
            'EPP-HE' => [
                'name' => 'EPP – Home Economics',
                'short_name' => 'EPP-HE',
                'description' => 'Edukasyong Pantahanan at Pangkabuhayan: kitchen safety, healthy breakfast and baon, meal planning, smart shopping and simple cooking.',
                'color' => 'carrot',
                'icon' => 'utensils',
            ],
            'PE' => [
                'name' => 'Physical Education',
                'short_name' => 'PE',
                'description' => 'MAPEH Physical Education: energy balance, active play, fitness and an active lifestyle.',
                'color' => 'indigo',
                'icon' => 'activity',
            ],
        ];
    }
}
