<?php

namespace Database\Seeders\Support\Catalog;

/**
 * The quizzes of the demo school, grouped by teacher, subject and grade.
 *
 * Each group's quizzes are assigned to all of the group's classrooms (one per section). Due dates are given in days
 * from today, so the quizzes are always spread over the last eight weeks and the next two.
 */
final class QuizCatalog
{
    /**
     * The instruction templates; ":count", ":minutes" and ":topic" are replaced per quiz.
     *
     * @var list<string>
     */
    public const INSTRUCTIONS = [
        'Read each question carefully and choose the best answer. You have :minutes minutes to finish. Good luck!',
        'Answer all :count questions. For identification items, type your answer and check your spelling before submitting.',
        'This quiz covers :topic. Review the NutriMind activities first, then do your best!',
        'Take your time and read every choice before answering. Only your best attempt counts.',
    ];

    /**
     * Get the quiz groups of the active school year.
     *
     * @return list<array{teacher: string, subject: string, grade: int, sections: list<string>, quizzes: list<array{title: string, focus: list<string>, due: int, status: string, questions: int, minutes: int, attempts: int|null, passing: int, shuffle: bool, show_answers: bool, assigned: bool, reviewed: bool}>}>
     */
    public static function groups(): array
    {
        return [
            self::group('santos', 'HEALTH', 5, ['Sampaguita', 'Ilang-Ilang'], [
                self::quiz('Go, Grow and Glow Review Quiz', ['H5N-Ia-1'], -52, 'closed', 10),
                self::quiz('Pinggang Pinoy Plate Quiz', ['H5N-Ic-2'], -33, 'published', 10, shuffle: true),
                self::quiz('Smart Snacking Quiz', ['H5N-IIa-1'], -12, 'published', 10, shuffle: true),
                self::quiz('Hydration Heroes Quiz', ['H5N-IIc-3'], 5, 'published', 10, minutes: 15),
                self::quiz('Quarter 2 Health Summative Test', [], 13, 'draft', 12, minutes: 40, attempts: 1, reviewed: false),
            ]),
            self::group('santos', 'SCIENCE', 5, ['Sampaguita'], [
                self::quiz('Plants We Eat Quiz', ['S5LT-Ie-1'], -47, 'closed', 9, minutes: 15),
                self::quiz('Digestive System Quiz: Mouth to Stomach', ['S5LT-IIa-1'], -26, 'published', 10),
                self::quiz('Digestive System Quiz: The Intestines', ['S5LT-IIa-1'], -6, 'published', 10, attempts: 3),
                self::quiz('Science 5 Quarter 2 Assessment', [], 11, 'draft', 10, minutes: 40, attempts: 1),
            ]),
            self::group('santos', 'HEALTH', 6, ['Narra'], [
                self::quiz('Vitamins and Minerals Quiz', ['H6N-Ia-1'], -50, 'closed', 10),
                self::quiz('Label Detective Quiz', ['H6N-Ib-2'], -36, 'published', 10, shuffle: true),
                self::quiz('Nutritional Disorders Quiz', ['H6N-IIa-1'], -15, 'published', 10),
                self::quiz('Nutrition Review: Labels and Disorders', ['H6N-Ib-2', 'H6N-IIa-1'], 8, 'published', 10, minutes: 25, attempts: null),
            ]),
            self::group('santos', 'SCIENCE', 6, ['Narra'], [
                self::quiz('Food Chains and Food Webs Quiz', ['S6LT-If-1'], -44, 'closed', 9, minutes: 15),
                self::quiz('Body Systems Teamwork Quiz', ['S6LT-IIa-1'], -19, 'published', 9, minutes: 15),
                self::quiz('School Garden Quiz', ['S6LT-IIg-3'], 4, 'published', 9, minutes: 15),
                self::quiz('Science 6 Long Test', [], 15, 'draft', 9, minutes: 45, attempts: 1, assigned: false, reviewed: false),
            ]),
            self::group('villanueva', 'SCIENCE', 5, ['Ilang-Ilang', 'Gumamela'], [
                self::quiz('Edible Plant Parts Quiz', ['S5LT-Ie-1'], -49, 'closed', 9, minutes: 15),
                self::quiz('Digestive System Check', ['S5LT-IIa-1'], -28, 'published', 10),
                self::quiz('Digestion and Healthy Habits', ['S5LT-IIa-1'], -8, 'published', 10, showAnswers: false),
                self::quiz('Science 5 Mid-Quarter Review', [], 6, 'published', 10, minutes: 30, attempts: null),
            ]),
            self::group('villanueva', 'SCIENCE', 6, ['Molave'], [
                self::quiz('Food Web Challenge', ['S6LT-If-1'], -42, 'closed', 9, minutes: 15),
                self::quiz('Body Systems Quiz', ['S6LT-IIa-1'], -21, 'published', 9, minutes: 15),
                self::quiz('Gulayan Garden Quiz', ['S6LT-IIg-3'], -3, 'published', 9, minutes: 15),
            ]),
            self::group('bautista', 'EPP-HE', 5, ['Sampaguita'], [
                self::quiz('Kitchen Safety Quiz', ['EPP5HE-Ia-1'], -45, 'closed', 8, minutes: 15),
                self::quiz('Breakfast Basics Quiz', ['EPP5HE-IIb-3'], -24, 'published', 8, minutes: 15, showAnswers: false),
                self::quiz('Healthy Baon Quiz', ['EPP5HE-IId-4'], 3, 'published', 8, minutes: 15),
            ]),
            self::group('bautista', 'EPP-HE', 6, ['Narra'], [
                self::quiz('Family Meal Planning Quiz', ['EPP6HE-Ia-1'], -40, 'closed', 8, minutes: 15),
                self::quiz('Smart Shopping Quiz', ['EPP6HE-IIb-2'], -17, 'published', 8, minutes: 15),
                self::quiz('Healthy Cooking Quiz', ['EPP6HE-IIe-4'], 12, 'draft', 8, minutes: 15, reviewed: false),
            ]),
            self::group('mendoza', 'PE', 5, ['Sampaguita', 'Ilang-Ilang'], [
                self::quiz('Energy Balance Quiz', ['PE5PF-Ib-1'], -38, 'closed', 8, minutes: 10, passing: 70),
                self::quiz('Active Play Quiz', ['PE5PF-IIa-2'], -10, 'published', 8, minutes: 10, passing: 70),
                self::quiz('60 Active Minutes Check', ['PE5PF-IIa-2', 'PE5PF-Ib-1'], 7, 'published', 8, minutes: 10, passing: 70),
            ]),
            self::group('mendoza', 'HEALTH', 6, ['Molave'], [
                self::quiz('Vitamins and Minerals Check', ['H6N-Ia-1'], -46, 'closed', 10),
                self::quiz('Food Label Quiz', ['H6N-Ib-2'], -30, 'published', 10),
                self::quiz('Nutritional Disorders Check', ['H6N-IIa-1'], -9, 'published', 10),
                self::quiz('Health 6 Quarter 2 Test', [], 12, 'draft', 12, minutes: 40, attempts: 1),
            ]),
            self::group('mendoza', 'PE', 6, ['Mahogany'], [
                self::quiz('Food as Fuel Quiz', ['PE6PF-Ib-1'], -34, 'closed', 8, minutes: 10, passing: 70),
                self::quiz('Components of Fitness Quiz', ['PE6PF-IIa-2'], -13, 'published', 8, minutes: 10, passing: 70),
                self::quiz('Weekly Activity Plan Check', ['PE6PF-IIa-2', 'PE6PF-Ib-1'], 9, 'published', 8, minutes: 10, passing: 70),
            ]),
            self::group('fernandez', 'HEALTH', 5, ['Gumamela'], [
                self::quiz('Food Groups Quiz', ['H5N-Ia-1'], -51, 'closed', 10, attempts: 3),
                self::quiz('Balanced Plate Quiz', ['H5N-Ic-2'], -31, 'closed', 10, attempts: 3),
                self::quiz('Healthy Snacks Quiz', ['H5N-IIa-1'], -14, 'published', 10, attempts: 3),
                self::quiz('Water and Healthy Drinks Quiz', ['H5N-IIc-3'], 2, 'published', 10, attempts: 3),
            ]),
            self::group('fernandez', 'EPP-HE', 6, ['Molave'], [
                self::quiz('Meal Planning Basics', ['EPP6HE-Ia-1'], -37, 'closed', 8, minutes: 15),
                self::quiz('Market Smarts Quiz', ['EPP6HE-IIb-2'], -16, 'published', 8, minutes: 15),
                self::quiz('Cooking Safely Quiz', ['EPP6HE-IIe-4'], 10, 'published', 8, minutes: 15),
            ]),
            self::group('fernandez', 'HEALTH', 6, ['Mahogany'], [
                self::quiz('Vitamins and Minerals Quiz', ['H6N-Ia-1'], -48, 'closed', 10, attempts: 3),
                self::quiz('Reading Food Labels Quiz', ['H6N-Ib-2'], -27, 'published', 10, attempts: 3),
                self::quiz('Nutritional Disorders Quiz', ['H6N-IIa-1'], -7, 'published', 10, attempts: 3),
            ]),
        ];
    }

