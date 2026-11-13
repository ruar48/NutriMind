<?php

namespace Database\Seeders\Support\Catalog;

/**
 * The parent forum of the demo school.
 *
 * Authors: "admin", "demo-parent", "teacher:{key}", "parent" (a parent account picked by the seeder) and, for
 * replies and reports, "author" (the thread's author). Reply times are hours after the thread was posted.
 */
final class ForumCatalog
{
    /**
     * Get the forum categories, keyed by slug.
     *
     * @return array<string, array{name: string, description: string, color: string, icon: string}>
     */
    public static function categories(): array
    {
        return [
            'school-announcements' => [
                'name' => 'School Announcements',
                'description' => 'Official news, schedules and reminders from Mabini Elementary School.',
                'color' => 'sky',
                'icon' => 'megaphone',
            ],
            'general-discussion' => [
                'name' => 'General Discussion',
                'description' => 'Introduce yourself and talk about anything related to NutriMind and school life.',
                'color' => 'leaf',
                'icon' => 'messages-square',
            ],
            'healthy-meals-at-home' => [
                'name' => 'Healthy Meals at Home',
                'description' => 'Share budget-friendly recipes and tips for balanced family meals.',
                'color' => 'carrot',
                'icon' => 'cooking-pot',
            ],
            'homework-learning-help' => [
                'name' => 'Homework & Learning Help',
                'description' => 'Ask questions about NutriMind activities, quizzes and school projects.',
                'color' => 'grape',
                'icon' => 'book-open',
            ],
            'healthy-baon-ideas' => [
                'name' => 'Healthy Baon Ideas',
                'description' => 'Swap ideas for healthy, safe and affordable school baon.',
                'color' => 'sun',
                'icon' => 'salad',
            ],
        ];
    }

