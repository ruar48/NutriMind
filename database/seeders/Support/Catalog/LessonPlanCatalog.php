<?php

namespace Database\Seeders\Support\Catalog;

/**
 * The lesson plans of the demo school.
 *
 * Most plans follow a learning module and are aligned with its activities, missions and quizzes. Some are left
 * unaligned on purpose to show the coverage feature: plans whose module has no published content yet (partial
 * coverage), plans for competencies no module covers (not covered) and plans without a competency code (unmapped).
 */
final class LessonPlanCatalog
{
    /**
     * Get the lesson plans.
     *
     * @return list<array{teacher: string, subject: string, grade: int, quarter: int, week: int, module: string|null, code: string|null, competency: string|null, title: string, objectives: list<string>, notes: string|null, align: bool}>
     */
    public static function plans(): array
    {
        return [
            self::plan('santos', 'HEALTH', 5, 1, 2, 'H5N-Ia-1', 'Go, Grow and Glow Foods', [
                'Classify common Filipino foods into Go, Grow and Glow groups.',
                'Explain how each food group helps the body.',
                'Sort the foods in a sample baon into the three groups.',
            ], 'Used the Basket Sort Rush mini-game as the motivation activity.'),
            self::plan('santos', 'HEALTH', 5, 1, 6, 'H5N-Ic-2', 'Building a Balanced Plate with Pinggang Pinoy', [
                'Describe the parts of the Pinggang Pinoy plate.',
                'Build a balanced meal with the right proportion of each food group.',
                'Explain why water is part of every meal.',
            ]),
            self::plan('santos', 'HEALTH', 5, 2, 3, 'H5N-IIa-1', 'Choosing Healthy Snacks', [
                'Compare snacks by their sugar, salt and fat.',
                'Choose healthy local snacks for merienda.',
                'Explain why salty and sugary snacks should be limited.',
            ], 'Learners brought snack wrappers to compare their salt and sugar.'),
            self::plan('santos', 'HEALTH', 5, 2, 7, 'H5N-IIc-3', 'Water: The Best Drink', [
                'Explain why the body needs water.',
                'Identify the signs of dehydration.',
                'Choose healthy drinks over sugary drinks.',
            ]),
            self::plan('santos', 'HEALTH', 5, 2, 11, 'H5N-IIa-1', 'Snacks and Drinks Review', [
                'Review healthy snack and drink choices.',
                'Plan a healthy merienda for a week.',
                'Prepare for the Hydration Heroes quiz.',
            ], align: false),
            self::plan('santos', 'HEALTH', 5, 2, 12, null, 'Quarter 2 Review and Remediation', [
                'Review the competencies of the Second Quarter.',
                'Give remediation activities to learners who need support.',
                'Prepare learners for the summative test.',
            ], 'Group learners by their NutriMind performance band for the remediation activities.', align: false),
            self::plan('santos', 'SCIENCE', 5, 1, 3, 'S5LT-Ie-1', 'Plants We Eat', [
                'Identify the plant part that each common vegetable comes from.',
                'Describe what plants need to grow.',
                'Group vegetables into roots, stems, leaves, flowers, fruits and seeds.',
            ]),
            self::plan('santos', 'SCIENCE', 5, 2, 2, 'S5LT-IIa-1', 'Organs of the Digestive System', [
                'Name the organs of the digestive system.',
                'Describe what happens to food in each organ.',
                'Practice habits that keep the digestive system healthy.',
            ]),
            self::plan('santos', 'SCIENCE', 5, 2, 9, null, 'Helpful Microorganisms in Food', [
                'Identify foods made with the help of microorganisms.',
                'Explain how microorganisms change milk into yogurt and sugarcane juice into vinegar.',
                'Practice safe handling of fermented foods.',
            ], code: 'S5LT-IIc-3', competency: 'Describes how helpful microorganisms are used to make foods such as yogurt, vinegar and patis.', align: false),
            self::plan('santos', 'SCIENCE', 5, 3, 2, 'S5MT-IIIb-2', 'Food Spoilage and Preservation', [
                'Explain how microorganisms make food spoil.',
                'Describe Filipino ways of preserving food.',
                'Tell whether food is still safe to eat.',
            ]),
            self::plan('santos', 'HEALTH', 6, 1, 1, 'H6N-Ia-1', 'Vitamins and Minerals', [
                'Identify the food sources of vitamins A, C and D.',
                'Explain the functions of calcium, iron and iodine.',
                'Plan a meal rich in vitamins and minerals.',
            ]),
            self::plan('santos', 'HEALTH', 6, 1, 5, 'H6N-Ib-2', 'Reading Nutrition Facts Labels', [
                'Identify the parts of a Nutrition Facts label.',
                'Compare two products using their labels.',
                'Read an ingredients list to find hidden sugar and salt.',
            ], 'Asked learners to bring labels of the drinks they buy at the canteen.'),
            self::plan('santos', 'HEALTH', 6, 2, 4, 'H6N-IIa-1', 'Nutritional Disorders and Their Prevention', [
                'Describe the signs of undernutrition and overweight.',
                'Identify the causes of anemia, goiter and night blindness.',
                'Suggest meals that prevent nutritional disorders.',
            ]),
            self::plan('santos', 'HEALTH', 6, 2, 10, null, 'Iodine Deficiency and Iodized Salt', [
                'Explain why the body needs iodine.',
                'Describe the effects of iodine deficiency.',
                'Explain why families should use iodized salt.',
            ], code: 'H6N-IIc-4', competency: 'Explains the importance of iodized salt in preventing iodine deficiency disorders.', align: false),
            self::plan('santos', 'HEALTH', 6, 3, 1, 'H6N-IIIa-1', 'Clean, Separate, Cook and Chill', [
                'Explain the four steps to food safety.',
                'Identify unsafe food-handling practices at home.',
                'Practice proper handwashing.',
            ], 'The Food Safety Squad world is still being prepared, so the mini-game cannot be assigned yet.', align: false),
            self::plan('santos', 'HEALTH', 6, 1, 7, null, 'Nutrition Month Culminating Activity', [
                'Showcase healthy dishes prepared by the learners.',
                'Share what was learned about balanced meals.',
                'Invite families to join healthy eating activities.',
            ], 'Held with the Grade 6 healthy baon fair at the covered court.', align: false),
            self::plan('santos', 'SCIENCE', 6, 1, 4, 'S6LT-If-1', 'Food Chains and Food Webs', [
                'Identify producers, consumers and decomposers.',
                'Trace the flow of energy in a food chain.',
                'Build a food web of a local ecosystem.',
            ]),
            self::plan('santos', 'SCIENCE', 6, 2, 6, 'S6LT-IIa-1', 'How Body Systems Use Nutrients', [
                'Describe how nutrients move from the small intestine into the blood.',
                'Explain how oxygen helps cells release energy from food.',
                'Relate healthy habits to healthy body systems.',
            ]),
            self::plan('santos', 'SCIENCE', 6, 2, 11, 'S6LT-IIg-3', 'Growing a School Vegetable Garden', [
                'Prepare garden plots with compost.',
                'Plant and care for pechay seedlings.',
                'Keep a garden journal.',
            ], align: false),
            self::plan('villanueva', 'SCIENCE', 5, 1, 3, 'S5LT-Ie-1', 'Edible Plant Parts', [
                'Identify the edible parts of common plants.',
                'Describe the needs of plants.',
                'Sort vegetables by the plant part we eat.',
            ]),
            self::plan('villanueva', 'SCIENCE', 5, 2, 2, 'S5LT-IIa-1', 'The Digestive System at Work', [
                'Trace the path of food through the digestive system.',
                'Describe the work of each digestive organ.',
                'Explain why chewing well helps digestion.',
            ]),
            self::plan('villanueva', 'SCIENCE', 6, 1, 4, 'S6LT-If-1', 'Energy in Food Chains', [
                'Explain why the sun is the source of energy in food chains.',
                'Classify organisms as producers, consumers or decomposers.',
                'Construct a food chain of a rice field.',
            ]),
            self::plan('villanueva', 'SCIENCE', 6, 2, 7, 'S6LT-IIa-1', 'Body Systems Working Together', [
                'Explain how the digestive, circulatory and respiratory systems work together.',
                'Describe the role of the villi.',
                'Relate exercise to healthy body systems.',
            ]),
            self::plan('villanueva', 'SCIENCE', 6, 2, 12, 'S6LT-IIg-3', 'Harvest Week in the Gulayan', [
                'Harvest and weigh the pechay from the class plots.',
                'Compute the harvest of each group.',
                'Donate the harvest to the school feeding program.',
            ], align: false),
            self::plan('bautista', 'EPP-HE', 5, 1, 2, 'EPP5HE-Ia-1', 'Safety and Hygiene in the Kitchen', [
                'Practice proper handwashing before preparing food.',
                'Handle knives and other kitchen tools safely.',
                'Keep the kitchen clean and free of hazards.',
            ]),
            self::plan('bautista', 'EPP-HE', 5, 2, 3, 'EPP5HE-IIb-3', 'Preparing a Healthy Breakfast', [
                'Explain why breakfast is important.',
                'Plan a breakfast with Go, Grow and Glow foods.',
                'Prepare a simple breakfast safely.',
            ]),
            self::plan('bautista', 'EPP-HE', 5, 2, 9, 'EPP5HE-IId-4', 'Packing a Healthy Baon', [
                'Plan a balanced and affordable baon.',
                'Pack baon in clean, safe containers.',
                'Choose dishes that do not spoil quickly.',
            ]),
            self::plan('bautista', 'EPP-HE', 6, 1, 3, 'EPP6HE-Ia-1', 'Planning Meals for the Family', [
                'Plan balanced meals for a family of five.',
                'Use foods in season to save money.',
                'Write a weekly menu.',
            ]),
            self::plan('bautista', 'EPP-HE', 6, 2, 5, 'EPP6HE-IIb-2', 'Shopping Wisely at the Palengke', [
                'Prepare a market list from a weekly menu.',
                'Choose fresh fish, meat and vegetables.',
                'Check the labels and condition of packaged goods.',
            ]),
            self::plan('bautista', 'EPP-HE', 6, 2, 12, 'EPP6HE-IIe-4', 'Cooking Ginisang Monggo', [
                'Follow a recipe for ginisang monggo.',
                'Cook with less salt and oil.',
                'Serve and store the dish safely.',
            ], align: false),
            self::plan('mendoza', 'PE', 5, 1, 2, 'PE5PF-Ib-1', 'Energy In and Energy Out', [
                'Explain energy balance.',
                'Identify activities that use a lot of energy.',
                'Relate food choices to physical activity.',
            ]),
            self::plan('mendoza', 'PE', 5, 2, 4, 'PE5PF-IIa-2', 'Moderate and Vigorous Activities', [
                'Tell moderate and vigorous activities apart.',
                'Play patintero and tumbang preso safely.',
                'Log 60 active minutes a day.',
            ]),
            self::plan('mendoza', 'HEALTH', 6, 2, 4, 'H6N-IIa-1', 'Preventing Malnutrition', [
                'Describe the forms of malnutrition.',
                'Identify foods that prevent anemia and goiter.',
                'Plan a balanced meal for a growing learner.',
            ]),
            self::plan('mendoza', 'PE', 6, 1, 3, 'PE6PF-Ib-1', 'Food as Fuel for Sports', [
                'Choose snacks that give lasting energy.',
                'Explain why water is needed before, during and after sports.',
                'Take the sit-and-reach and step tests.',
            ]),
            self::plan('mendoza', 'PE', 6, 2, 6, 'PE6PF-IIa-2', 'Planning an Active Week', [
                'Identify the components of physical fitness.',
                'Plan a week of varied physical activities.',
                'Perform a proper warm-up and cool-down.',
            ]),
            self::plan('fernandez', 'HEALTH', 5, 1, 2, 'H5N-Ia-1', 'Sorting Foods into Go, Grow and Glow', [
                'Sort foods into the three food groups.',
                'Give examples of local Go, Grow and Glow foods.',
                'Explain why we need all three groups every day.',
            ]),
            self::plan('fernandez', 'HEALTH', 5, 2, 5, 'H5N-IIa-1', 'Smart Snack Choices', [
                'Identify healthy and unhealthy snacks.',
                'Read the salt and sugar content of snack labels.',
                'Prepare a healthy merienda menu.',
            ]),
            self::plan('fernandez', 'HEALTH', 6, 2, 6, 'H6N-IIa-1', 'Signs of Nutritional Disorders', [
                'Describe the signs of common nutritional disorders.',
                'Identify the nutrient each disorder is linked to.',
                'Suggest ways to prevent them at home.',
            ]),
            self::plan('fernandez', 'HEALTH', 6, 3, 1, 'H6N-IIIa-1', 'Food Safety at Home', [
                'Explain how germs spread in the kitchen.',
                'Apply the four steps to food safety.',
                'Store leftovers safely.',
            ], align: false),
            self::plan('fernandez', 'EPP-HE', 6, 2, 9, 'EPP6HE-IIe-4', 'Healthy Cooking Methods', [
                'Compare healthy and less healthy cooking methods.',
                'Cook a dish with less salt and oil.',
                'Practice safe food handling while cooking.',
            ]),
        ];
    }

    /**
     * Build a lesson plan entry.
     *
     * @param  list<string>  $objectives
     * @return array{teacher: string, subject: string, grade: int, quarter: int, week: int, module: string|null, code: string|null, competency: string|null, title: string, objectives: list<string>, notes: string|null, align: bool}
     */
    private static function plan(
        string $teacher,
        string $subject,
        int $grade,
        int $quarter,
        int $week,
        ?string $module,
        string $title,
        array $objectives,
        ?string $notes = null,
        ?string $code = null,
        ?string $competency = null,
        bool $align = true,
    ): array {
        return [
            'teacher' => $teacher,
            'subject' => $subject,
            'grade' => $grade,
            'quarter' => $quarter,
            'week' => $week,
            'module' => $module,
            'code' => $code ?? $module,
            'competency' => $competency,
            'title' => $title,
            'objectives' => $objectives,
            'notes' => $notes,
            'align' => $align && $module !== null,
        ];
    }
}