    /**
     * Get the closed quiz of last year's archived classroom.
     *
     * @return array{title: string, focus: list<string>, created_at: string, published_at: string, available_from: string, due_at: string, closed_at: string, questions: int, minutes: int, attempts: int, passing: int}
     */
    public static function archivedQuiz(): array
    {
        return [
            'title' => 'Health 5 Fourth Quarter Assessment',
            'focus' => [],
            'created_at' => '2026-03-03 16:20:00',
            'published_at' => '2026-03-05 15:05:00',
            'available_from' => '2026-03-06 07:00:00',
            'due_at' => '2026-03-13 23:59:00',
            'closed_at' => '2026-03-16 08:30:00',
            'questions' => 12,
            'minutes' => 40,
            'attempts' => 2,
            'passing' => 75,
        ];
    }

    /**
     * Build a quiz group.
     *
     * @param  list<string>  $sections
     * @param  list<array{title: string, focus: list<string>, due: int, status: string, questions: int, minutes: int, attempts: int|null, passing: int, shuffle: bool, show_answers: bool, assigned: bool, reviewed: bool}>  $quizzes
     * @return array{teacher: string, subject: string, grade: int, sections: list<string>, quizzes: list<array{title: string, focus: list<string>, due: int, status: string, questions: int, minutes: int, attempts: int|null, passing: int, shuffle: bool, show_answers: bool, assigned: bool, reviewed: bool}>}
     */
    private static function group(string $teacher, string $subject, int $grade, array $sections, array $quizzes): array
    {
        return ['teacher' => $teacher, 'subject' => $subject, 'grade' => $grade, 'sections' => $sections, 'quizzes' => $quizzes];
    }

    /**
     * Build a quiz entry. Drafts are reviewed unless stated otherwise; published and closed quizzes always are.
     *
     * @param  list<string>  $focus  The competency codes of the modules the quiz is about (empty for a summative test).
     * @return array{title: string, focus: list<string>, due: int, status: string, questions: int, minutes: int, attempts: int|null, passing: int, shuffle: bool, show_answers: bool, assigned: bool, reviewed: bool}
     */
    private static function quiz(
        string $title,
        array $focus,
        int $due,
        string $status,
        int $questions,
        int $minutes = 20,
        ?int $attempts = 2,
        int $passing = 75,
        bool $shuffle = false,
        bool $showAnswers = true,
        bool $assigned = true,
        bool $reviewed = true,
    ): array {
        return [
            'title' => $title,
            'focus' => $focus,
            'due' => $due,
            'status' => $status,
            'questions' => $questions,
            'minutes' => $minutes,
            'attempts' => $attempts,
            'passing' => $passing,
            'shuffle' => $shuffle,
            'show_answers' => $showAnswers,
            'assigned' => $assigned,
            'reviewed' => $status !== 'draft' || $reviewed,
        ];
    }
}