    /**
     * Get the threads with their replies, keyed by a short thread key.
     *
     * @return array<string, array{category: string, author: string, title: string, body: string, days_ago: float, status: string, pinned: bool, locked: bool, replies: list<array{author: string, after: float, body: string, status: string, key: string|null}>}>
     */
    public static function threads(): array
    {
        return [
            'guidelines' => self::thread('school-announcements', 'admin', 'Welcome to the NutriMind Parent Forum: please read our community guidelines',
                "Welcome, parents and guardians! This forum is a space for Mabini Elementary School families and teachers to share ideas about healthy eating, support our learners in NutriMind and ask questions.\n\nPlease keep our community safe and friendly:\n• Be kind and respectful. We are all learning together.\n• Do not share personal information such as home addresses, phone numbers or your child's password.\n• No selling, advertising or chain messages.\n• Share health tips from trusted sources such as the DOH, FNRI and your child's teachers.\n• Use the Report button if you see something that breaks these rules. Our moderators will review it.\n\nThank you for helping us raise healthy, happy learners!",
                75.0, pinned: true, replies: [
                    self::reply('parent', 5, 'Thank you for setting this up! Excited to learn from other parents here.'),
                    self::reply('parent', 9, "Salamat po! This will really help working parents like us who can't always visit the school."),
                    self::reply('teacher:santos', 26, 'Welcome, everyone! Teachers will check the forum regularly, so feel free to post your questions about NutriMind activities here.'),
                ]),
            'nutrition-month' => self::thread('school-announcements', 'admin', 'Nutrition Month 2026 culminating program on July 31',
                "Nutrition Month reminds us that healthy eating starts at home. Our culminating program will be on Friday, July 31, at the covered court.\n\nGrade 5 learners will present a Pinggang Pinoy cooking demo and Grade 6 learners will hold a healthy baon fair. Parents are welcome to join! Please coordinate with your child's adviser if you can help prepare the booths.",
                130.0, locked: true, replies: [
                    self::reply('parent', 20, 'We will be there! My daughter is so excited about the cooking demo.'),
                    self::reply('parent', 46, 'Can parents bring food to share at the baon fair? I can make turon with less sugar.'),
                    self::reply('teacher:bautista', 52, 'Yes, healthy homemade dishes are welcome! Please label them with the ingredients in case of allergies.'),
                    self::reply('parent', 600, 'Congratulations to all the learners! The Pinggang Pinoy cooking demo was wonderful.'),
                    self::reply('admin', 650, 'Thank you for celebrating Nutrition Month with us! We are locking this thread now. Watch out for the photos in the school newsletter.'),
                ]),
            'baon-week' => self::thread('school-announcements', 'admin', 'Healthy Baon Week: November 9 to 20',
                "From November 9 to 20, we are celebrating Healthy Baon Week! Pack a baon with Go, Grow and Glow foods and a bottle of water.\n\nLearners can also complete the Healthy Baon Week Challenge in NutriMind to earn bonus points. Share your best baon ideas in the Healthy Baon Ideas category!",
                9.2, replies: [
                    self::reply('parent', 3, 'Great idea! My son wants to try the challenge. Is there a list of suggested baon?'),
                    self::reply('teacher:bautista', 5, 'The Healthy Baon Packing world in NutriMind has a planner your child can use. Simple combinations like rice, chicken adobo, steamed sayote and a banana are perfect.'),
                    self::reply('parent', 8, "Thank you, Ma'am Rowena! We will try that this week."),
                    self::reply('demo-parent', 100, 'Sofia packed her own baon today using the planner: rice, tortang talong and a slice of papaya. Proud nanay here!'),
                    self::reply('parent', 120, 'Love this initiative. The kids are reminding each other to bring their water bottles.'),
                ]),
            'quiz-schedule' => self::thread('school-announcements', 'teacher:santos', 'Second Quarter quiz schedule and reminders',
                "Good day, parents! Here are some reminders for the Second Quarter:\n\n• Health and Science quizzes are posted in NutriMind with their due dates.\n• Learners can take most quizzes twice, and only their best score counts.\n• If your child plays offline, please connect to the internet at least once a day so the results reach me.\n\nYou can check scores and my feedback in the Parent Portal under My Children.",
                16.4, replies: [
                    self::reply('parent', 2, 'Thank you, Teacher Maricel. How do we know if the results were already synced?'),
                    self::reply('teacher:santos', 4, 'In the Parent Portal, open My Children and then the Quizzes tab. Every quiz shows when it was submitted, and quizzes taken offline also show when they were synced.'),
                    self::reply('parent', 7, "Noted po. My son sometimes plays at his lola's house without Wi-Fi, so this is very helpful."),
                    self::reply('demo-parent', 20, "Thank you for the reminders, Ma'am! Sofia and Miguel already checked their due dates."),
                    self::reply('parent', 50, 'Is the Hydration Heroes quiz included this quarter?'),
                    self::reply('teacher:santos', 53, 'Yes, it is! Learners can review the Hydration Harbor activities first.'),
                    self::reply('parent', 75, "Thank you for the clear reminders, Ma'am. It's easier to plan our evenings now."),
                ]),
            'parent-teacher-conference' => self::thread('school-announcements', 'admin', 'Parent-Teacher Conference on November 28',
                "Our Second Quarter Parent-Teacher Conference will be on Saturday, November 28, from 8:00 AM to 12:00 NN. Report cards and NutriMind progress reports will be shared.\n\nPlease bring your child's quiz notebook. See you!",
                2.1, replies: [
                    self::reply('parent', 3, 'Will there be a separate schedule for Grade 5 and Grade 6?'),
                    self::reply('admin', 5, 'Grade 5 advisers will be available from 8:00 to 10:00 AM and Grade 6 advisers from 10:00 AM to 12:00 NN.'),
                    self::reply('parent', 26, 'Noted, thank you! See you on the 28th.'),
                ]),
            'hello' => self::thread('general-discussion', 'parent', 'Hello from a Grade 5 parent!',
                "Hi everyone! I'm the mother of a Grade 5 learner in Sampaguita. My daughter loves the NutriMind game, especially Veggie Valley. She now reminds me to add vegetables to our ulam! Looking forward to sharing tips with you all.",
                60.0, replies: [
                    self::reply('parent', 4, 'Welcome! Same here, my son keeps telling us which foods are Go, Grow and Glow.'),
                    self::reply('parent', 9, 'Hello! Our Grade 6 kid is in Narra. The game really helps them understand nutrition.'),
                    self::reply('teacher:santos', 22, "Welcome to the forum! It's wonderful to hear that the learners bring their lessons home."),
                    self::reply('parent', 30, 'Hi po! Nice to meet fellow parents here.'),
                    self::reply('author', 33, 'Thank you, everyone! Happy to be part of this community.'),
                ]),
            'screen-time' => self::thread('general-discussion', 'parent', 'How do you balance screen time with NutriMind?',
                'My son wants to play NutriMind every evening, which is good, but I worry about too much screen time. How much time do your kids spend on it? Do you have rules at home?',
                41.0, replies: [
                    self::reply('parent', 3, 'We allow 30 minutes after homework on school days. He finishes one or two activities and then we eat dinner together.'),
                    self::reply('teacher:mendoza', 8, 'A good rule is to pair screen time with active time. After every NutriMind session, try 30 to 60 minutes of active play such as biking, dancing or patintero. Children need at least 60 minutes of physical activity a day.'),
                    self::reply('parent', 12, "That's a great tip, Sir Arnel. We'll try the 'play, then move' rule."),
                    self::reply('parent', 20, 'We use a timer. When it rings, the tablet goes back to the charging corner, no arguments.', key: 'timer'),
                    self::reply('author', 26, "Thank you all! We'll start with 30 minutes and an outdoor game after."),
                ]),
            'ampalaya' => self::thread('general-discussion', 'parent', 'My son finally ate ampalaya!',
                'I just have to share: after finishing the Glow foods activities, my son asked for ampalaya with egg for dinner. He said it is a Glow food that helps his body fight sickness. I almost cried! 😄',
                33.0, replies: [
                    self::reply('parent', 2, "Wow, congratulations! That's a big win."),
                    self::reply('parent', 5, 'Ampalaya with egg is a favorite in our house too. Adding a little tomato helps with the bitterness.'),
                    self::reply('teacher:fernandez', 9, 'This made my day! Thank you for sharing. Small wins like this are what NutriMind is all about.'),
                    self::reply('parent', 14, 'Sana all! My daughter still picks out all the vegetables. 😅'),
                    self::reply('author', 16, "Don't give up! It took us months. Letting him help me cook made a big difference."),
                    self::reply('parent', 30, "Good idea, I'll ask her to help me wash and cut the vegetables this weekend."),
                    self::reply('parent', 55, "Our kids now call malunggay 'superhero leaves' because of the game. 😄"),
                ]),
            'offline' => self::thread('general-discussion', 'parent', 'Can NutriMind be played without internet?',
                'We have limited data at home. Can my daughter still play the game offline? Will her scores be saved?',
                27.0, replies: [
                    self::reply('teacher:villanueva', 3, 'Yes! NutriMind has an offline mode. Your daughter can play activities and take quizzes without internet. Her progress is saved on the device and syncs to the school once she connects again.'),
                    self::reply('author', 5, "That's a relief. How often should she connect?"),
                    self::reply('teacher:villanueva', 7, 'Once a day is ideal, even just for a few minutes, so her teacher can see her latest scores before the due dates.'),
                    self::reply('parent', 20, 'We connect to the school Wi-Fi during recess to sync. It works well for us.'),
                    self::reply('author', 22, 'Thank you so much, Sir Jonathan!'),
                    self::reply('parent', 40, 'Good to know! We also have limited data at home.'),
                ]),
            'picky-eaters' => self::thread('general-discussion', 'parent', 'Tips for picky eaters?',
                'My Grade 6 son only wants fried chicken and rice. He refuses most vegetables and fruits except bananas. Any tips that worked for your family?',
                20.0, replies: [
                    self::reply('parent', 2, 'Try adding vegetables slowly: finely chopped malunggay in sopas or grated carrots in giniling. Then make them more visible little by little.'),
                    self::reply('parent', 4, 'Let him choose one new vegetable at the palengke each week and cook it together.'),
                    self::reply('parent', 6, "If your kid only eats fried chicken, that's your fault as a parent. Just don't feed him until he eats his vegetables.", status: 'hidden', key: 'rude'),
                    self::reply('teacher:bautista', 9, 'Involving children in cooking really helps. In EPP we teach simple dishes like ginisang sayote and pinakbet, and learners are proud to eat what they cooked.'),
                    self::reply('parent', 18, "We play the 'rainbow plate' game at dinner: whoever has the most colors on their plate wins."),
                    self::reply('author', 22, "These are great ideas, thank you! We'll try the palengke challenge this Saturday."),
                    self::reply('parent', 40, 'Also, be patient. Kids sometimes need to taste a new food many times before they like it.'),
                ]),
            'spam' => self::thread('general-discussion', 'parent', 'Earn ₱5,000 a week from home!!!',
                'Hello mommies and daddies! Want extra income? Sell our slimming coffee and detox tea, no experience needed. Message me for the starter kit, limited slots only!!!',
                12.0, status: 'hidden'),
            'budget-meals' => self::thread('healthy-meals-at-home', 'parent', 'Budget-friendly Pinggang Pinoy meals for a family of five',
                "We are a family of five on a tight budget. What are your go-to Pinggang Pinoy meals that are healthy but affordable? I'll start: ginisang monggo with malunggay, rice and a banana for dessert.",
                52.0, replies: [
                    self::reply('parent', 3, 'Tinolang manok with sayote and dahon ng sili! One whole chicken lasts us two meals.'),
                    self::reply('parent', 6, 'Sardines with misua and patola is cheap and filling. We add lots of patola for the Glow part.'),
                    self::reply('teacher:bautista', 10, 'Great choices! Monggo, tokwa and eggs are affordable Grow foods, and buying vegetables that are in season saves a lot.'),
                    self::reply('parent', 20, 'Pinakbet with squash, okra and string beans. My kids like it with a little bagoong.'),
                    self::reply('parent', 26, 'Anyone selling a second-hand bike for a Grade 6 kid? PM me.', key: 'bike'),
                    self::reply('author', 30, "Thank you, everyone! I'm saving all of these for next week's menu."),
                    self::reply('parent', 44, "Don't forget the water! We stopped buying soft drinks and save around ₱300 a week."),
                    self::reply('parent', 70, 'Ginisang upo with a little ground pork is a cheap favorite in our house.'),
                ]),
            'malunggay' => self::thread('healthy-meals-at-home', 'parent', 'Ways to sneak malunggay into everyday dishes',
                'Malunggay grows in our backyard and I know it is very nutritious. Besides tinola, how do you use it?',
                44.0, replies: [
                    self::reply('parent', 2, 'I add chopped malunggay leaves to omelets and pancit.'),
                    self::reply('parent', 6, 'Malunggay pandesal! You can blend the leaves into the dough.'),
                    self::reply('teacher:fernandez', 10, 'Malunggay is rich in vitamin A, vitamin C, calcium and iron, so adding it to soups and egg dishes is an easy way to boost any meal.'),
                    self::reply('parent', 15, "We put it in our champorado once. The kids didn't notice. 😂"),
                    self::reply('author', 17, "Haha, that's brave! I'll try the omelet first."),
                ]),
            'merienda' => self::thread('healthy-meals-at-home', 'parent', 'Healthy merienda ideas that kids actually like',
                'My kids are always hungry after school. I want to stop buying chips. What healthy merienda do your kids enjoy?',
                30.0, replies: [
                    self::reply('parent', 2, 'Boiled saba or kamote, and sometimes nilagang mais with a little butter.'),
                    self::reply('parent', 4, 'Fruit shake with banana, milk and ice. No sugar needed if the banana is ripe.'),
                    self::reply('parent', 6, 'Try my homemade detox juice, it cures everything! Message me to order, ₱150 per bottle. Delivery available.', key: 'detox'),
                    self::reply('teacher:santos', 9, 'Lovely ideas! In the Smart Snacking world, learners also made simple fruit kebabs. Ask them to show you!'),
                    self::reply('parent', 16, 'Turon with less sugar, cooked in just a little oil, is our family favorite.'),
                    self::reply('author', 21, 'Thank you! The fruit shake was a hit this afternoon.'),
                    self::reply('parent', 45, 'Kamote cue with less sugar works for us too.'),
                ]),
            'less-salt' => self::thread('healthy-meals-at-home', 'parent', 'Less salt, more flavor: what do you use instead of seasoning mixes?',
                'Our doctor said my husband needs to lower his salt. I usually cook with seasoning cubes and patis. What do you use to make food tasty with less salt?',
                18.0, replies: [
                    self::reply('parent', 3, 'Garlic, onion, ginger and tomatoes, and lots of them. Calamansi also adds flavor.'),
                    self::reply('parent', 6, 'We use tanglad (lemongrass) and pepper in our tinola.'),
                    self::reply('teacher:bautista', 9, 'Herbs and spices like garlic, onion, ginger, pepper and calamansi are great substitutes for salt. Reading labels helps too, because many seasoning mixes are very high in sodium.'),
                    self::reply('author', 12, 'Thank you, this is very helpful!'),
                    self::reply('parent', 30, 'My father also had to cut down on salt. We switched to low-sodium soy sauce and use only half as much.'),
                ]),
            'meatless-monday' => self::thread('healthy-meals-at-home', 'parent', 'Our family tried Meatless Monday with monggo',
                'Inspired by the Grow foods lesson, we tried a Meatless Monday: ginisang monggo with malunggay and fried tokwa. The kids loved it, and it was cheaper too!',
                9.0, replies: [
                    self::reply('parent', 3, 'Love this! Tokwa without the baboy? 😄'),
                    self::reply('parent', 7, 'Monggo is so underrated. We also cook sitaw and kalabasa, sometimes with a little gata on weekends.'),
                    self::reply('teacher:fernandez', 12, 'Beans, tokwa and eggs are excellent Grow foods. Great job making healthy eating fun!'),
                    self::reply('author', 15, "Thank you, Ma'am! We'll make it a weekly habit."),
                ]),
            'banana-pancakes' => self::thread('healthy-meals-at-home', 'parent', 'Recipe: banana oat pancakes without sugar',
                "Here's our weekend recipe: mash 2 ripe bananas, mix with 1 cup of oats and 2 eggs, then cook in a non-stick pan. No sugar needed. The kids love it with a little peanut butter!",
                1.2, status: 'pending'),
            'plate-help' => self::thread('homework-learning-help', 'parent', 'How can I help my child with the Pinggang Pinoy activity?',
                'My daughter is stuck on the Plate Builder mini-game. She keeps putting too much rice on the plate. How can I help her at home?',
                58.0, replies: [
                    self::reply('teacher:santos', 3, 'A fun way is to use your real dinner plate! Ask her to divide it: half for fruits and vegetables, about a third for rice, and the rest for fish, meat or eggs.'),
                    self::reply('author', 26, "We tried it at dinner and she finally got it. Thank you, Ma'am!"),
                    self::reply('parent', 30, 'We printed the Pinggang Pinoy poster and stuck it on our ref. It really helps.'),
                    self::reply('parent', 40, 'Same problem here with my son. Will try the real plate trick.'),
                ]),
            'label-practice' => self::thread('homework-learning-help', 'demo-parent', 'How can we practice reading food labels at home?',
                'Hi Teacher! Miguel says the questions about ingredients lists in the Label Detective lessons are confusing. What can we practice at home?',
                34.0, replies: [
                    self::reply('teacher:santos', 2, 'Thank you for reaching out, Mrs. Navarro! Try reading labels together when you go to the grocery. Remember: ingredients are listed from the biggest amount to the smallest. Ask him to find the snacks that list sugar first.'),
                    self::reply('author', 72, 'We did this at the grocery last weekend. He found three drinks with sugar as the second ingredient!'),
                    self::reply('parent', 80, "Great idea! I'll do this with my daughter too."),
                    self::reply('teacher:santos', 84, 'Wonderful! He can also replay the Compare Two Labels activity in NutriMind for more practice.'),
                ]),
            'younger-sibling' => self::thread('homework-learning-help', 'parent', 'Explaining Go, Grow and Glow foods to a younger sibling',
                'My Grade 5 son wants to teach his little sister about Go, Grow and Glow foods. Any simple ways to explain it to a 5-year-old?',
                24.0, replies: [
                    self::reply('parent', 4, "We use simple words: Go foods are 'energy foods' like rice, Grow foods help you 'get taller' like eggs and milk, and Glow foods make your skin and eyes 'shine' like fruits and vegetables."),
                    self::reply('teacher:fernandez', 8, 'Love this! You can also sort pictures of food into three boxes, just like the Basket Sort Rush mini-game.'),
                    self::reply('author', 30, 'He made his own flashcards from magazine cutouts. So cute!'),
                ]),
            'feedback-tab' => self::thread('homework-learning-help', 'parent', "Where can we see the teacher's feedback?",
                'My son said his teacher sent him feedback on his quiz. Where can I find it in the Parent Portal?',
                14.0, replies: [
                    self::reply('teacher:mendoza', 2, 'Go to My Children, choose your child, then open the Feedback tab. You will see all the feedback teachers have shared with parents.'),
                    self::reply('author', 4, 'Found it! Thank you, Sir. The feedback is very encouraging.'),
                    self::reply('parent', 20, "I didn't know about this tab. Thanks for asking!"),
                ]),
            'digestive-model' => self::thread('homework-learning-help', 'parent', 'Science project: digestive system model ideas',
                'Our Grade 5 learners need to make a model of the digestive system. Any cheap and easy ideas?',
                6.0, replies: [
                    self::reply('teacher:villanueva', 3, 'You can use a plastic bottle for the stomach, a straw for the esophagus and a long rubber tube or yarn for the intestines. Label each organ and explain what it does.'),
                    self::reply('parent', 8, 'We used recycled boxes and colored paper. My son explained the journey of a rice grain from the mouth to the large intestine.'),
                    self::reply('parent', 14, 'Clay works well too! Use a different color for each organ.'),
                    self::reply('author', 20, "Thank you! We'll try the bottle and yarn idea."),
                ]),
            'baon-under-50' => self::thread('healthy-baon-ideas', 'parent', 'Share your best baon combos under ₱50',
                "Let's help each other! What healthy baon do you pack for under ₱50? Mine: rice, scrambled egg with tomatoes, steamed sayote and a small banana.",
                47.0, replies: [
                    self::reply('parent', 2, 'Rice, adobong sitaw with tokwa, and a slice of papaya.'),
                    self::reply('parent', 5, 'Pandesal with peanut butter, a boiled egg and a guava from our tree.'),
                    self::reply('parent', 9, 'Leftover tinola with lots of sayote and malunggay. It saves money and time!'),
                    self::reply('teacher:bautista', 12, 'These are all balanced! Remember to pack a bottle of water instead of juice drinks.'),
                    self::reply('parent', 20, 'Kamote cue with less sugar and a boiled egg on busy mornings.'),
                    self::reply('author', 26, 'Love all of these. Saving them for next week!'),
                    self::reply('parent', 50, 'Ginisang sayote with egg and rice, plus a banana. Around ₱35 for us.'),
                ]),
            'fresh-baon' => self::thread('healthy-baon-ideas', 'parent', 'Keeping baon fresh until lunch without a refrigerator',
                "Our classroom doesn't have a refrigerator. How do you keep baon from spoiling by lunchtime, especially on hot days?",
                36.0, replies: [
                    self::reply('teacher:bautista', 3, 'Use a clean, covered container and an insulated lunch bag. Freeze a bottle of water overnight and use it as an ice pack. On hot days, avoid dishes with gata or mayonnaise because they spoil quickly.'),
                    self::reply('parent', 7, 'We pack food cooked that morning, not the night before.'),
                    self::reply('parent', 12, 'Drier dishes like adobo keep better than dishes with lots of sauce.'),
                    self::reply('author', 15, "The frozen water bottle trick is genius. Thank you, Ma'am!"),
                ]),
            'fruit-ideas' => self::thread('healthy-baon-ideas', 'parent', 'Fruit ideas besides banana?',
                'My kids are tired of bananas. What other fruits are easy to pack for school?',
                21.0, replies: [
                    self::reply('parent', 2, 'Guava, chico and lanzones when in season. Small dalandan too.'),
                    self::reply('parent', 4, 'Sliced mango or papaya in a small container.'),
                    self::reply('parent', 8, 'Grapes and apples are pricey but nice for special days.'),
                    self::reply('teacher:santos', 11, 'Guava is a superstar: it has even more vitamin C than oranges! Fruits in season are also cheaper and fresher.'),
                    self::reply('author', 14, 'Guava it is! We have a tree in the backyard. 😊'),
                    self::reply('parent', 30, 'Saba is great too, boiled the night before and packed in the morning.'),
                ]),
            'water-bottle' => self::thread('healthy-baon-ideas', 'parent', 'Water bottle reminders for kids who forget to drink',
                'My daughter brings her water bottle home still full almost every day. How do you remind your kids to drink water in school?',
                11.0, replies: [
                    self::reply('parent', 2, 'We put a sticker chart on her bottle: one sticker for every refill.'),
                    self::reply('parent', 5, "Honestly, if they drink soft drinks or juice, that's the same as water. Liquid is liquid.", key: 'liquid'),
                    self::reply('teacher:santos', 8, 'Water is still the best choice! Sweet drinks add a lot of sugar. In the Hydration Heroes world, learners also learn the signs of dehydration, such as dark yellow urine and headaches.'),
                    self::reply('parent', 12, 'Marking the bottle with times (9 AM, 11 AM, 1 PM, 3 PM) helps my son.'),
                    self::reply('author', 16, 'The time marks are a great idea. Thank you!'),
                ]),
            'instant-noodles' => self::thread('healthy-baon-ideas', 'parent', 'Is it okay to pack instant noodles once a week?',
                "Sometimes I'm really rushed in the morning. Is it okay to pack instant noodles once a week if I add egg and vegetables?",
                0.6, status: 'pending'),
        ];
    }

