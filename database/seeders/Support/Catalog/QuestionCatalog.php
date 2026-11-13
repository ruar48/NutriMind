<?php

namespace Database\Seeders\Support\Catalog;

use App\Enums\QuestionType;
use Database\Seeders\Support\DemoRandom;

/**
 * The question content of the demo school.
 *
 * Bank questions become question bank items owned by a teacher; the others are questions a teacher wrote directly
 * in a quiz. Multiple choice questions list the correct choice first and the most tempting wrong choice second; the
 * stored order is shuffled the same way on every run. Identification questions list the accepted answers and the
 * wrong answers learners typically type.
 */
final class QuestionCatalog
{
    /**
     * Get every question.
     *
     * @return list<array{module: string, owner: string, bank: bool, shared: bool, reviewed: bool, type: QuestionType, difficulty: string, prompt: string, choices: list<string>|null, accepted: list<string>, wrong: list<string>, true_statement: bool|null, explanation: string}>
     */
    public static function all(): array
    {
        return [
            ...self::healthFive(),
            ...self::healthSix(),
            ...self::scienceFive(),
            ...self::scienceSix(),
            ...self::homeEconomicsFive(),
            ...self::homeEconomicsSix(),
            ...self::physicalEducation(),
        ];
    }

    /**
     * Get every question keyed by prompt.
     *
     * @return array<string, array{module: string, owner: string, bank: bool, shared: bool, reviewed: bool, type: QuestionType, difficulty: string, prompt: string, choices: list<string>|null, accepted: list<string>, wrong: list<string>, true_statement: bool|null, explanation: string}>
     */
    public static function byPrompt(): array
    {
        return array_column(self::all(), null, 'prompt');
    }

    /**
     * Get the stored content of a question: the choices in their (deterministically shuffled) order and the answer key.
     *
     * @param  array{type: QuestionType, prompt: string, choices: list<string>|null, accepted: list<string>, true_statement: bool|null, explanation: string}  $question
     * @return array{type: string, prompt: string, choices: list<string>|null, correct_answer: string, explanation: string}
     */
    public static function content(array $question): array
    {
        [$choices, $correctAnswer] = match ($question['type']) {
            QuestionType::MultipleChoice => self::arrangeChoices($question['prompt'], $question['choices']),
            QuestionType::TrueFalse => [['True', 'False'], $question['true_statement'] ? '0' : '1'],
            QuestionType::Identification => [null, implode('|', $question['accepted'])],
        };

        return [
            'type' => $question['type']->value,
            'prompt' => $question['prompt'],
            'choices' => $choices,
            'correct_answer' => $correctAnswer,
            'explanation' => $question['explanation'],
        ];
    }

    /**
     * Get the points a question is worth in a quiz: recall questions and hard questions are worth more.
     *
     * @param  array{type: QuestionType, difficulty: string}  $question
     */
    public static function pointsFor(array $question): int
    {
        return match ($question['type']) {
            QuestionType::TrueFalse => 1,
            QuestionType::MultipleChoice => $question['difficulty'] === 'hard' ? 2 : 1,
            QuestionType::Identification => $question['difficulty'] === 'hard' ? 3 : 2,
        };
    }

    /**
     * Shuffle the choices of a multiple choice question the same way on every run.
     *
     * @param  list<string>  $choices
     * @return array{0: list<string>, 1: string}
     */
    private static function arrangeChoices(string $prompt, array $choices): array
    {
        $order = DemoRandom::stream('choices:'.$prompt)->shuffle(array_keys($choices));

        return [
            array_map(fn (int $index): string => $choices[$index], $order),
            (string) array_search(0, $order, true),
        ];
    }

