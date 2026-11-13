<?php

namespace Database\Seeders\Support\Catalog;

use InvalidArgumentException;

/**
 * The NutriMind game content of the demo school: learning modules, activities, missions and rewards.
 *
 * Modules are identified by their competency code, which is unique in the demo content.
 */
final class ContentCatalog
{
    /**
     * The points each activity type is worth.
     *
     * @var array<string, int>
     */
    public const ACTIVITY_POINTS = ['lesson' => 10, 'video' => 10, 'reading' => 15, 'practice' => 20, 'mini_game' => 25];

    /**
     * Get the learning modules with their activities, in subject, grade and sort order.
     *
     * @return list<array{code: string, subject: string, grade: int, quarter: int, sort: int, title: string, world: string, topic: string, competency: string, description: string, published: bool, activities: list<array{title: string, type: string, minutes: int, description: string}>}>
     */
    public static function modules(): array
    {
        return [
            self::module('H5N-Ia-1', 'HEALTH', 5, 1, 1, 'Go, Grow and Glow Foods', 'Veggie Valley', 'Go, Grow and Glow foods',
                'Classifies foods into the Go, Grow and Glow food groups and explains how each group helps the body.',
                'Sort the Veggie Valley harvest into Go, Grow and Glow baskets and discover how each food group gives energy, builds the body and keeps it healthy.', [
                    ['Meet the Three Food Groups', 'lesson', 10, 'Find out why rice gives energy, fish helps us grow and vegetables help us glow.'],
                    ['Farmer Lito\'s Harvest Tour', 'video', 6, 'An animated tour of the valley farms showing where Go, Grow and Glow foods come from.'],
                    ['Basket Sort Rush', 'mini_game', 12, 'Race the clock to drop every crop into the right food group basket.'],
                    ['Bea\'s Tired Morning', 'reading', 12, 'A short story about Bea, who learns why she feels tired when she skips Go foods.'],
                    ['Name That Food Group', 'practice', 10, 'Twenty quick picture questions on Go, Grow and Glow foods.'],
                ]),
            self::module('H5N-Ic-2', 'HEALTH', 5, 1, 2, 'Pinggang Pinoy Plate Builder', 'Grain Grove', 'the Pinggang Pinoy plate',
                'Uses the Pinggang Pinoy food guide to build a balanced meal with the right proportion of each food group.',
                'Help the cooks of Grain Grove fill their plates the Pinggang Pinoy way: half fruits and vegetables, about a third rice or other Go foods, and the rest Grow foods.', [
                    ['What Is Pinggang Pinoy?', 'lesson', 10, 'Learn the parts of the Pinggang Pinoy plate and why water belongs beside it.'],
                    ['Plate Builder', 'mini_game', 15, 'Drag foods onto the plate until each section has the right portion.'],
                    ['Fix the Unbalanced Plate', 'practice', 12, 'Spot what is missing, or too much, on ten sample meals.'],
                    ['A Day of Balanced Meals', 'reading', 10, 'Follow Paolo\'s family through breakfast, lunch and dinner planned with Pinggang Pinoy.'],
                ]),
            self::module('H5N-IIa-1', 'HEALTH', 5, 2, 3, 'Smart Snacking', 'Snack Shack', 'healthy snacks',
                'Chooses healthy snacks and explains why snacks high in sugar, salt and fat should be limited.',
                'Run the Snack Shack and serve snacks that are tasty and healthy, from boiled saba to fresh fruit.', [
                    ['Snacks That Help, Snacks That Harm', 'lesson', 10, 'Compare everyday snacks by their sugar, salt and fat.'],
                    ['Inside a Bag of Chips', 'video', 6, 'See how much salt and oil hide in common chichirya.'],
                    ['Snack Shack Rush', 'mini_game', 15, 'Serve the healthiest snack to each customer before time runs out.'],
                    ['Healthy Swap Challenge', 'practice', 12, 'Replace sugary and salty snacks with healthier local choices.'],
                    ['Lola\'s Merienda Recipes', 'reading', 12, 'Simple merienda ideas like nilagang mais, boiled kamote and banana shakes without added sugar.'],
                ]),
            self::module('H5N-IIc-3', 'HEALTH', 5, 2, 4, 'Hydration Heroes', 'Hydration Harbor', 'drinking enough water',
                'Explains the importance of drinking enough water every day and identifies healthy drinks.',
                'Keep the Hydration Harbor boats afloat by choosing water and other healthy drinks and spotting the signs of dehydration.', [
                    ['Why Our Bodies Need Water', 'lesson', 8, 'Learn what water does in the body and how much children need each day.'],
                    ['The Journey of a Glass of Water', 'video', 5, 'Follow a glass of water from the mouth to every cell of the body.'],
                    ['Refill the Harbor', 'mini_game', 12, 'Keep the harbor full by choosing healthy drinks for the sailors.'],
                    ['Spot the Sugary Drink', 'practice', 10, 'Find the drinks with the most added sugar.'],
                ]),
            self::module('H6N-Ia-1', 'HEALTH', 6, 1, 1, 'Vitamin and Mineral Quest', 'Fruit Forest', 'vitamins and minerals',
                'Identifies the food sources and functions of key vitamins and minerals such as vitamins A, C and D, calcium, iron and iodine.',
                'Collect the vitamins and minerals hidden in the Fruit Forest and learn which foods give each one and how it keeps the body strong.', [
                    ['Vitamins: Small but Mighty', 'lesson', 12, 'Meet vitamins A, B, C and D and the foods that give them.'],
                    ['Minerals That Build and Protect', 'lesson', 12, 'Learn how calcium, iron and iodine keep bones, blood and the thyroid healthy.'],
                    ['Forest Vitamin Hunt', 'mini_game', 15, 'Collect fruits and vegetables to power up each vitamin meter.'],
                    ['The Malunggay Story', 'reading', 10, 'Why the humble malunggay leaf is one of the most nutritious vegetables in our backyard.'],
                    ['Match the Nutrient to the Food', 'practice', 12, 'Pair each vitamin and mineral with its best food sources.'],
                ]),
            self::module('H6N-Ib-2', 'HEALTH', 6, 1, 2, 'Label Detective', 'Label Lab', 'reading food labels',
                'Reads and interprets the Nutrition Facts and the ingredients list of packaged foods to make healthier choices.',
                'Investigate packaged foods in the Label Lab: compare serving sizes, sugar and sodium, and decode ingredients lists.', [
                    ['Parts of a Nutrition Facts Label', 'lesson', 12, 'Serving size, energy, fat, sodium and sugars, one part at a time.'],
                    ['Serving Size Secrets', 'video', 6, 'Why one small bag of chips can hold two or three servings.'],
                    ['Compare Two Labels', 'practice', 15, 'Choose the healthier product by comparing two labels side by side.'],
                    ['Label Detective Case Files', 'mini_game', 15, 'Solve cases by finding hidden sugar and salt in the ingredients list.'],
                ]),
            self::module('H6N-IIa-1', 'HEALTH', 6, 2, 3, 'Spot and Prevent Nutritional Disorders', 'Protein Port', 'nutritional disorders',
                'Describes common nutritional disorders, their signs and causes, and ways to prevent them.',
                'Help the doctors of Protein Port recognize undernutrition, overweight and vitamin or mineral deficiencies, and plan meals that prevent them.', [
                    ['When the Body Lacks Nutrients', 'lesson', 12, 'Undernutrition, overnutrition and micronutrient deficiencies explained.'],
                    ['Case Files of Protein Port', 'reading', 15, 'Read patient stories and identify the nutrient each one lacks.'],
                    ['Iron, Iodine and Vitamin A', 'video', 7, 'How anemia, goiter and night blindness happen and how to prevent them.'],
                    ['Clinic Rush', 'mini_game', 15, 'Match each patient with the meal plan that helps them recover.'],
                    ['Prevention Plan', 'practice', 12, 'Plan a day of meals that prevents common deficiencies.'],
                ]),
            self::module('H6N-IIIa-1', 'HEALTH', 6, 3, 4, 'Food Safety Squad', 'Clean Cuisine Castle', 'food safety',
                'Applies the four steps to food safety (clean, separate, cook and chill) at home and in school.',
                'Coming in the 3rd Quarter: join the Food Safety Squad and keep the castle kitchen free from germs.', [
                    ['Clean, Separate, Cook, Chill', 'lesson', 10, 'The four steps that keep food safe from the market to the table.'],
                    ['Germ Busters', 'mini_game', 15, 'Stop germs from spreading in a busy kitchen.'],
                    ['Safe or Unsafe?', 'practice', 10, 'Decide whether each kitchen habit keeps food safe.'],
                ], published: false),
            self::module('S5LT-Ie-1', 'SCIENCE', 5, 1, 1, 'Plants We Eat', 'Sprout Fields', 'the plant parts we eat',
                'Identifies the plant parts people eat and describes the conditions plants need to grow.',
                'Explore Sprout Fields to find out which part of each plant ends up on our plates, from carrot roots to malunggay leaves.', [
                    ['Roots, Stems, Leaves and More', 'lesson', 10, 'Sort common vegetables by the plant part we eat.'],
                    ['Harvest the Right Part', 'mini_game', 12, 'Pick the edible part of each plant before the season ends.'],
                    ['What Plants Need to Grow', 'reading', 10, 'Sunlight, water, air and healthy soil: what every plant needs.'],
                    ['Sort the Vegetables', 'practice', 10, 'Group vegetables into roots, stems, leaves, flowers, fruits and seeds.'],
                ]),
            self::module('S5LT-IIa-1', 'SCIENCE', 5, 2, 2, 'Journey Through the Digestive System', 'Tummy Tunnel', 'the digestive system',
                'Describes the organs of the digestive system and how they work together to digest food.',
                'Ride a grain of rice through the Tummy Tunnel, from the mouth to the large intestine, and see how the body breaks down food.', [
                    ['A Rice Grain\'s Journey', 'video', 7, 'An animated trip through every organ of the digestive system.'],
                    ['Organs of the Digestive System', 'lesson', 12, 'What the mouth, esophagus, stomach and intestines each do.'],
                    ['Tummy Tunnel Run', 'mini_game', 15, 'Guide the food through the right organs in the right order.'],
                    ['Label the Digestive System', 'practice', 12, 'Name each organ on the diagram.'],
                    ['Habits for a Happy Tummy', 'reading', 10, 'Chewing well, drinking water and eating fiber for healthy digestion.'],
                ]),
            self::module('S5MT-IIIb-2', 'SCIENCE', 5, 3, 3, 'Food Spoilage and Preservation', 'Pantry Peak', 'food spoilage and preservation',
                'Explains how microorganisms cause food spoilage and describes ways of preserving food.',
                'Guard the Pantry Peak storehouse: discover why food spoils and how drying, salting, pickling and chilling keep it safe longer.', [
                    ['Tiny Living Things in Our Food', 'lesson', 10, 'How bacteria and molds make food spoil.'],
                    ['Tuyo, Atchara and Other Ways to Preserve Food', 'reading', 12, 'Traditional Filipino ways of preserving food and why they work.'],
                    ['Fresh or Spoiled?', 'practice', 10, 'Use your senses and food labels to decide whether food is still safe.'],
                ]),
            self::module('S6LT-If-1', 'SCIENCE', 6, 1, 1, 'Food Chains and Food Webs', 'Ecosystem Island', 'food chains and food webs',
                'Explains how energy from food passes through producers, consumers and decomposers in food chains and food webs.',
                'Map the food webs of Ecosystem Island and follow the energy from the sun to plants, animals and decomposers.', [
                    ['Producers, Consumers and Decomposers', 'lesson', 12, 'The roles every living thing plays in an ecosystem.'],
                    ['Energy From the Sun', 'video', 6, 'How plants capture the sun\'s energy and pass it along the food chain.'],
                    ['Build the Food Web', 'mini_game', 15, 'Connect the island\'s plants and animals into a working food web.'],
                    ['Who Eats What?', 'practice', 10, 'Identify herbivores, carnivores and omnivores.'],
                ]),
            self::module('S6LT-IIa-1', 'SCIENCE', 6, 2, 2, 'Nutrients and Body Systems', 'Body Basecamp', 'how body systems use nutrients',
                'Explains how the digestive, circulatory and respiratory systems work together to deliver nutrients and oxygen to the body.',
                'At Body Basecamp, follow nutrients from the small intestine into the blood and see how oxygen and food energy reach every cell.', [
                    ['Teamwork Inside the Body', 'lesson', 12, 'How three body systems work together to keep cells alive.'],
                    ['From Plate to Cell', 'video', 7, 'Follow a nutrient from a spoonful of monggo to a muscle cell.'],
                    ['Blood Stream Delivery', 'mini_game', 15, 'Deliver nutrients and oxygen to the cells that need them.'],
                    ['Keeping Body Systems Healthy', 'reading', 12, 'Food, exercise and rest habits that keep body systems strong.'],
                ]),
            self::module('S6LT-IIg-3', 'SCIENCE', 6, 2, 3, 'Growing a School Vegetable Garden', 'Gulayan Gardens', 'school vegetable gardening',
                'Applies knowledge of soil, water, sunlight and compost to grow vegetables for a school or home garden.',
                'Plan, plant and care for the Gulayan Gardens, from making compost to harvesting pechay for the school feeding program.', [
                    ['Planning a Gulayan sa Paaralan', 'lesson', 12, 'Choose the site, the vegetables and the schedule of a school garden.'],
                    ['Compost or Not?', 'practice', 10, 'Decide which scraps belong in the compost pit.'],
                    ['Garden Keeper', 'mini_game', 15, 'Water, weed and protect your plots until harvest day.'],
                    ['From Seed to Harvest Journal', 'reading', 12, 'A Grade 6 class journal of their first pechay harvest.'],
                ]),
            self::module('EPP5HE-Ia-1', 'EPP-HE', 5, 1, 1, 'Kitchen Safety and Hygiene', 'Kitchen Kingdom', 'kitchen safety and hygiene',
                'Practices safety and hygiene in handling food, kitchen tools and equipment.',
                'Earn your apron in the Kitchen Kingdom by washing up the right way and handling knives, stoves and spills safely.', [
                    ['Clean Hands, Clean Kitchen', 'lesson', 10, 'Personal hygiene and cleanliness rules for young cooks.'],
                    ['Handwashing Step by Step', 'video', 5, 'Wash your hands the right way, for at least 20 seconds with soap.'],
                    ['Kitchen Hazard Hunt', 'mini_game', 12, 'Find and fix the dangers hiding in the royal kitchen.'],
                    ['Safe or Not Safe?', 'practice', 10, 'Judge everyday kitchen habits.'],
                ]),
            self::module('EPP5HE-IIb-3', 'EPP-HE', 5, 2, 2, 'Breakfast Basics', 'Breakfast Bay', 'a healthy breakfast',
                'Prepares a simple, nutritious breakfast and explains why breakfast is important.',
                'Wake up Breakfast Bay with simple, nutritious breakfasts that give energy for school, like champorado with milk or pandesal with egg.', [
                    ['Why Breakfast Matters', 'lesson', 8, 'How breakfast fuels the brain and body for school.'],
                    ['Breakfast Around the Philippines', 'reading', 10, 'Healthy breakfasts from Luzon, the Visayas and Mindanao.'],
                    ['Breakfast Bay Diner', 'mini_game', 15, 'Serve balanced breakfasts to the early customers.'],
                    ['Plan Tomorrow\'s Breakfast', 'practice', 10, 'Build a breakfast with Go, Grow and Glow foods.'],
                ]),
            self::module('EPP5HE-IId-4', 'EPP-HE', 5, 2, 3, 'Healthy Baon Packing', 'Lunchbox Lagoon', 'packing a healthy baon',
                'Plans and packs a healthy, safe and affordable baon using local foods.',
                'Pack lunchboxes for the Lunchbox Lagoon field trip that are balanced, budget-friendly and still safe to eat at lunchtime.', [
                    ['The Balanced Baon', 'lesson', 10, 'What a healthy baon looks like.'],
                    ['Lunchbox Packer', 'mini_game', 15, 'Pack balanced lunchboxes within the budget.'],
                    ['Baon on a Budget', 'practice', 12, 'Plan a week of baon for under ₱50 a day.'],
                    ['Keeping Baon Fresh and Safe', 'reading', 10, 'Containers, ice packs and dishes that do not spoil quickly.'],
                ]),
            self::module('EPP6HE-Ia-1', 'EPP-HE', 6, 1, 1, 'Meal Planning for the Family', 'Menu Meadows', 'family meal planning',
                'Plans nutritious, budget-friendly meals for the family using the Pinggang Pinoy guide.',
                'Plan a week of family meals in Menu Meadows that are balanced, affordable and use foods in season.', [
                    ['Planning Balanced Family Meals', 'lesson', 12, 'Steps in planning meals for the whole family.'],
                    ['Budget Menu Challenge', 'practice', 15, 'Plan three days of meals with a fixed budget.'],
                    ['Menu Meadows Planner', 'mini_game', 15, 'Fill the family calendar with balanced meals.'],
                    ['Seasonal Foods Calendar', 'reading', 10, 'Which fruits and vegetables are in season each month.'],
                ]),
            self::module('EPP6HE-IIb-2', 'EPP-HE', 6, 2, 2, 'Smart Food Shopping', 'Palengke Plaza', 'smart food shopping',
                'Selects fresh, safe and affordable ingredients at the market and checks the labels of packaged foods.',
                'Shop at Palengke Plaza with a market list and a budget, and learn how to spot fresh fish, vegetables and safe canned goods.', [
                    ['Making a Market List', 'lesson', 10, 'Plan your shopping from the week\'s menu.'],
                    ['Fresh Fish Checklist', 'video', 5, 'Bright eyes, red gills and firm flesh: how to choose fresh fish.'],
                    ['Palengke Budget Run', 'mini_game', 15, 'Buy everything on the list without going over the budget.'],
                    ['Choose the Freshest', 'practice', 10, 'Pick the freshest produce and the safest canned goods.'],
                ]),
            self::module('EPP6HE-IIe-4', 'EPP-HE', 6, 2, 3, 'Cooking Simple Healthy Dishes', 'Cooking Cove', 'healthy cooking',
                'Prepares simple, healthy dishes such as ginisang monggo and fresh lumpia following safe food-handling practices.',
                'Cook along in Cooking Cove: prepare ginisang monggo and fresh lumpia with less salt and oil, and serve them safely.', [
                    ['Ginisang Monggo Cook-Along', 'video', 8, 'Step-by-step cooking of a classic Filipino Grow food dish.'],
                    ['Healthy Cooking Methods', 'lesson', 10, 'Steaming, boiling, grilling and sautéing compared with deep-frying.'],
                    ['Recipe Remix: Less Salt, Less Oil', 'practice', 12, 'Make favorite recipes healthier.'],
                    ['Cooking Cove Challenge', 'mini_game', 15, 'Cook and plate three dishes before the guests arrive.'],
                    ['Serving and Storing Leftovers Safely', 'reading', 10, 'Cover, cool and refrigerate leftovers within two hours.'],
                ]),
            self::module('PE5PF-Ib-1', 'PE', 5, 1, 1, 'Energy In, Energy Out', 'Energy Arena', 'energy balance',
                'Explains energy balance: food gives the body the energy that physical activity uses.',
                'Power up the Energy Arena by matching the energy from food with fun activities that use it.', [
                    ['What Is Energy Balance?', 'lesson', 10, 'Energy in from food, energy out through growth and activity.'],
                    ['Energy Arena Relay', 'mini_game', 12, 'Fuel up and race through the arena\'s activity stations.'],
                    ['Active or Not Active?', 'practice', 8, 'Sort daily activities by how much energy they use.'],
                ]),
            self::module('PE5PF-IIa-2', 'PE', 5, 2, 2, 'Active Play Challenges', 'Sports Summit', 'active play',
                'Participates in at least 60 minutes of moderate to vigorous physical activity every day.',
                'Climb Sports Summit through traditional Filipino games like patintero and tumbang preso, and track 60 active minutes a day.', [
                    ['Patintero and Tumbang Preso Rules', 'video', 6, 'How to play two favorite Filipino street games safely.'],
                    ['Moderate and Vigorous Activities', 'lesson', 10, 'How to tell light, moderate and vigorous activities apart.'],
                    ['Summit Step Counter', 'mini_game', 12, 'Earn steps on the summit by logging real activity.'],
                    ['My Activity Log', 'practice', 10, 'Record and add up your active minutes for a week.'],
                ]),
            self::module('PE6PF-Ib-1', 'PE', 6, 1, 1, 'Fitness and Food Fuel', 'Power Up Plaza', 'fitness and food as fuel',
                'Relates food choices to energy for physical activity and assesses personal fitness.',
                'Fuel up at Power Up Plaza: learn which foods give lasting energy for sports, and test your fitness at fun stations.', [
                    ['Food as Fuel', 'lesson', 10, 'Which foods give quick energy and which give lasting energy.'],
                    ['Fitness Station Check', 'practice', 12, 'Learn what each physical fitness test measures.'],
                    ['Plaza Fitness Trials', 'mini_game', 15, 'Complete the plaza\'s strength, endurance and flexibility trials.'],
                    ['Hydration for Young Athletes', 'reading', 10, 'Drinking water before, during and after sports.'],
                ]),
            self::module('PE6PF-IIa-2', 'PE', 6, 2, 2, 'Active Lifestyle Plan', 'Trail of Champions', 'an active lifestyle',
                'Plans and participates in physical activities that keep energy balanced and the body fit.',
                'Plan your own weekly activity schedule along the Trail of Champions, mixing aerobic, strength and flexibility activities.', [
                    ['Components of Fitness', 'lesson', 12, 'Cardiovascular endurance, strength, flexibility and body composition.'],
                    ['Warm-up and Cool-down Routine', 'video', 6, 'A safe routine to start and end every workout.'],
                    ['Build a Weekly Activity Plan', 'practice', 12, 'Mix activities for every component of fitness.'],
                    ['Trail of Champions', 'mini_game', 15, 'Complete daily challenges on the trail to the champions\' camp.'],
                ]),
        ];
    }

