<?php

use App\Models\Quiz;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\Storage;

dataset('reviewed question types', [
    'multiple choice' => [
        fn (): QuizQuestion => QuizQuestion::factory()->for(Quiz::factory()->published())->multipleChoice()->create([
            'prompt' => 'Which of these foods is a good source of protein?',
            'choices' => ['Candy', 'Fish', 'Soft drinks', 'Potato chips'],
            'correct_answer' => '1',
            'explanation' => 'Fish is a Grow food that helps our body grow and repair itself.',
            'points' => 2,
        ]),
        '#<li[^>]*data-choice="1"[^>]*data-correct-choice#',
        'Correct answer',
    ],
    'true or false' => [
        fn (): QuizQuestion => QuizQuestion::factory()->for(Quiz::factory()->published())->trueFalse()->create([
            'prompt' => 'Fruits and vegetables are Glow foods.',
            'choices' => ['True', 'False'],
            'correct_answer' => '0',
            'explanation' => 'Fruits and vegetables give vitamins and minerals, so they are Glow foods.',
            'points' => 2,
        ]),
        '#<li[^>]*data-choice="0"[^>]*data-correct-choice#',
        'Correct answer',
    ],
    'identification' => [
        fn (): QuizQuestion => QuizQuestion::factory()->for(Quiz::factory()->published())->identification()->create([
            'prompt' => 'What vitamin is found in large amounts in oranges and calamansi?',
            'correct_answer' => 'Vitamin C|Vit C',
            'explanation' => 'Citrus fruits like oranges and calamansi are rich in Vitamin C.',
            'points' => 2,
        ]),
        '#data-answer-review-accepted.*?Vitamin C.*?Vit C#s',
        'Accepted answers',
    ],
]);

dataset('stored answer results', [
    'correct answer' => [
        fn (QuizQuestion $question): QuizAttemptAnswer => QuizAttemptAnswer::factory()->recycle($question->quiz)->for($question, 'question')->correct()->create(),
        'correct', 'Correct', 'circle-check', 'leaf', '2 / 2 points',
    ],
    'incorrect answer' => [
        fn (QuizQuestion $question): QuizAttemptAnswer => QuizAttemptAnswer::factory()->recycle($question->quiz)->for($question, 'question')->incorrect()->create(),
        'incorrect', 'Incorrect', 'circle-x', 'berry', '0 / 2 points',
    ],
    'no answer' => [
        fn (QuizQuestion $question): ?QuizAttemptAnswer => null,
        'unanswered', 'Not answered', 'circle-dashed', 'slate', '0 / 2 points',
    ],
]);

it('labels the stored result with an icon, a label, a color and the points earned', function (
    QuizQuestion $question, string $revealedPattern, string $revealedLabel,
    Closure $answerFor, string $status, string $label, string $icon, string $color, string $points,
) {
    $answer = $answerFor($question);

    $view = $this->blade('<x-question.answer-review :question="$question" :answer="$answer" :number="1" />', ['question' => $question, 'answer' => $answer]);

    $view->assertSee('data-answer-review="'.$status.'"', false)
        ->assertSeeText($points);
    expect((string) $view)->toMatch('#data-answer-review-status[^>]*data-color="'.$color.'"[^>]*>\s*<svg[^>]*data-icon="'.$icon.'"[^>]*>.*?</svg>\s*'.$label.'\s*</span>#s');
})->with('reviewed question types')->with('stored answer results');

it('reveals the correct answer and the explanation by default', function (
    QuizQuestion $question, string $revealedPattern, string $revealedLabel,
    Closure $answerFor,
) {
    $answer = $answerFor($question);

    $view = $this->blade('<x-question.answer-review :question="$question" :answer="$answer" :number="1" />', ['question' => $question, 'answer' => $answer]);

    $view->assertSeeText($revealedLabel)
        ->assertSee('data-answer-review-explanation', false)
        ->assertSeeText($question->explanation);
    expect((string) $view)->toMatch($revealedPattern);
})->with('reviewed question types')->with('stored answer results');

it('shows only the correctness marks when the answers are not revealed', function (
    QuizQuestion $question, string $revealedPattern, string $revealedLabel,
    Closure $answerFor, string $status,
) {
    $answer = $answerFor($question);

    $view = $this->blade('<x-question.answer-review :question="$question" :answer="$answer" :number="1" :reveal="false" />', ['question' => $question, 'answer' => $answer]);

    $view->assertSee('data-answer-review="'.$status.'"', false)
        ->assertDontSeeText($revealedLabel)
        ->assertDontSee('data-correct-choice', false)
        ->assertDontSee('data-answer-review-accepted', false)
        ->assertDontSee('data-answer-review-explanation', false)
        ->assertDontSeeText($question->explanation);
    expect((string) $view)->not->toMatch($revealedPattern);
})->with('reviewed question types')->with('stored answer results');