    /**
     * Grade 5 Health questions.
     *
     * @return list<array<string, mixed>>
     */
    private static function healthFive(): array
    {
        return [
            self::choice('H5N-Ia-1', 'santos', 'easy', 'Which food group gives our body energy to work and play?',
                ['Go foods', 'Grow foods', 'Glow foods', 'Junk foods'],
                'Go foods such as rice, bread, corn and root crops are rich in carbohydrates, which give the body energy.', shared: true),
            self::choice('H5N-Ia-1', 'santos', 'easy', 'Which of these is a Grow food?',
                ['Fish', 'Kamote', 'Banana', 'Pandesal'],
                'Fish is rich in protein, which helps the body grow and repair itself. Kamote and pandesal are Go foods, and banana is a Glow food.', shared: true),
            self::trueFalse('H5N-Ia-1', 'fernandez', 'easy', 'Fruits and vegetables are Glow foods because they are rich in vitamins and minerals.', true,
                'Glow foods like papaya, malunggay and kangkong give vitamins and minerals that keep the skin, eyes and immune system healthy.', shared: true),
            self::identification('H5N-Ia-1', 'santos', 'medium', 'What food group do rice, corn, bread and root crops belong to?',
                ['Go foods', 'Go food', 'Go'], ['Grow foods', 'Glow foods', 'Carbohydrates'],
                'Rice, corn, bread and root crops are Go foods: they are rich in carbohydrates that give energy.'),
            self::choice('H5N-Ic-2', 'santos', 'medium', 'According to Pinggang Pinoy, which foods should fill about half of your plate?',
                ['Fruits and vegetables (Glow foods)', 'Rice and other Go foods', 'Fish, meat and eggs (Grow foods)', 'Desserts and sweets'],
                'Pinggang Pinoy fills half the plate with fruits and vegetables, about a third with rice or other Go foods, and the rest with Grow foods.', shared: true),
            self::choice('H5N-Ic-2', 'santos', 'easy', 'Which drink does the Pinggang Pinoy guide show beside the plate?',
                ['A glass of water', 'A bottle of soft drink', 'A glass of powdered juice', 'A cup of iced coffee'],
                'The Pinggang Pinoy guide pairs every meal with a glass of water, the best drink for the body.', shared: true),
            self::trueFalse('H5N-Ic-2', 'fernandez', 'medium', 'Pinggang Pinoy shows how much of each food group to put on your plate at every meal.', true,
                'Pinggang Pinoy is a per-meal guide: it shows the right proportion of Go, Grow and Glow foods for one meal.', shared: true),
            self::identification('H5N-Ic-2', 'santos', 'medium', 'What is the Filipino food guide that uses a plate to show how much Go, Grow and Glow food to eat in one meal?',
                ['Pinggang Pinoy', 'Pinggang Pinoy food guide'], ['Food pyramid', 'Food plate', 'Pingang Pinoy'],
                'Pinggang Pinoy, developed by the Food and Nutrition Research Institute (FNRI-DOST), shows the right portions for one meal.'),
            self::choice('H5N-IIa-1', 'santos', 'easy', 'Which merienda is the healthiest choice?',
                ['Boiled saba bananas', 'A bag of chicharon', 'A chocolate-coated wafer', 'A can of soft drink'],
                'Boiled saba gives energy, fiber and vitamins without added salt or sugar.', shared: true),
            self::choice('H5N-IIa-1', 'fernandez', 'medium', 'Why should we limit salty snacks like chips and chicharon?',
                ['Too much salt can raise blood pressure', 'Salt makes our bones grow faster', 'Salt gives us vitamin C', 'Salt keeps our teeth white'],
                'Eating too much salt (sodium) over time can lead to high blood pressure, so salty snacks should be eaten only once in a while.', shared: true),
            self::trueFalse('H5N-IIa-1', 'santos', 'easy', 'Eating too many sugary snacks can cause tooth decay.', true,
                'Germs in the mouth turn sugar into acids that damage the teeth, so sugary snacks should be limited.'),
            self::identification('H5N-IIa-1', 'fernandez', 'medium', 'What nutrient, found in fruits, vegetables and whole grains, helps us digest food and keeps us full longer?',
                ['Fiber', 'Dietary fiber', 'Fibre'], ['Fat', 'Sugar', 'Protein'],
                'Fiber helps food move through the digestive system and keeps us full longer.', reviewed: false),
            self::choice('H5N-IIc-3', 'santos', 'easy', 'About how many glasses of water should a school-age child drink every day?',
                ['6 to 8 glasses', '1 glass', '2 glasses', '15 to 20 glasses'],
                'Most school-age children need about 6 to 8 glasses of water a day, and more when it is hot or after playing.', shared: true),
            self::choice('H5N-IIc-3', 'santos', 'medium', 'Which of these is a sign that your body needs more water?',
                ['Dark yellow urine', 'Feeling full after eating', 'Having strong muscles', 'Sleeping well at night'],
                'Dark yellow urine, thirst, headache and dry lips are signs of dehydration. Drink water right away.'),
            self::trueFalse('H5N-IIc-3', 'fernandez', 'easy', 'Soft drinks are a good replacement for water when you are thirsty.', false,
                'Soft drinks are high in sugar. Water is the best drink when you are thirsty.', shared: true),
            self::identification('H5N-IIc-3', 'santos', 'medium', 'What is the condition called when the body loses more water than it takes in?',
                ['Dehydration'], ['Hydration', 'Fever', 'Dihydration'],
                'Dehydration happens when the body loses more water than it takes in, for example through sweat on a hot day.'),
            self::choice('H5N-Ia-1', 'santos', 'medium', 'Ana ate rice, fried fish and ginisang ampalaya for lunch. Which food group did the ampalaya give her?',
                ['Glow foods', 'Go foods', 'Grow foods', 'None of the food groups'],
                'Ampalaya is a vegetable, so it is a Glow food rich in vitamins and minerals.', bank: false),
            self::trueFalse('H5N-Ia-1', 'fernandez', 'easy', 'Eggs, milk and monggo are examples of Grow foods.', true,
                'Eggs, milk and beans like monggo are rich in protein, the nutrient that builds and repairs the body.', bank: false),
            self::choice('H5N-Ic-2', 'santos', 'easy', 'Which government agency developed the Pinggang Pinoy food guide?',
                ['The Food and Nutrition Research Institute (FNRI-DOST)', 'The Department of Public Works and Highways', 'The Bureau of Customs', 'The Philippine Statistics Authority'],
                'Pinggang Pinoy was created by the Food and Nutrition Research Institute of the Department of Science and Technology.', bank: false),
            self::choice('H5N-IIa-1', 'santos', 'medium', 'Liza has ₱20 for merienda. Which choice is the healthiest?',
                ['Two pieces of fresh banana', 'A pack of instant noodles', 'A sachet of powdered juice', 'A bag of cheese puffs'],
                'Fresh fruit is affordable and gives vitamins, minerals and fiber without too much salt or sugar.', bank: false),
            self::trueFalse('H5N-IIa-1', 'fernandez', 'medium', 'A snack labeled "fruit-flavored" always contains real fruit.', false,
                'Fruit-flavored snacks and drinks often contain only artificial flavors and added sugar. Check the ingredients list.', bank: false),
            self::choice('H5N-IIc-3', 'santos', 'medium', 'Which drink is a healthy choice after playing outdoors on a hot day?',
                ['Plain water or fresh buko water', 'Sweetened iced tea', 'An energy drink', 'A cola drink'],
                'Plain water and fresh buko (coconut) water replace the water lost through sweat without much added sugar.', bank: false),
        ];
    }