    /**
     * Get the game missions in sort order.
     *
     * @return list<array{title: string, subject: string, grade: int|null, module: string|null, type: string, difficulty: string, points: int, description: string, objective: string, starts_on: string|null, ends_on: string|null, active: bool}>
     */
    public static function missions(): array
    {
        return [
            self::mission('Rescue the Veggie Valley Harvest', 'HEALTH', 5, 'H5N-Ia-1', 'main', 'easy', 100,
                'The Veggie Valley harvest festival is in trouble! Sort the crops into Go, Grow and Glow baskets to save the celebration.',
                'Complete every activity in Veggie Valley.'),
            self::mission('Build the Perfect Pinggang Pinoy', 'HEALTH', 5, 'H5N-Ic-2', 'main', 'medium', 120,
                'The Grain Grove cooks need help serving balanced meals to the villagers.',
                'Complete every activity in Grain Grove.'),
            self::mission('Open the Healthy Snack Shack', 'HEALTH', 5, 'H5N-IIa-1', 'main', 'medium', 120,
                'The Snack Shack is opening and only healthy snacks may be served. Can you run it?',
                'Complete every activity in the Snack Shack.'),
            self::mission('Refill Hydration Harbor', 'HEALTH', 5, 'H5N-IIc-3', 'main', 'easy', 100,
                'The harbor is drying up! Choose healthy drinks to refill it and keep the boats sailing.',
                'Complete every activity in Hydration Harbor.'),
            self::mission('Restore the Fruit Forest', 'HEALTH', 6, 'H6N-Ia-1', 'main', 'medium', 120,
                'A storm scattered the vitamins of the Fruit Forest. Collect them all to bring the forest back to life.',
                'Complete every activity in the Fruit Forest.'),
            self::mission('Crack the Label Code', 'HEALTH', 6, 'H6N-Ib-2', 'main', 'hard', 150,
                'Mysterious packages arrived at the Label Lab. Read their labels to find out which ones are healthy.',
                'Complete every activity in the Label Lab.'),
            self::mission('Protect Protein Port', 'HEALTH', 6, 'H6N-IIa-1', 'main', 'hard', 150,
                'Sailors at Protein Port are getting sick. Find the missing nutrients and plan meals that prevent disorders.',
                'Complete every activity in Protein Port.'),
            self::mission('Water Check-in', 'HEALTH', null, null, 'daily', 'easy', 30,
                'Keep your body hydrated today by drinking water instead of sugary drinks.',
                'Log at least six glasses of water in your game diary today.'),
            self::mission('Five-a-Day Week', 'HEALTH', null, null, 'weekly', 'medium', 80,
                'Eat a rainbow of fruits and vegetables every day this week.',
                'Log five servings of fruits and vegetables a day for seven days.'),
            self::mission('Sugar Sleuth Challenge', 'HEALTH', 6, null, 'challenge', 'hard', 200,
                'Become a sugar sleuth: read the labels of the drinks at home and in the canteen and rank them by sugar.',
                'Find and record the sugar content of ten packaged drinks.', '2026-10-12', '2026-11-06'),
            self::mission('Nutrition Month 2026 Challenge', 'HEALTH', null, null, 'challenge', 'medium', 150,
                'Celebrate Nutrition Month by trying a new vegetable dish with your family every week of July.',
                'Cook or try four new vegetable dishes and share a photo of each in the game.', '2026-07-01', '2026-07-31', active: false),
            self::mission('Grow the Garden of Plenty', 'SCIENCE', 5, 'S5LT-Ie-1', 'main', 'easy', 100,
                'Sprout Fields needs a farmer who knows which part of each plant we eat.',
                'Complete every activity in Sprout Fields.'),
            self::mission('Journey Through the Tummy Tunnel', 'SCIENCE', 5, 'S5LT-IIa-1', 'main', 'medium', 120,
                'Guide a grain of rice safely through every organ of the digestive system.',
                'Complete every activity in the Tummy Tunnel.'),
            self::mission('Spoilage Detective Week', 'SCIENCE', 5, 'S5MT-IIIb-2', 'weekly', 'medium', 80,
                'Observe how bread, fruit and rice change over a week and find out what makes them spoil.',
                'Record daily observations of three foods for one week.', '2026-12-01', '2026-12-11'),
            self::mission('Balance the Island Food Web', 'SCIENCE', 6, 'S6LT-If-1', 'main', 'medium', 120,
                'Ecosystem Island is out of balance. Rebuild its food web before the animals go hungry.',
                'Complete every activity on Ecosystem Island.'),
            self::mission('Plant the School Gulayan', 'SCIENCE', 6, 'S6LT-IIg-3', 'main', 'medium', 120,
                'The school feeding program needs fresh vegetables. Plan and grow the Gulayan Gardens.',
                'Complete every activity in the Gulayan Gardens.'),
            self::mission('Clean Up Kitchen Kingdom', 'EPP-HE', 5, 'EPP5HE-Ia-1', 'main', 'easy', 100,
                'The royal kitchen is a mess! Make it clean and safe before the royal feast.',
                'Complete every activity in the Kitchen Kingdom.'),
            self::mission('Pack the Perfect Baon', 'EPP-HE', 5, 'EPP5HE-IId-4', 'main', 'medium', 120,
                'The Lunchbox Lagoon field trip is tomorrow. Pack balanced, safe and affordable baon for everyone.',
                'Complete every activity in Lunchbox Lagoon.'),
            self::mission('Plan a Week of Family Meals', 'EPP-HE', 6, 'EPP6HE-Ia-1', 'main', 'hard', 150,
                'The families of Menu Meadows need a week of balanced meals within their budget.',
                'Complete every activity in Menu Meadows.'),
            self::mission('Healthy Baon Week Challenge', 'EPP-HE', null, null, 'challenge', 'medium', 150,
                'Celebrate Healthy Baon Week by packing a baon with Go, Grow and Glow foods and a bottle of water every school day.',
                'Pack and log a healthy baon on five school days.', '2026-11-09', '2026-11-20'),
            self::mission('Power Up the Energy Arena', 'PE', 5, 'PE5PF-Ib-1', 'main', 'easy', 100,
                'The Energy Arena lights went out. Balance energy in and energy out to power them up again.',
                'Complete every activity in the Energy Arena.'),
            self::mission('60-Minute Move', 'PE', null, null, 'daily', 'easy', 30,
                'Play, dance or do chores: keep your body moving today.',
                'Log at least 60 minutes of physical activity today.'),
            self::mission('Active Family Weekend', 'PE', 6, null, 'weekly', 'medium', 80,
                'Invite your family to be active together this weekend.',
                'Do two family activities, such as a walk or a game of patintero, and log them.'),
            self::mission('Holiday Healthy Plate Challenge', 'PE', null, null, 'challenge', 'hard', 200,
                'Stay active and eat balanced meals through the holiday parties.',
                'Log 60 active minutes and a balanced plate on eight days of the holidays.', '2026-12-14', '2026-12-23'),
            self::mission('Sports Fest Fitness Quest', 'PE', 6, 'PE6PF-IIa-2', 'challenge', 'hard', 200,
                'A training quest for the school sports fest. Still being prepared by the PE team.',
                'Complete the five-day sports fest training plan.', active: false),
        ];
    }

