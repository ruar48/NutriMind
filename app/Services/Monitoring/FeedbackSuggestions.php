<?php

namespace App\Services\Monitoring;

use App\Enums\FeedbackType;
use App\Enums\PerformanceBand;
use App\Models\LearningModule;
use App\Models\QuizAttempt;
use App\Services\LearnerProgressSummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Suggests feedback messages from a learner's results in the teacher's subjects.
 *
 * Rules, in this order:
 * - Praise when the average score is at least 90 (Outstanding).
 * - Encouragement when the latest week with attempts averaged at least 5 points less than the week with attempts
 *   before it (within the summary's 8-week window).
 * - Guidance naming the weakest area: the learning module with the lowest average of best quiz percentages, or, without
 *   module-linked quizzes, the subject with the lowest average — when that average is below 85.
 * - Concern when the average Did Not Meet Expectations (below 75).
 */
final class FeedbackSuggestions
{
    /**
     * The lowest average that earns a praise suggestion.
     */
    public const PRAISE_FROM = 90.0;

    /**
     * The smallest week-on-week drop (in percentage points) that earns an encouragement suggestion.
     */
    public const DROP_OF_AT_LEAST = 5.0;

    /**
     * The average below which the weakest module or subject earns a guidance suggestion.
     */
    public const GUIDANCE_BELOW = 85.0;

    /**
     * The longest message a suggestion may have (the feedback message limit).
     */
    public const MESSAGE_LIMIT = 1000;

    /**
     * Build the suggestions for a learner summary limited to the given subjects.
     *
     * @param  list<int>  $subjectIds  the subjects the summary was built for
     * @return list<FeedbackSuggestion>
     */
    public function for(LearnerProgressSummary $summary, array $subjectIds): array
    {
        $firstName = $this->firstName($summary->student->name);

        return array_values(array_filter([
            $this->praise($summary, $firstName),
            $this->encouragement($summary, $firstName),
            $this->guidance($summary, $subjectIds, $firstName),
            $this->concern($summary, $firstName),
        ]));
    }

    private function praise(LearnerProgressSummary $summary, string $firstName): ?FeedbackSuggestion
    {
        if ($summary->averageScore === null || $summary->averageScore < self::PRAISE_FROM) {
            return null;
        }

        $average = $this->percent($summary->averageScore);

        return $this->suggestion(
            FeedbackType::Praise,
            'Celebrate an outstanding average',
            'Average '.$average.' · '.$summary->band->label(),
            "Excellent work, {$firstName}! Your quiz average of {$average} shows that you really understand our lessons. Keep making healthy choices and keep exploring new missions in NutriMind. I am proud of you!",
        );
    }

    private function encouragement(LearnerProgressSummary $summary, string $firstName): ?FeedbackSuggestion
    {
        $weeks = array_values(array_filter($summary->weeklyScores, fn (array $week): bool => $week['average'] !== null));

        if (count($weeks) < 2) {
            return null;
        }

        [$previous, $latest] = array_slice($weeks, -2);

        if ($previous['average'] - $latest['average'] < self::DROP_OF_AT_LEAST) {
            return null;
        }

        $from = $this->percent($previous['average']);
        $to = $this->percent($latest['average']);

        return $this->suggestion(
            FeedbackType::Encouragement,
            'Encourage a comeback',
            "Weekly average fell from {$from} (week of {$previous['label']}) to {$to} (week of {$latest['label']})",
            "Keep going, {$firstName}! Your quiz average went from {$from} to {$to} recently, and that is okay. Every champion has tough weeks. Replay the lessons in NutriMind, take your time on each question, and try again. I know you can bounce back!",
        );
    }

    /**
     * @param  list<int>  $subjectIds
     */
    private function guidance(LearnerProgressSummary $summary, array $subjectIds, string $firstName): ?FeedbackSuggestion
    {
        $module = $this->weakestModule($summary, $subjectIds);

        if ($module !== null) {
            [$learningModule, $average] = $module;
            $area = '“'.$learningModule->title.'” in '.$learningModule->subject->name;
            $reason = 'Lowest module average: '.$learningModule->title.' ('.$learningModule->subject->name.') · '.$this->percent($average);
        } else {
            $subject = collect($summary->subjectBreakdown)
                ->filter(fn (array $row): bool => $row['averageScore'] !== null)
                ->sortBy(fn (array $row): float => $row['averageScore'])
                ->first();

            if ($subject === null || $subject['averageScore'] >= self::GUIDANCE_BELOW) {
                return null;
            }

            $average = $subject['averageScore'];
            $area = $subject['subject']->name;
            $reason = 'Lowest subject average: '.$subject['subject']->name.' · '.$this->percent($average);
        }

        $percent = $this->percent($average);

        return $this->suggestion(
            FeedbackType::Guidance,
            'Point to the weakest area',
            $reason,
            "{$firstName}, let's focus on {$area}, where your quiz average is {$percent}. Replay its activities in NutriMind, review the questions you missed, and then try the quiz again. Small steps every day make a big difference!",
        );
    }

    private function concern(LearnerProgressSummary $summary, string $firstName): ?FeedbackSuggestion
    {
        if ($summary->band !== PerformanceBand::DidNotMeetExpectations) {
            return null;
        }

        $average = $this->percent((float) $summary->averageScore);

        return $this->suggestion(
            FeedbackType::Concern,
            'Raise a concern',
            'Average '.$average.' · '.$summary->band->label(),
            "{$firstName}, your quiz average is {$average}, which is below the 75% mark. Let's work on this together: please see me so we can review the lessons, and keep practicing in NutriMind at home. Parents and guardians, your support and encouragement at home will help a lot.",
        );
    }

    /**
     * Find the learning module whose quizzes have the learner's lowest average of best percentages, when that average
     * is below the guidance threshold. Ties go to the module with the lower id.
     *
     * @param  list<int>  $subjectIds
     * @return array{0: LearningModule, 1: float}|null
     */
    private function weakestModule(LearnerProgressSummary $summary, array $subjectIds): ?array
    {
        if ($subjectIds === []) {
            return null;
        }

        $weakest = QuizAttempt::query()
            ->submitted()
            ->where('quiz_attempts.student_id', $summary->student->getKey())
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->whereNotNull('quizzes.learning_module_id')
            ->whereIn('quizzes.subject_id', $subjectIds)
            ->toBase()
            ->select(['quizzes.learning_module_id', 'quiz_attempts.quiz_id'])
            ->selectRaw('max(quiz_attempts.percentage) as best')
            ->groupBy('quizzes.learning_module_id', 'quiz_attempts.quiz_id')
            ->get()
            ->groupBy(fn (object $row): int => (int) $row->learning_module_id)
            ->map(fn (Collection $quizzes): float => round((float) $quizzes->avg(fn (object $row): float => (float) $row->best), 1))
            ->sortKeys()
            ->sort()
            ->filter(fn (float $average): bool => $average < self::GUIDANCE_BELOW);

        if ($weakest->isEmpty()) {
            return null;
        }

        $module = LearningModule::query()->with('subject')->find($weakest->keys()->first());

        return $module === null ? null : [$module, $weakest->first()];
    }

    private function suggestion(FeedbackType $type, string $title, string $reason, string $message): FeedbackSuggestion
    {
        return new FeedbackSuggestion($type, $title, $reason, Str::limit($message, self::MESSAGE_LIMIT, ''));
    }

    private function firstName(string $name): string
    {
        $firstWord = Str::of($name)->squish()->before(' ')->toString();

        return $firstWord !== '' ? $firstWord : $name;
    }

    private function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.').'%';
    }
}