it('lists every choice in order with its letter', function () {
    $question = QuizQuestion::factory()->multipleChoice()->create([
        'choices' => ['Candy', 'Fish', 'Soft drinks', 'Potato chips'],
        'correct_answer' => '1',
    ]);

    $this->blade('<x-question.answer-review :question="$question" />', ['question' => $question])
        ->assertSeeTextInOrder(['A', 'Candy', 'B', 'Fish', 'C', 'Soft drinks', 'D', 'Potato chips']);
});

it('marks only the chosen choice with the answer label and its correctness', function () {
    $question = QuizQuestion::factory()->for(Quiz::factory()->published())->multipleChoice()->create([
        'choices' => ['Candy', 'Fish', 'Soft drinks', 'Potato chips'],
        'correct_answer' => '1',
    ]);
    $answer = QuizAttemptAnswer::factory()->recycle($question->quiz)->for($question, 'question')->create(['answer' => '0']);

    $html = (string) $this->blade('<x-question.answer-review :question="$question" :answer="$answer" />', ['question' => $question, 'answer' => $answer]);

    expect($html)
        ->toMatch('#<li\s+class="[^"]*bg-berry-50[^"]*"\s+data-choice="0"\s+data-chosen\s*>.*?Candy.*?data-icon="circle-x".*?Learner&\#039;s answer<span class="sr-only"> \(incorrect\)</span>.*?</li>#s')
        ->toMatch('#<li\s+class="[^"]*bg-leaf-50/40[^"]*"\s+data-choice="1"\s+data-correct-choice\s*>#');
    expect(substr_count($html, 'data-chosen'))->toBe(1);
});

it('marks a correct chosen choice as both the learner answer and the correct answer', function () {
    $question = QuizQuestion::factory()->for(Quiz::factory()->published())->trueFalse()->create(['correct_answer' => '1']);
    $answer = QuizAttemptAnswer::factory()->recycle($question->quiz)->for($question, 'question')->correct()->create();

    $html = (string) $this->blade('<x-question.answer-review :question="$question" :answer="$answer" />', ['question' => $question, 'answer' => $answer]);

    expect($html)->toMatch('#<li\s+class="[^"]*bg-leaf-50 [^"]*"\s+data-choice="1"\s+data-chosen\s+data-correct-choice\s*>.*?Learner&\#039;s answer<span class="sr-only"> \(correct\)</span>.*?Correct answer.*?</li>#s');
});

it('names the chosen answer with a custom label', function () {
    $question = QuizQuestion::factory()->for(Quiz::factory()->published())->trueFalse()->create();
    $answer = QuizAttemptAnswer::factory()->recycle($question->quiz)->for($question, 'question')->incorrect()->create();

    $this->blade('<x-question.answer-review :question="$question" :answer="$answer" answer-label="Your answer" />', ['question' => $question, 'answer' => $answer])
        ->assertSeeText('Your answer')
        ->assertDontSeeText("Learner's answer");
});

it('shows a stored choice index that matches no choice as plain text', function () {
    $question = QuizQuestion::factory()->for(Quiz::factory()->published())->multipleChoice()->create([
        'choices' => ['Candy', 'Fish'],
        'correct_answer' => '1',
    ]);
    $answer = QuizAttemptAnswer::factory()->recycle($question->quiz)->for($question, 'question')->create(['answer' => '7']);

    $this->blade('<x-question.answer-review :question="$question" :answer="$answer" />', ['question' => $question, 'answer' => $answer])
        ->assertSee('data-answer-review="incorrect"', false)
        ->assertDontSee('data-chosen', false)
        ->assertSeeText("Learner's answer: 7");
});

it('shows the typed answer of an identification question', function () {
    $question = QuizQuestion::factory()->for(Quiz::factory()->published())->identification()->create(['correct_answer' => 'Calcium']);
    $answer = QuizAttemptAnswer::factory()->recycle($question->quiz)->for($question, 'question')->create(['answer' => 'Iron  and   zinc']);

    $html = (string) $this->blade('<x-question.answer-review :question="$question" :answer="$answer" />', ['question' => $question, 'answer' => $answer]);

    expect($html)
        ->toMatch('#data-answer-review-typed>.*?Learner&\#039;s answer.*?data-icon="circle-x".*?Iron and zinc</span>#s')
        ->toMatch('#data-answer-review-accepted>.*?Accepted answer</dt>.*?Calcium#s');
});

it('shows No answer for an identification question without an answer', function () {
    $question = QuizQuestion::factory()->identification()->create();

    $html = (string) $this->blade('<x-question.answer-review :question="$question" :answer="null" />', ['question' => $question]);

    expect($html)->toMatch('#data-answer-review-typed>.*?data-icon="circle-dashed".*?italic[^>]*>No answer</span>#s');
});