    /**
     * Get the rewards with the rule that awards them.
     *
     * Rules: activities (completed activities), points (total points), module (worlds fully completed), missions
     * (completed missions), quizzes_passed (different quizzes passed), perfect (perfect quiz scores), mission (a
     * mission completed, by title) and subject_activities (completed activities of one subject, by code).
     *
     * @return list<array{name: string, description: string, type: string, icon: string, color: string, criteria: string, points_required: int|null, active: bool, rule: array{0: string, 1: int|string, 2?: int}}>
     */
    public static function rewards(): array
    {
        return [
            self::reward('First Bite', 'badge', 'apple', 'sun', 'You finished your very first NutriMind activity!',
                'Complete your first learning activity.', ['activities', 1]),
            self::reward('Rising Sprout', 'badge', 'sprout', 'leaf', 'Your healthy habits are starting to grow.',
                'Reach 500 points.', ['points', 500], pointsRequired: 500),
            self::reward('World Explorer', 'badge', 'compass', 'sky', 'You explored a learning world from start to finish.',
                'Complete every activity in one learning world.', ['module', 1]),
            self::reward('Mission Starter', 'badge', 'flag', 'carrot', 'Three missions down, many more to go!',
                'Complete 3 missions.', ['missions', 3]),
            self::reward('Quiz Whiz', 'badge', 'brain', 'grape', 'You know your nutrition facts.',
                'Pass 3 different quizzes.', ['quizzes_passed', 3]),
            self::reward('Hydration Hero', 'badge', 'glass-water', 'sky', 'You kept Hydration Harbor afloat.',
                'Complete the Refill Hydration Harbor story mission.', ['mission', 'Refill Hydration Harbor']),
            self::reward('Label Detective', 'badge', 'search', 'indigo', 'No hidden sugar or salt can escape you.',
                'Complete the Crack the Label Code story mission.', ['mission', 'Crack the Label Code']),
            self::reward('Perfect Plate', 'trophy', 'crown', 'sun', 'A perfect score! Every answer was right.',
                'Get a perfect score on any quiz.', ['perfect', 1]),
            self::reward('Mission Master', 'trophy', 'trophy', 'berry', 'You are a true NutriMind mission master.',
                'Complete 8 missions.', ['missions', 8]),
            self::reward('Golden Spoon', 'trophy', 'utensils', 'carrot', 'Two thousand points of healthy learning!',
                'Reach 2,000 points.', ['points', 2000], pointsRequired: 2000),
            self::reward('Veggie Backpack', 'avatar_item', 'sparkles', 'leaf', 'A backpack covered in veggie patches for your avatar.',
                'Reach 1,000 points to unlock the Veggie Backpack.', ['points', 1000], pointsRequired: 1000),
            self::reward('Chef\'s Hat', 'avatar_item', 'cooking-pot', 'carrot', 'A chef\'s hat for young cooks.',
                'Complete 5 EPP – Home Economics activities.', ['subject_activities', 'EPP-HE', 5]),
            self::reward('Sports Headband', 'avatar_item', 'medal', 'teal', 'A sweatband for active champions.',
                'Complete 5 Physical Education activities.', ['subject_activities', 'PE', 5]),
            self::reward('Nutrition Month Star 2026', 'badge', 'star', 'pink', 'A special badge from Nutrition Month 2026.',
                'Complete the Nutrition Month 2026 Challenge.', ['mission', 'Nutrition Month 2026 Challenge'], active: false),
        ];
    }