    /**
     * Grade 6 Health questions.
     *
     * @return list<array<string, mixed>>
     */
    private static function healthSix(): array
    {
        return [
            self::choice('H6N-Ia-1', 'santos', 'easy', 'Which vitamin keeps our eyes healthy and helps us see in dim light?',
                ['Vitamin A', 'Vitamin C', 'Vitamin D', 'Vitamin K'],
                'Vitamin A, found in carrots, kalabasa, malunggay and egg yolk, keeps the eyes healthy and helps us see in dim light.', shared: true),
            self::choice('H6N-Ia-1', 'santos', 'medium', 'Which fruit is an excellent source of vitamin C?',
                ['Bayabas (guava)', 'Saba banana', 'Avocado', 'Grapes'],
                'Bayabas has even more vitamin C than oranges. Vitamin C helps the body fight infections and heal wounds.', shared: true),
            self::choice('H6N-Ia-1', 'mendoza', 'medium', 'Which mineral helps carry oxygen in the blood?',
                ['Iron', 'Calcium', 'Iodine', 'Sodium'],
                'Iron is needed to make hemoglobin, which carries oxygen in red blood cells. Liver, lean meat, malunggay and monggo are good sources.', shared: true),
            self::trueFalse('H6N-Ia-1', 'fernandez', 'easy', 'Calcium from milk and small fish like dilis helps build strong bones and teeth.', true,
                'Milk, cheese, dilis eaten with the bones, and green leafy vegetables give calcium for strong bones and teeth.', shared: true),
            self::identification('H6N-Ia-1', 'fernandez', 'medium', 'What mineral is added to table salt to prevent goiter?',
                ['Iodine'], ['Iron', 'Sodium', 'Calcium'],
                'Iodine is added to salt (iodized salt) because a lack of iodine can cause goiter, a swelling of the thyroid gland in the neck.'),
            self::choice('H6N-Ib-2', 'santos', 'medium', 'On a Nutrition Facts label, the amounts of nutrients are given for what?',
                ['One serving', 'The whole store shelf', 'The price of the food', 'The expiration date'],
                'The amounts on a Nutrition Facts label are for one serving. If you eat two servings, you get double the amounts.', shared: true),
            self::choice('H6N-Ib-2', 'santos', 'hard', 'The ingredients list of a juice drink reads: water, sugar, apple juice concentrate, citric acid. What can you conclude?',
                ['It contains more sugar than apple juice', 'It is pure apple juice', 'It has no added sugar', 'Apple juice is its main ingredient'],
                'Ingredients are listed from the largest amount to the smallest. Sugar comes before the apple juice, so the drink has more sugar than juice.'),
            self::trueFalse('H6N-Ib-2', 'fernandez', 'medium', 'If sugar is listed first in the ingredients list, the food contains more sugar than any other ingredient.', true,
                'Ingredients are listed in order of amount, so the first ingredient is the one the food contains the most of.', shared: true),
            self::identification('H6N-Ib-2', 'mendoza', 'medium', 'What date on a food label tells you until when the product is best or safe to eat?',
                ['Expiration date', 'Expiry date', 'Best before date', 'Best before', 'Use by date'], ['Serving size', 'Net weight', 'Manufacturing date'],
                'Always check the expiration or best-before date before buying or eating packaged food.', reviewed: false),
            self::choice('H6N-IIa-1', 'santos', 'hard', 'A child who is pale, gets tired easily and has trouble concentrating may lack which mineral?',
                ['Iron', 'Iodine', 'Sodium', 'Fluoride'],
                'These are common signs of iron-deficiency anemia. Liver, lean meat, monggo and dark green leafy vegetables help prevent it.', shared: true),
            self::choice('H6N-IIa-1', 'mendoza', 'medium', 'Night blindness is a sign of a lack of which vitamin?',
                ['Vitamin A', 'Vitamin B1', 'Vitamin C', 'Vitamin D'],
                'A lack of vitamin A can cause night blindness. Eat yellow and orange vegetables like carrots and kalabasa, and green leafy vegetables.', shared: true),
            self::choice('H6N-IIa-1', 'fernandez', 'hard', 'What is it called when a child is too short for his or her age because of long-term undernutrition?',
                ['Stunting', 'Wasting', 'Obesity', 'Goiter'],
                'Stunting is low height for age caused by long-term undernutrition. Wasting is low weight for height.'),
            self::trueFalse('H6N-IIa-1', 'mendoza', 'medium', 'Overweight and obesity are also forms of malnutrition.', true,
                'Malnutrition means getting too little or too much of what the body needs, so it includes both undernutrition and overweight.'),
            self::identification('H6N-IIa-1', 'fernandez', 'hard', 'What disease, caused by a lack of vitamin C, makes the gums swell and bleed?',
                ['Scurvy'], ['Anemia', 'Goiter', 'Beriberi', 'Scurby'],
                'Scurvy is caused by a lack of vitamin C. Citrus fruits, bayabas and papaya help prevent it.', shared: true),
            self::choice('H6N-IIIa-1', 'santos', 'easy', 'Which of these is one of the four steps to food safety?',
                ['Chill leftovers promptly', 'Leave cooked food out overnight', 'Use one cutting board for raw meat and salad', 'Taste food to check if it has spoiled'],
                'The four steps are clean, separate, cook and chill. Refrigerate leftovers within two hours.', reviewed: false),
            self::trueFalse('H6N-IIIa-1', 'santos', 'easy', 'Raw chicken should be kept away from fruits and vegetables that will be eaten raw.', true,
                'Keeping raw meat apart from ready-to-eat food prevents cross-contamination.', reviewed: false),
            self::choice('H6N-Ia-1', 'mendoza', 'medium', 'Our skin makes vitamin D when it is exposed to what?',
                ['Sunlight', 'Cold water', 'Loud music', 'Darkness'],
                'The skin makes vitamin D in sunlight. Vitamin D helps the body use calcium to build strong bones.', bank: false),
            self::choice('H6N-Ib-2', 'santos', 'medium', 'Per serving, Brand A crackers have 250 mg of sodium and Brand B crackers have 90 mg. Which is better for a healthy heart?',
                ['Brand B, because it has less sodium', 'Brand A, because it has more sodium', 'Both are the same', 'Neither has sodium'],
                'Less sodium is better for blood pressure and the heart. Compare labels and choose the lower-sodium product.', bank: false),
            self::trueFalse('H6N-Ib-2', 'fernandez', 'easy', 'The net weight on a package tells how much food is inside, without the package.', true,
                'Net weight is the weight of the food itself, not including the container.', bank: false),
            self::choice('H6N-IIa-1', 'mendoza', 'easy', 'Which habit helps prevent obesity?',
                ['Being active for at least 60 minutes a day', 'Drinking soft drinks with every meal', 'Watching TV for many hours', 'Skipping breakfast and eating a big dinner'],
                'Regular physical activity and balanced meals keep energy in and energy out in balance.', bank: false),
            self::trueFalse('H6N-IIa-1', 'fernandez', 'medium', 'Goiter, a swelling in the neck, can be prevented by using iodized salt.', true,
                'Iodized salt gives the body the iodine the thyroid gland needs, which prevents goiter.', bank: false),
            self::identification('H6N-IIa-1', 'santos', 'medium', 'What disorder, caused by a lack of iron, makes a person pale and weak?',
                ['Anemia', 'Iron-deficiency anemia', 'Iron deficiency anemia', 'Anaemia'], ['Scurvy', 'Goiter', 'Rickets'],
                'Iron-deficiency anemia makes a person pale and tired because the blood carries less oxygen.', bank: false),
        ];
    }

