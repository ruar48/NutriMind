<?php

use App\Enums\FeedbackType;
use App\Enums\GradeLevel;
use App\Models\Classroom;
use App\Models\LearningModule;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use App\Services\LearnerProgressService;
use App\Services\Monitoring\FeedbackSuggestion;
use App\Services\Monitoring\FeedbackSuggestions;
use Carbon\CarbonImmutable;

/**
 * A Grade 5 Health learner ("Ana Cruz") and a helper that records one submitted attempt at a new quiz.
 *
 * @return array{subject: Subject, student: User, attempt: Closure(float, ?LearningModule=, ?string=): QuizAttempt}
 */
function feedbackSuggestionsSetup(): array
{
    $subject = Subject::factory()->create(['name' => 'Health']);
    $classroom = Classroom::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);
    $student = User::factory()->student(GradeLevel::Five)->enrolledIn($classroom)->create(['name' => 'Ana Cruz']);

    $attempt = function (float $percentage, ?LearningModule $module = null, ?string $submittedAt = null) use ($subject, $student, $classroom): QuizAttempt {
        $quiz = $module === null
            ? Quiz::factory()->published()->for($subject)->create(['grade_level' => GradeLevel::Five])
            : Quiz::factory()->published()->for($module, 'module')->create();

        return QuizAttempt::factory()->for($quiz)->for($student, 'student')->for($classroom)->create([
            'score' => (int) round($percentage),
            'max_score' => 100,
            'percentage' => $percentage,
            'passed' => $percentage >= 75,
            'submitted_at' => $submittedAt ?? now(),
        ]);
    };

    return compact('subject', 'student', 'attempt');
}

/**
 * Build the suggestions for the setup's learner in Health.
 *
 * @param  array{subject: Subject, student: User}  $data
 * @return list<FeedbackSuggestion>
 */
function feedbackSuggestionsFor(array $data): array
{
    $summary = app(LearnerProgressService::class)->summarize($data['student'], [$data['subject']->id]);

    return app(FeedbackSuggestions::class)->for($summary, [$data['subject']->id]);
}

/**
 * @param  list<FeedbackSuggestion>  $suggestions
 * @return list<FeedbackType>
 */
function feedbackSuggestionTypes(array $suggestions): array
{
    return array_map(fn (FeedbackSuggestion $suggestion): FeedbackType => $suggestion->type, $suggestions);
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
});

it('praises an average of 90 or more', function (float $percentage, array $expected) {
    $data = feedbackSuggestionsSetup();
    ($data['attempt'])($percentage);

    expect(feedbackSuggestionTypes(feedbackSuggestionsFor($data)))->toBe($expected);
})->with([
    'exactly 90' => [90, [FeedbackType::Praise]],
    'just below 90' => [89.9, []],
]);

it('addresses the learner by first name with the average', function () {
    $data = feedbackSuggestionsSetup();
    ($data['attempt'])(92.5);

    $suggestion = feedbackSuggestionsFor($data)[0];

    expect($suggestion->reason)->toBe('Average 92.5% · Outstanding')
        ->and($suggestion->message)->toStartWith('Excellent work, Ana! Your quiz average of 92.5%');
});

it('encourages a learner whose weekly average dropped by 5 points or more', function (float $latest, array $expected) {
    $data = feedbackSuggestionsSetup();
    ($data['attempt'])(90, null, '2026-11-04 09:00:00');
    ($data['attempt'])($latest, null, '2026-11-12 09:00:00');

    expect(feedbackSuggestionTypes(feedbackSuggestionsFor($data)))->toBe($expected);
})->with([
    'a 6-point drop' => [84, [FeedbackType::Encouragement]],
    'exactly 5 points' => [85, [FeedbackType::Encouragement]],
    'a 4.9-point drop' => [85.1, []],
    'an improvement' => [95, [FeedbackType::Praise]],
]);

it('names the weeks of the drop', function () {
    $data = feedbackSuggestionsSetup();
    ($data['attempt'])(90, null, '2026-11-04 09:00:00');
    ($data['attempt'])(84, null, '2026-11-12 09:00:00');

    $suggestion = feedbackSuggestionsFor($data)[0];

    expect($suggestion->reason)->toBe('Weekly average fell from 90% (week of Nov 2) to 84% (week of Nov 9)')
        ->and($suggestion->message)->toContain('from 90% to 84%');
});

it('guides the learner to the module with the lowest average', function () {
    $data = feedbackSuggestionsSetup();
    $weak = LearningModule::factory()->for($data['subject'])->create(['grade_level' => GradeLevel::Five, 'title' => 'Go, Grow, Glow World']);
    $strong = LearningModule::factory()->for($data['subject'])->create(['grade_level' => GradeLevel::Five, 'title' => 'Water World']);
    ($data['attempt'])(70, $weak);
    ($data['attempt'])(80, $weak);
    ($data['attempt'])(95, $strong);

    $suggestions = feedbackSuggestionsFor($data);

    expect(feedbackSuggestionTypes($suggestions))->toBe([FeedbackType::Guidance])
        ->and($suggestions[0]->reason)->toBe('Lowest module average: Go, Grow, Glow World (Health) · 75%')
        ->and($suggestions[0]->message)->toContain('“Go, Grow, Glow World” in Health, where your quiz average is 75%');
});

it('guides the learner to the weakest subject without a weak module', function () {
    $data = feedbackSuggestionsSetup();
    ($data['attempt'])(95, LearningModule::factory()->for($data['subject'])->create(['grade_level' => GradeLevel::Five]));
    ($data['attempt'])(65);

    $suggestions = feedbackSuggestionsFor($data);

    expect(feedbackSuggestionTypes($suggestions))->toBe([FeedbackType::Guidance])
        ->and($suggestions[0]->reason)->toBe('Lowest subject average: Health · 80%')
        ->and($suggestions[0]->message)->toContain('focus on Health, where your quiz average is 80%');
});

it('raises a concern below 75 along with guidance', function () {
    $data = feedbackSuggestionsSetup();
    ($data['attempt'])(60);

    $suggestions = feedbackSuggestionsFor($data);

    expect(feedbackSuggestionTypes($suggestions))->toBe([FeedbackType::Guidance, FeedbackType::Concern])
        ->and($suggestions[1]->reason)->toBe('Average 60% · Did Not Meet Expectations')
        ->and($suggestions[1]->message)->toStartWith('Ana, your quiz average is 60%, which is below the 75% mark.');
});

it('suggests nothing without attempts', function () {
    $data = feedbackSuggestionsSetup();

    expect(feedbackSuggestionsFor($data))->toBe([]);
});

it('keeps every suggestion within the feedback message limit', function () {
    $data = feedbackSuggestionsSetup();
    ($data['attempt'])(95, null, '2026-11-04 09:00:00');
    ($data['attempt'])(40, LearningModule::factory()->for($data['subject'])->create(['grade_level' => GradeLevel::Five, 'title' => str_repeat('Long world name ', 9)]), '2026-11-12 09:00:00');

    $suggestions = feedbackSuggestionsFor($data);

    expect(feedbackSuggestionTypes($suggestions))->toBe([FeedbackType::Encouragement, FeedbackType::Guidance, FeedbackType::Concern]);
    foreach ($suggestions as $suggestion) {
        expect(mb_strlen($suggestion->message))->toBeLessThanOrEqual(FeedbackSuggestions::MESSAGE_LIMIT);
    }
});