    /**
     * Get the module with the given competency code.
     *
     * @return array{code: string, subject: string, grade: int, quarter: int, sort: int, title: string, world: string, topic: string, competency: string, description: string, published: bool, activities: list<array{title: string, type: string, minutes: int, description: string}>}
     */
    public static function moduleByCode(string $code): array
    {
        foreach (self::modules() as $module) {
            if ($module['code'] === $code) {
                return $module;
            }
        }

        throw new InvalidArgumentException("Unknown demo module [{$code}].");
    }

    /**
     * Build a module entry.
     *
     * @param  list<array{0: string, 1: string, 2: int, 3: string}>  $activities
     * @return array{code: string, subject: string, grade: int, quarter: int, sort: int, title: string, world: string, topic: string, competency: string, description: string, published: bool, activities: list<array{title: string, type: string, minutes: int, description: string}>}
     */
    private static function module(
        string $code,
        string $subject,
        int $grade,
        int $quarter,
        int $sort,
        string $title,
        string $world,
        string $topic,
        string $competency,
        string $description,
        array $activities,
        bool $published = true,
    ): array {
        return [
            'code' => $code,
            'subject' => $subject,
            'grade' => $grade,
            'quarter' => $quarter,
            'sort' => $sort,
            'title' => $title,
            'world' => $world,
            'topic' => $topic,
            'competency' => $competency,
            'description' => $description,
            'published' => $published,
            'activities' => array_map(
                fn (array $activity): array => ['title' => $activity[0], 'type' => $activity[1], 'minutes' => $activity[2], 'description' => $activity[3]],
                $activities,
            ),
        ];
    }