    /**
     * Get the moderation reports. Times are hours after the reported content was posted.
     *
     * @return list<array{thread: string, reply: string|null, reporter: string, reason: string, details: string|null, after: float, resolution: string|null, resolved_after: float|null}>
     */
    public static function reports(): array
    {
        return [
            self::report('budget-meals', 'bike', 'parent', 'off_topic', 'This is not about healthy meals.', 6),
            self::report('merienda', 'detox', 'parent', 'spam', 'Selling products and making health claims.', 3),
            self::report('water-bottle', 'liquid', 'teacher:santos', 'misinformation', 'This could mislead other parents: water is the healthiest drink for children.', 2.5),
            self::report('spam', null, 'parent', 'spam', 'Advertising slimming products.', 1.5, 'content_hidden', 4),
            self::report('spam', null, 'teacher:mendoza', 'spam', null, 2.5, 'content_hidden', 4),
            self::report('picky-eaters', 'rude', 'author', 'harassment', 'This comment is hurtful to parents.', 1, 'content_hidden', 5),
            self::report('screen-time', 'timer', 'parent', 'other', 'I think this rule is too strict for kids.', 10, 'dismissed', 20),
        ];
    }

    /**
     * Build a thread entry.
     *
     * @param  list<array{author: string, after: float, body: string, status: string, key: string|null}>  $replies
     * @return array{category: string, author: string, title: string, body: string, days_ago: float, status: string, pinned: bool, locked: bool, replies: list<array{author: string, after: float, body: string, status: string, key: string|null}>}
     */
    private static function thread(
        string $category,
        string $author,
        string $title,
        string $body,
        float $daysAgo,
        string $status = 'published',
        bool $pinned = false,
        bool $locked = false,
        array $replies = [],
    ): array {
        return [
            'category' => $category,
            'author' => $author,
            'title' => $title,
            'body' => $body,
            'days_ago' => $daysAgo,
            'status' => $status,
            'pinned' => $pinned,
            'locked' => $locked,
            'replies' => $replies,
        ];
    }

    /**
     * Build a reply entry.
     *
     * @return array{author: string, after: float, body: string, status: string, key: string|null}
     */
    private static function reply(string $author, float $after, string $body, string $status = 'published', ?string $key = null): array
    {
        return ['author' => $author, 'after' => $after, 'body' => $body, 'status' => $status, 'key' => $key];
    }

    /**
     * Build a report entry.
     *
     * @return array{thread: string, reply: string|null, reporter: string, reason: string, details: string|null, after: float, resolution: string|null, resolved_after: float|null}
     */
    private static function report(
        string $thread,
        ?string $reply,
        string $reporter,
        string $reason,
        ?string $details,
        float $after,
        ?string $resolution = null,
        ?float $resolvedAfter = null,
    ): array {
        return [
            'thread' => $thread,
            'reply' => $reply,
            'reporter' => $reporter,
            'reason' => $reason,
            'details' => $details,
            'after' => $after,
            'resolution' => $resolution,
            'resolved_after' => $resolvedAfter,
        ];
    }
}