    /**
     * Grade 5 Science questions.
     *
     * @return list<array<string, mixed>>
     */
    private static function scienceFive(): array
    {
        return [
            self::choice('S5LT-Ie-1', 'santos', 'easy', 'Which part of the carrot plant do we eat?',
                ['Root', 'Leaf', 'Flower', 'Seed'],
                'The carrot we eat is the root of the plant, where it stores food.', shared: true),
            self::choice('S5LT-Ie-1', 'villanueva', 'medium', 'Lettuce, pechay and malunggay are vegetables. Which plant part do they give us?',
                ['Leaves', 'Roots', 'Seeds', 'Flowers'],
                'Leafy vegetables like lettuce, pechay and malunggay give us the leaves of the plant.', shared: true),
            self::trueFalse('S5LT-Ie-1', 'villanueva', 'easy', 'Plants need sunlight, water, air and nutrients from the soil to grow.', true,
                'Plants use sunlight, water and carbon dioxide from the air to make food, and they take nutrients from the soil.'),
            self::identification('S5LT-Ie-1', 'santos', 'hard', 'Tomato, eggplant and okra grow from the flower of the plant and contain seeds. What plant part are they?',
                ['Fruit', 'Fruits'], ['Vegetable', 'Leaf', 'Stem'],
                'Tomato, eggplant and okra are fruits because they grow from the flower and hold the seeds, even if we cook them as vegetables.'),
            self::choice('S5LT-IIa-1', 'villanueva', 'easy', 'Where does digestion begin?',
                ['Mouth', 'Stomach', 'Small intestine', 'Large intestine'],
                'Digestion starts in the mouth, where the teeth break food into small pieces and saliva starts to digest starch.', shared: true),
            self::choice('S5LT-IIa-1', 'santos', 'medium', 'In which organ are most nutrients absorbed into the blood?',
                ['Small intestine', 'Stomach', 'Esophagus', 'Large intestine'],
                'Most digested nutrients pass into the blood through the walls of the small intestine.', shared: true),
            self::trueFalse('S5LT-IIa-1', 'santos', 'medium', 'The large intestine absorbs water from undigested food.', true,
                'The large intestine absorbs water from the food that was not digested and forms the waste the body removes.'),
            self::identification('S5LT-IIa-1', 'villanueva', 'medium', 'What is the tube that carries food from the mouth to the stomach?',
                ['Esophagus', 'Oesophagus', 'Food pipe', 'Gullet'], ['Trachea', 'Windpipe', 'Small intestine'],
                'The esophagus, or food pipe, squeezes food down from the mouth to the stomach.'),
            self::choice('S5MT-IIIb-2', 'villanueva', 'medium', 'Which tiny living things cause food to spoil?',
                ['Bacteria and molds', 'Ants and flies only', 'Vitamins and minerals', 'Salt and sugar'],
                'Microorganisms such as bacteria, molds and yeasts feed on food and make it spoil.', shared: true),
            self::choice('S5MT-IIIb-2', 'santos', 'medium', 'Which method preserves fish by removing the water from it?',
                ['Drying, as in making tuyo or daing', 'Boiling it in soup', 'Keeping it in a warm room', 'Wrapping it in newspaper'],
                'Drying removes the water that bacteria and molds need to grow, so dried fish like tuyo and daing last longer.', shared: true),
            self::choice('S5LT-Ie-1', 'villanueva', 'medium', 'Which of these foods comes from the seeds of a plant?',
                ['Monggo', 'Kangkong', 'Carrot', 'Ampalaya leaves'],
                'Monggo, corn, rice and peanuts are seeds that store food for a new plant.', bank: false),
            self::trueFalse('S5LT-Ie-1', 'santos', 'hard', 'Ginger and potatoes are underground stems, not roots.', true,
                'Ginger is a rhizome and a potato is a tuber; both are underground stems that store food.', bank: false),
            self::choice('S5LT-IIa-1', 'santos', 'medium', 'Which organ makes bile, a juice that helps digest fats?',
                ['Liver', 'Heart', 'Lungs', 'Kidneys'],
                'The liver makes bile, which is stored in the gallbladder and helps break down fats in the small intestine.', bank: false),
            self::trueFalse('S5LT-IIa-1', 'villanueva', 'easy', 'Chewing food well makes digestion easier.', true,
                'Chewing breaks food into small pieces and mixes it with saliva, so the stomach and intestines can digest it more easily.', bank: false),
        ];
    }

