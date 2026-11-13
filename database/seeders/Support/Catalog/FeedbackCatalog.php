<?php

namespace Database\Seeders\Support\Catalog;

/**
 * The feedback messages teachers write in the demo school.
 *
 * Placeholders: ":first" (the learner's first name), ":quiz" (quiz title), ":score" and ":previous" (percentages),
 * ":topic" and ":world" (of the quiz's learning module) and ":date" (when an inactive learner last played).
 */
final class FeedbackCatalog
{
    /**
     * How many feedback messages each teacher wrote.
     *
     * @var array<string, int>
     */
    public const MESSAGES_PER_TEACHER = ['santos' => 52, 'villanueva' => 22, 'bautista' => 16, 'mendoza' => 31, 'fernandez' => 29];

    /**
     * The messages for each feedback type.
     *
     * @var array<string, list<string>>
     */
    public const MESSAGES = [
        'praise' => [
            'Excellent work on the :quiz, :first! You scored :score% and clearly understand :topic. Keep sharing your healthy ideas with the class.',
            ':first, you did a great job on the :quiz (:score%). Your answers about :topic were clear and correct. I am proud of your effort!',
            'Wow, :score% on the :quiz! :first, your hard work in :world is paying off. Keep it up!',
            'Great job, :first! You are one of the top scorers in the :quiz. Try helping a classmate review :topic this week.',
            ':first earned :score% on the :quiz. Excellent understanding of :topic! Keep doing the bonus missions to level up.',
        ],
        'encouragement' => [
            'Nice improvement, :first! Your score went up from :previous% to :score% on the :quiz. Keep reviewing and you will reach the top band soon.',
            ':first, I can see how hard you worked on your second try at the :quiz. Passing it shows that practice works. Keep going!',
            'Good effort on the :quiz, :first. You are getting closer! Replay the :world activities once more before the next quiz.',
            ':first, you are improving every week. Do not give up on :topic: finish the remaining activities in :world and you will do even better.',
            'Keep it up, :first! Your scores show steady progress. Try to complete one NutriMind activity every day.',
        ],
        'guidance' => [
            ':first, please review the :world lessons before your next quiz. Focus on :topic and try the practice activity again.',
            ':first, you answered most questions in the :quiz correctly, but some answers about :topic were wrong. Read the explanation after each question to learn from them.',
            'Take your time on identification questions, :first, and check your spelling. The :world reading activity can help you review :topic.',
            ':first, try to finish the remaining activities in :world before the next due date. They will prepare you for questions about :topic.',
            'Before your next attempt, :first, look over your answers in the :quiz and ask me about the items you found difficult.',
        ],
        'concern' => [
            ':first scored :score% on the :quiz and still has unfinished activities in :world. Let us work on this together at home and in school: 15 minutes of NutriMind practice each day will help.',
            'I am concerned about :first\'s result on the :quiz (:score%). Please encourage :first to review :topic. I am happy to talk about it during the Parent-Teacher Conference.',
            ':first found the :quiz difficult (:score%). Let us schedule a short review session during recess this week.',
        ],
        'inactive' => [
            ':first has not opened NutriMind since :date. Please help :first connect to the internet so the game can sync and missed quizzes can still be taken.',
            'We miss :first in NutriMind! The game has not synced since :date. Is everything okay at home? Please message me if :first needs help with the tablet or the internet.',
        ],
    ];
}
