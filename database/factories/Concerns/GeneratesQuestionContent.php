<?php

namespace Database\Factories\Concerns;

use App\Enums\QuestionType;

/**
 * Realistic nutrition questions for the quiz question and question bank factories.
 */
trait GeneratesQuestionContent
{
    /**
     * Multiple choice questions; the first choice of each is the correct one.
     *
     * @var list<array{prompt: string, choices: list<string>, explanation: string}>
     */
    private const MULTIPLE_CHOICE_QUESTIONS = [
        [
            'prompt' => 'Which food group gives our body energy to work and play?',
            'choices' => ['Go foods', 'Grow foods', 'Glow foods', 'Junk foods'],
            'explanation' => 'Go foods such as rice, bread and root crops are rich in carbohydrates that give us energy.',
        ],
        [
            'prompt' => 'Which of these foods is a good source of protein?',
            'choices' => ['Fish', 'Candy', 'Soft drinks', 'Potato chips'],
            'explanation' => 'Fish is a Grow food that is rich in protein, which helps our body grow and repair itself.',
        ],
        [
            'prompt' => 'Which vitamin helps keep our eyes healthy?',
            'choices' => ['Vitamin A', 'Vitamin D', 'Vitamin K', 'Vitamin B12'],
            'explanation' => 'Vitamin A, found in carrots, squash and malunggay, helps us see well.',
        ],
        [
            'prompt' => 'Which snack is the healthiest choice?',
            'choices' => ['A banana', 'A chocolate bar', 'A bag of chips', 'A can of soda'],
            'explanation' => 'A banana gives vitamins, minerals and fiber without added sugar or salt.',
        ],
        [
            'prompt' => 'Glow foods are rich in which nutrients?',
            'choices' => ['Vitamins and minerals', 'Fats and oils', 'Sugar and salt', 'Caffeine'],
            'explanation' => 'Fruits and vegetables are Glow foods that give us vitamins and minerals to stay healthy.',
        ],
        [
            'prompt' => 'About how many glasses of water should you drink every day?',
            'choices' => ['Eight glasses', 'One glass', 'Two glasses', 'None at all'],
            'explanation' => 'Drinking about eight glasses of water a day keeps the body hydrated.',
        ],
    ];

    /**
     * True or false statements and whether each one is true.
     *
     * @var list<array{prompt: string, is_true: bool, explanation: string}>
     */
    private const TRUE_FALSE_QUESTIONS = [
        [
            'prompt' => 'Fruits and vegetables are Glow foods.',
            'is_true' => true,
            'explanation' => 'Fruits and vegetables give vitamins and minerals, so they are Glow foods.',
        ],
        [
            'prompt' => 'Skipping breakfast helps you focus better in class.',
            'is_true' => false,
            'explanation' => 'A healthy breakfast gives the energy your brain needs to focus.',
        ],
        [
            'prompt' => 'Washing your hands before eating helps prevent sickness.',
            'is_true' => true,
            'explanation' => 'Clean hands keep germs away from your food and your mouth.',
        ],
        [
            'prompt' => 'Soft drinks are a healthy replacement for water.',
            'is_true' => false,
            'explanation' => 'Soft drinks contain a lot of sugar; water is the best drink for your body.',
        ],
    ];

    /**
     * Identification questions with every accepted answer.
     *
     * @var list<array{prompt: string, accepted_answers: list<string>, explanation: string}>
     */
    private const IDENTIFICATION_QUESTIONS = [
        [
            'prompt' => 'What vitamin is found in large amounts in oranges and calamansi?',
            'accepted_answers' => ['Vitamin C', 'Vit C'],
            'explanation' => 'Citrus fruits like oranges and calamansi are rich in Vitamin C.',
        ],
        [
            'prompt' => 'To which food group do rice, bread and corn belong?',
            'accepted_answers' => ['Go foods', 'Go food'],
            'explanation' => 'Rice, bread and corn give energy, so they are Go foods.',
        ],
        [
            'prompt' => 'What mineral in milk helps build strong bones and teeth?',
            'accepted_answers' => ['Calcium'],
            'explanation' => 'Milk and other dairy foods are rich in calcium.',
        ],
        [
            'prompt' => 'What is the label that lists the nutrients in packaged food called?',
            'accepted_answers' => ['Nutrition Facts', 'Nutrition label', 'Nutrition Facts label'],
            'explanation' => 'The Nutrition Facts label shows the serving size and nutrients of packaged food.',
        ],
    ];

    /**
     * Build the content of a multiple choice question with its choices in random order.
     *
     * @return array{type: QuestionType, prompt: string, choices: list<string>, correct_answer: string, explanation: string}
     */
    protected function multipleChoiceContent(): array
    {
        $question = fake()->randomElement(self::MULTIPLE_CHOICE_QUESTIONS);
        $choices = fake()->shuffleArray($question['choices']);

        return [
            'type' => QuestionType::MultipleChoice,
            'prompt' => $question['prompt'],
            'choices' => $choices,
            'correct_answer' => (string) array_search($question['choices'][0], $choices, true),
            'explanation' => $question['explanation'],
        ];
    }

    /**
     * Build the content of a true or false question.
     *
     * @return array{type: QuestionType, prompt: string, choices: list<string>, correct_answer: string, explanation: string}
     */
    protected function trueFalseContent(): array
    {
        $question = fake()->randomElement(self::TRUE_FALSE_QUESTIONS);

        return [
            'type' => QuestionType::TrueFalse,
            'prompt' => $question['prompt'],
            'choices' => ['True', 'False'],
            'correct_answer' => $question['is_true'] ? '0' : '1',
            'explanation' => $question['explanation'],
        ];
    }

    /**
     * Build the content of an identification question.
     *
     * @return array{type: QuestionType, prompt: string, choices: null, correct_answer: string, explanation: string}
     */
    protected function identificationContent(): array
    {
        $question = fake()->randomElement(self::IDENTIFICATION_QUESTIONS);

        return [
            'type' => QuestionType::Identification,
            'prompt' => $question['prompt'],
            'choices' => null,
            'correct_answer' => implode('|', $question['accepted_answers']),
            'explanation' => $question['explanation'],
        ];
    }
}