    /**
     * Grade 6 Science questions.
     *
     * @return list<array<string, mixed>>
     */
    private static function scienceSix(): array
    {
        return [
            self::choice('S6LT-If-1', 'santos', 'easy', 'What is the main source of energy for almost every food chain?',
                ['The sun', 'The soil', 'The rain', 'The moon'],
                'Plants capture energy from the sun to make food, and that energy passes along the food chain.', shared: true),
            self::choice('S6LT-If-1', 'villanueva', 'medium', 'In the food chain grass → grasshopper → frog → snake, which organism is the producer?',
                ['Grass', 'Grasshopper', 'Frog', 'Snake'],
                'Producers make their own food from sunlight. In this food chain, the grass is the producer.', shared: true),
            self::trueFalse('S6LT-If-1', 'villanueva', 'medium', 'Decomposers such as bacteria and fungi break down dead plants and animals and return nutrients to the soil.', true,
                'Decomposers recycle nutrients so producers can use them again.'),
            self::identification('S6LT-If-1', 'santos', 'medium', 'What do you call an animal that eats both plants and animals?',
                ['Omnivore', 'Omnivores'], ['Herbivore', 'Carnivore', 'Decomposer'],
                'Omnivores, like humans, eat both plants and animals.'),
            self::choice('S6LT-IIa-1', 'santos', 'hard', 'Which body system carries digested nutrients from the small intestine to all parts of the body?',
                ['Circulatory system', 'Respiratory system', 'Skeletal system', 'Nervous system'],
                'Nutrients enter the blood in the small intestine, and the circulatory system carries them to every cell.', shared: true),
            self::choice('S6LT-IIa-1', 'villanueva', 'medium', 'Which body system brings oxygen into the body so cells can release energy from food?',
                ['Respiratory system', 'Digestive system', 'Excretory system', 'Muscular system'],
                'The respiratory system takes in oxygen, which cells need to release the energy stored in food.', shared: true),
            self::trueFalse('S6LT-IIa-1', 'santos', 'hard', 'Tiny finger-like villi in the small intestine help absorb nutrients into the blood.', true,
                'Villi give the small intestine a huge surface area for absorbing nutrients.'),
            self::choice('S6LT-IIg-3', 'villanueva', 'medium', 'What can you make from fruit and vegetable peelings to feed the soil of a school garden?',
                ['Compost', 'Plastic mulch', 'Pesticide', 'Charcoal'],
                'Composting turns food scraps and dry leaves into natural fertilizer that adds nutrients to the soil.', shared: true),
            self::choice('S6LT-IIg-3', 'santos', 'easy', 'Which vegetable grows quickly and is often planted in a Gulayan sa Paaralan?',
                ['Pechay', 'Coconut', 'Mango', 'Narra'],
                'Pechay can be harvested about a month after planting, which makes it a favorite in school gardens.', shared: true),
            self::identification('S6LT-IIg-3', 'villanueva', 'medium', 'What is the DepEd program in which schools grow vegetables to support school feeding and nutrition?',
                ['Gulayan sa Paaralan', 'Gulayan sa Paaralan Program', 'GPP'], ['Brigada Eskwela', 'Oplan Kalusugan', 'Gulayan'],
                'The Gulayan sa Paaralan Program encourages schools to grow vegetables for school feeding and nutrition education.', reviewed: false),
            self::choice('S6LT-If-1', 'villanueva', 'medium', 'What do many connected food chains in one ecosystem form?',
                ['Food web', 'Food pyramid', 'Food label', 'Food group'],
                'A food web shows how the food chains of an ecosystem connect and overlap.', bank: false),
            self::trueFalse('S6LT-IIa-1', 'villanueva', 'easy', 'The heart pumps blood that carries nutrients and oxygen to the cells of the body.', true,
                'The heart is the pump of the circulatory system.', bank: false),
            self::trueFalse('S6LT-IIg-3', 'santos', 'medium', 'Most vegetable plants need several hours of direct sunlight every day to grow well.', true,
                'Vegetables need plenty of sunlight to make food; choose a sunny spot for the garden.', bank: false),
        ];
    }