it('treats a stored answer row without an answer as not answered', function () {
    $question = QuizQuestion::factory()->for(Quiz::factory()->published())->multipleChoice()->create();
    $answer = QuizAttemptAnswer::factory()->recycle($question->quiz)->for($question, 'question')->create(['answer' => null]);

    $this->blade('<x-question.answer-review :question="$question" :answer="$answer" />', ['question' => $question, 'answer' => $answer])
        ->assertSee('data-answer-review="unanswered"', false)
        ->assertSeeText('Not answered')
        ->assertDontSee('data-chosen', false);
});

it('uses the singular unit for a one-point question', function () {
    $question = QuizQuestion::factory()->trueFalse()->create(['points' => 1]);

    $this->blade('<x-question.answer-review :question="$question" />', ['question' => $question])
        ->assertSeeText('0 / 1 point')
        ->assertDontSeeText('0 / 1 points');
});

it('shows the question type, the prompt and the question number', function () {
    $question = QuizQuestion::factory()->identification()->create(['prompt' => 'What mineral in milk helps build strong bones and teeth?']);

    $view = $this->blade('<x-question.answer-review :question="$question" :number="3" />', ['question' => $question]);

    $view->assertSeeText('Question 3')
        ->assertSeeText('What mineral in milk helps build strong bones and teeth?');
    expect((string) $view)->toMatch('#<svg[^>]*data-icon="pencil"[^>]*>.*?</svg>\s*Identification\s*</span>#s');
});

it('leaves out the number badge when no number is given', function () {
    $question = QuizQuestion::factory()->create();

    $this->blade('<x-question.answer-review :question="$question" />', ['question' => $question])
        ->assertDontSee('<span class="sr-only">Question </span>', false);
});

it('leaves out the explanation block for a question without an explanation', function () {
    $question = QuizQuestion::factory()->create(['explanation' => null]);

    $this->blade('<x-question.answer-review :question="$question" />', ['question' => $question])
        ->assertDontSee('data-answer-review-explanation', false);
});

it('renders the question media', function () {
    Storage::fake('public');
    $question = QuizQuestion::factory()->create(['image_path' => 'quiz-media/images/plate.png']);

    $this->blade('<x-question.answer-review :question="$question" />', ['question' => $question])
        ->assertSee('data-question-media="full"', false)
        ->assertSee('src="/storage/quiz-media/images/plate.png"', false);
});

it('merges extra attributes into the card', function () {
    $question = QuizQuestion::factory()->create();

    $html = (string) $this->blade('<x-question.answer-review :question="$question" id="question-5" class="scroll-mt-24" />', ['question' => $question]);

    expect($html)->toMatch('#<div\s+class="[^"]*\bscroll-mt-24\b[^"]*"[^>]*\sdata-answer-review="unanswered"[^>]*\sid="question-5"#');
});

it('escapes the prompt, the choices and the explanation', function () {
    $question = QuizQuestion::factory()->multipleChoice()->create([
        'prompt' => 'Pick one <script>alert("prompt")</script>',
        'choices' => ['<img src=x onerror=alert("choice")>', 'Fish'],
        'correct_answer' => '1',
        'explanation' => '<b onclick="alert(1)">Fish</b> builds muscles.',
    ]);

    $html = (string) $this->blade('<x-question.answer-review :question="$question" />', ['question' => $question]);

    expect($html)
        ->toContain('Pick one &lt;script&gt;alert(&quot;prompt&quot;)&lt;/script&gt;')
        ->toContain('&lt;img src=x onerror=alert(&quot;choice&quot;)&gt;')
        ->toContain('&lt;b onclick=&quot;alert(1)&quot;&gt;Fish&lt;/b&gt; builds muscles.')
        ->not->toContain('<script>alert')
        ->not->toContain('<img src=x')
        ->not->toContain('<b onclick');
});

it('escapes the typed answer, the accepted answers and the answer label', function () {
    $question = QuizQuestion::factory()->for(Quiz::factory()->published())->identification()->create([
        'correct_answer' => '<i>Calcium</i>|Ca',
    ]);
    $answer = QuizAttemptAnswer::factory()->recycle($question->quiz)->for($question, 'question')->create([
        'answer' => '<script>alert("typed")</script>',
    ]);

    $html = (string) $this->blade('<x-question.answer-review :question="$question" :answer="$answer" :answer-label="$label" />', [
        'question' => $question,
        'answer' => $answer,
        'label' => '<u>Child</u> answer',
    ]);

    expect($html)
        ->toContain('&lt;script&gt;alert(&quot;typed&quot;)&lt;/script&gt;')
        ->toContain('&lt;i&gt;Calcium&lt;/i&gt;')
        ->toContain('&lt;u&gt;Child&lt;/u&gt; answer')
        ->not->toContain('<script>alert')
        ->not->toContain('<i>Calcium')
        ->not->toContain('<u>Child');
});