    /**
     * Build a mission entry.
     *
     * @return array{title: string, subject: string, grade: int|null, module: string|null, type: string, difficulty: string, points: int, description: string, objective: string, starts_on: string|null, ends_on: string|null, active: bool}
     */
    private static function mission(
        string $title,
        string $subject,
        ?int $grade,
        ?string $module,
        string $type,
        string $difficulty,
        int $points,
        string $description,
        string $objective,
        ?string $startsOn = null,
        ?string $endsOn = null,
        bool $active = true,
    ): array {
        return [
            'title' => $title,
            'subject' => $subject,
            'grade' => $grade,
            'module' => $module,
            'type' => $type,
            'difficulty' => $difficulty,
            'points' => $points,
            'description' => $description,
            'objective' => $objective,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'active' => $active,
        ];
    }

    /**
     * Build a reward entry.
     *
     * @param  array{0: string, 1: int|string, 2?: int}  $rule
     * @return array{name: string, description: string, type: string, icon: string, color: string, criteria: string, points_required: int|null, active: bool, rule: array{0: string, 1: int|string, 2?: int}}
     */
    private static function reward(
        string $name,
        string $type,
        string $icon,
        string $color,
        string $description,
        string $criteria,
        array $rule,
        ?int $pointsRequired = null,
        bool $active = true,
    ): array {
        return [
            'name' => $name,
            'description' => $description,
            'type' => $type,
            'icon' => $icon,
            'color' => $color,
            'criteria' => $criteria,
            'points_required' => $pointsRequired,
            'active' => $active,
            'rule' => $rule,
        ];
    }
}