    /**
     * Grade 5 EPP – Home Economics questions.
     *
     * @return list<array<string, mixed>>
     */
    private static function homeEconomicsFive(): array
    {
        return [
            self::choice('EPP5HE-Ia-1', 'bautista', 'easy', 'What should you do first before preparing food?',
                ['Wash your hands with soap and water', 'Taste the ingredients', 'Turn on the stove', 'Wipe your hands on your shirt'],
                'Washing your hands with soap and water for at least 20 seconds removes germs before you touch food.', shared: true),
            self::choice('EPP5HE-Ia-1', 'bautista', 'medium', 'How should you carry a knife in the kitchen?',
                ['With the tip pointing down, close to your side', 'With the blade pointing up', 'By tossing it to a classmate', 'In your pocket'],
                'Carry a knife with the tip pointing down and close to your side so it cannot hurt anyone.', shared: true),
            self::trueFalse('EPP5HE-Ia-1', 'bautista', 'easy', 'Spills on the kitchen floor should be wiped up right away to prevent slipping.', true,
                'Wet floors cause falls, so wipe spills immediately.'),
            self::choice('EPP5HE-IIb-3', 'bautista', 'easy', 'Why is breakfast called the most important meal of the day?',
                ['It gives energy and helps you focus in class', 'It makes you sleepy in school', 'It replaces lunch and dinner', 'It is always the sweetest meal'],
                'After a night without food, breakfast refuels the body and brain for learning and play.', shared: true),
            self::choice('EPP5HE-IIb-3', 'bautista', 'medium', 'Which breakfast has Go, Grow and Glow foods?',
                ['Pandesal, a boiled egg and a slice of papaya', 'Two cups of coffee', 'A bowl of sugary cereal only', 'Chocolate cake and soda'],
                'Pandesal (Go), egg (Grow) and papaya (Glow) make a balanced breakfast.'),
            self::identification('EPP5HE-IIb-3', 'bautista', 'medium', 'Champorado is a Filipino breakfast made from rice that gives us energy. What food group is it?',
                ['Go food', 'Go foods', 'Go'], ['Grow food', 'Glow food', 'Grow'],
                'Champorado is made from glutinous rice, a Go food that gives energy. Add milk for a Grow food.'),
            self::choice('EPP5HE-IId-4', 'bautista', 'medium', 'Which baon is the healthiest and most balanced?',
                ['Rice, chicken adobo, steamed vegetables and a banana', 'Instant noodles and a soft drink', 'Chips and a chocolate bar', 'White bread with sugar spread'],
                'A balanced baon has Go, Grow and Glow foods: rice, chicken, vegetables and fruit.', shared: true),
            self::trueFalse('EPP5HE-IId-4', 'bautista', 'medium', 'Dishes cooked with gata (coconut milk) spoil quickly in the heat, so they are not ideal for baon on a hot day.', true,
                'Coconut milk dishes spoil quickly at room temperature. Choose drier dishes for baon on hot days.'),
            self::trueFalse('EPP5HE-Ia-1', 'bautista', 'medium', 'Pot handles should point outward, away from the stove, so they are easy to grab.', false,
                'Turn pot handles inward so no one bumps them and spills hot food.', bank: false),
            self::choice('EPP5HE-IId-4', 'bautista', 'easy', 'What is the best drink to pack with your baon?',
                ['Water in a clean, reusable bottle', 'A can of soft drink', 'Sweetened powdered juice', 'Iced coffee'],
                'Water keeps you hydrated without added sugar, and a reusable bottle reduces waste.', bank: false),
        ];
    }

    /**
     * Grade 6 EPP – Home Economics questions.
     *
     * @return list<array<string, mixed>>
     */
    private static function homeEconomicsSix(): array
    {
        return [
            self::choice('EPP6HE-Ia-1', 'bautista', 'medium', 'Why is it wise to use fruits and vegetables that are in season when planning family meals?',
                ['They are usually cheaper and fresher', 'They have no vitamins', 'They are always imported', 'They take longer to cook'],
                'Foods in season are plentiful, so they are usually cheaper, fresher and tastier.', shared: true),
            self::choice('EPP6HE-Ia-1', 'fernandez', 'medium', 'Which meal follows the Pinggang Pinoy guide?',
                ['Rice, grilled fish, ginisang gulay and a slice of watermelon', 'Fried rice, fried chicken and French fries', 'Spaghetti, hotdog and soft drink', 'Bread, butter and jam'],
                'This meal has a Go food, a Grow food, plenty of vegetables and a fruit, served with water.', shared: true),
            self::trueFalse('EPP6HE-Ia-1', 'bautista', 'easy', 'A meal plan helps a family save money and eat a variety of healthy food.', true,
                'Planning meals avoids impulse buying and makes sure the family eats from all food groups.'),
            self::choice('EPP6HE-IIb-2', 'bautista', 'medium', 'Which is a sign that a fish is fresh?',
                ['Bright, clear eyes and red gills', 'Cloudy, sunken eyes', 'A strong, sour smell', 'Soft flesh that stays dented when pressed'],
                'Fresh fish has bright, clear eyes, red gills, firm flesh and a mild sea smell.', shared: true),
            self::choice('EPP6HE-IIb-2', 'bautista', 'easy', 'Which canned good should you NOT buy?',
                ['A can that is bulging or badly dented', 'A can with a clear label', 'A can with a far expiration date', 'A can that is clean and sealed'],
                'Bulging or badly dented cans may contain harmful bacteria. Leave them on the shelf.'),
            self::trueFalse('EPP6HE-IIb-2', 'fernandez', 'medium', 'Making a market list before going to the palengke helps you avoid buying things you do not need.', true,
                'A market list keeps shopping within the budget and the meal plan.'),
            self::choice('EPP6HE-IIe-4', 'bautista', 'medium', 'Which cooking method uses the least oil?',
                ['Steaming', 'Deep-frying', 'Pan-frying in lots of oil', 'Making tempura'],
                'Steaming cooks food with hot water vapor and needs no oil at all.', shared: true),
            self::identification('EPP6HE-IIe-4', 'fernandez', 'medium', 'Ginisang monggo is made from mung beans. What food group are mung beans?',
                ['Grow food', 'Grow foods', 'Grow'], ['Go food', 'Glow food', 'Go'],
                'Mung beans are rich in protein, so they are a Grow food.', shared: true, reviewed: false),
            self::identification('EPP6HE-Ia-1', 'fernandez', 'medium', 'What do you call a written plan of the dishes a family will serve for the week?',
                ['Menu', 'Meal plan', 'Weekly menu', 'Menu plan'], ['Recipe', 'Market list', 'Budget'],
                'A weekly menu, or meal plan, lists the dishes for each meal of the week.', bank: false),
            self::choice('EPP6HE-IIb-2', 'bautista', 'medium', 'Why should you buy cold and frozen foods last when shopping?',
                ['So they stay cold until you get home', 'Because they are the cheapest', 'Because they are the heaviest', 'So the store can restock them'],
                'Buying cold foods last keeps them out of the temperature danger zone for a shorter time.', bank: false),
            self::trueFalse('EPP6HE-IIe-4', 'fernandez', 'easy', 'Leftovers should be covered and refrigerated within two hours after cooking.', true,
                'Bacteria grow quickly at room temperature, so refrigerate leftovers within two hours.', bank: false),
        ];
    }

    /**
     * Grade 5 and Grade 6 Physical Education questions.
     *
     * @return list<array<string, mixed>>
     */
    private static function physicalEducation(): array
    {
        return [
            self::choice('PE5PF-Ib-1', 'mendoza', 'easy', 'Where does our body get the energy it uses when we run and play?',
                ['From the food we eat', 'From the clothes we wear', 'From the shoes we use', 'From watching TV'],
                'Food gives the body energy, measured in calories, that we use to grow, think and move.', shared: true),
            self::choice('PE5PF-Ib-1', 'mendoza', 'hard', 'What happens when a person regularly eats more energy than the body uses?',
                ['The body stores the extra energy as fat', 'The body loses weight', 'Nothing changes in the body', 'The body grows taller right away'],
                'Extra energy that the body does not use is stored as fat, which can lead to overweight over time.', shared: true),
            self::trueFalse('PE5PF-Ib-1', 'mendoza', 'easy', 'Playing outdoor games like patintero uses energy from food.', true,
                'Every movement uses energy, and active games use a lot of it.'),
            self::choice('PE5PF-IIa-2', 'mendoza', 'easy', 'How many minutes of moderate to vigorous physical activity should children get every day?',
                ['At least 60 minutes', '5 minutes', '10 minutes', 'Only on weekends'],
                'Children and adolescents should do at least 60 minutes of moderate to vigorous physical activity daily.', shared: true),
            self::choice('PE5PF-IIa-2', 'mendoza', 'medium', 'Which of these is a vigorous activity?',
                ['Running in a game of patintero', 'Sitting while reading', 'Watching a movie', 'Lying down to rest'],
                'Vigorous activities like running make you breathe hard and your heart beat fast.'),
            self::identification('PE5PF-IIa-2', 'mendoza', 'medium', 'What do you call the light exercises done before a game to prepare the muscles and prevent injury?',
                ['Warm-up', 'Warm up', 'Warmup', 'Warm-up exercises', 'Warming up'], ['Cool-down', 'Cool down', 'Rest'],
                'A warm-up slowly raises the heart rate and loosens the muscles before vigorous activity.'),
            self::choice('PE6PF-Ib-1', 'mendoza', 'medium', 'Which snack gives lasting energy before a sports practice?',
                ['A banana and a glass of water', 'A can of soft drink', 'A bag of chips', 'A candy bar'],
                'A banana gives carbohydrates and potassium for energy, and water keeps you hydrated.', shared: true),
            self::choice('PE6PF-Ib-1', 'mendoza', 'medium', 'When should you drink water during sports?',
                ['Before, during and after the activity', 'Only when you feel dizzy', 'Only after the game ends', 'Never, because it causes cramps'],
                'Drink water before, during and after activity to replace the water lost in sweat.', shared: true),
            self::trueFalse('PE6PF-Ib-1', 'mendoza', 'medium', 'The sit-and-reach test measures flexibility.', true,
                'The sit-and-reach test checks the flexibility of the lower back and the back of the legs.'),
            self::choice('PE6PF-IIa-2', 'mendoza', 'medium', 'Which activity mainly builds muscular strength?',
                ['Push-ups', 'Stretching', 'Walking slowly', 'Deep breathing'],
                'Push-ups make the arm, chest and shoulder muscles work against the body\'s weight, which builds strength.', shared: true),
            self::choice('PE6PF-IIa-2', 'mendoza', 'easy', 'What should you do after exercising to help your heart rate slowly return to normal?',
                ['Cool down with light walking and stretching', 'Lie down right away and sleep', 'Drink a soft drink', 'Start a new game immediately'],
                'A cool-down lets the heart rate and breathing return to normal gradually.'),
            self::trueFalse('PE6PF-IIa-2', 'mendoza', 'medium', 'Children should limit sedentary screen time and stay active throughout the day.', true,
                'Long periods of sitting are unhealthy; break up screen time with active play.', reviewed: false),
            self::trueFalse('PE5PF-Ib-1', 'mendoza', 'medium', 'Energy balance means the energy from food matches the energy we use for growth and activity.', true,
                'When energy in equals energy out, the body stays at a healthy weight while it grows.', bank: false),
            self::choice('PE5PF-IIa-2', 'mendoza', 'medium', 'Which traditional Filipino game involves knocking down a tin can with a slipper?',
                ['Tumbang preso', 'Patintero', 'Piko', 'Sipa'],
                'In tumbang preso, players throw their slippers to knock down a tin can guarded by the "it".', bank: false),
            self::identification('PE6PF-Ib-1', 'mendoza', 'medium', 'What is the ability of the heart and lungs to keep the body moving for a long time, as in jogging, called?',
                ['Cardiovascular endurance', 'Cardiorespiratory endurance', 'Cardio endurance', 'Endurance'], ['Flexibility', 'Strength', 'Speed'],
                'Cardiovascular endurance is built by activities like jogging, swimming and dancing.', bank: false),
            self::choice('PE6PF-IIa-2', 'mendoza', 'medium', 'Which is a health-related component of physical fitness?',
                ['Flexibility', 'Handwriting', 'Singing voice', 'Shoe size'],
                'The health-related components are cardiovascular endurance, muscular strength, muscular endurance, flexibility and body composition.', bank: false),
        ];
    }

    /**
     * Build a multiple choice question; the first choice is the correct one and the second the most tempting wrong one.
     *
     * @param  list<string>  $choices
     * @return array<string, mixed>
     */
    private static function choice(
        string $module,
        string $owner,
        string $difficulty,
        string $prompt,
        array $choices,
        string $explanation,
        bool $shared = false,
        bool $reviewed = true,
        bool $bank = true,
    ): array {
        return self::question($module, $owner, QuestionType::MultipleChoice, $difficulty, $prompt, $explanation, $shared, $reviewed, $bank, choices: $choices);
    }

    /**
     * Build a true or false question about a statement.
     *
     * @return array<string, mixed>
     */
    private static function trueFalse(
        string $module,
        string $owner,
        string $difficulty,
        string $statement,
        bool $isTrue,
        string $explanation,
        bool $shared = false,
        bool $reviewed = true,
        bool $bank = true,
    ): array {
        return self::question($module, $owner, QuestionType::TrueFalse, $difficulty, $statement, $explanation, $shared, $reviewed, $bank, trueStatement: $isTrue);
    }

    /**
     * Build an identification question with its accepted answers and typical wrong answers.
     *
     * @param  list<string>  $accepted
     * @param  list<string>  $wrong
     * @return array<string, mixed>
     */
    private static function identification(
        string $module,
        string $owner,
        string $difficulty,
        string $prompt,
        array $accepted,
        array $wrong,
        string $explanation,
        bool $shared = false,
        bool $reviewed = true,
        bool $bank = true,
    ): array {
        return self::question($module, $owner, QuestionType::Identification, $difficulty, $prompt, $explanation, $shared, $reviewed, $bank, accepted: $accepted, wrong: $wrong);
    }

    /**
     * Build a question entry.
     *
     * @param  list<string>|null  $choices
     * @param  list<string>  $accepted
     * @param  list<string>  $wrong
     * @return array<string, mixed>
     */
    private static function question(
        string $module,
        string $owner,
        QuestionType $type,
        string $difficulty,
        string $prompt,
        string $explanation,
        bool $shared,
        bool $reviewed,
        bool $bank,
        ?array $choices = null,
        array $accepted = [],
        array $wrong = [],
        ?bool $trueStatement = null,
    ): array {
        return [
            'module' => $module,
            'owner' => $owner,
            'bank' => $bank,
            'shared' => $bank && $shared,
            'reviewed' => $reviewed,
            'type' => $type,
            'difficulty' => $difficulty,
            'prompt' => $prompt,
            'choices' => $choices,
            'accepted' => $accepted,
            'wrong' => $wrong,
            'true_statement' => $trueStatement,
            'explanation' => $explanation,
        ];
    }
}
