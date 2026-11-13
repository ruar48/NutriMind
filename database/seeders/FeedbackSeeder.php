<?php

namespace Database\Seeders;

use App\Enums\FeedbackType;
use App\Models\Classroom;
use App\Models\LearnerFeedback;
use App\Models\LearningModule;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolYear;
use App\Models\User;
use Database\Seeders\Support\BulkInsert;
use Database\Seeders\Support\Catalog\ContentCatalog;
use Database\Seeders\Support\Catalog\FeedbackCatalog;
use Database\Seeders\Support\Catalog\PeopleCatalog;
use Database\Seeders\Support\Catalog\SchoolCatalog;
use Database\Seeders\Support\DemoClock;
use Database\Seeders\Support\DemoRandom;
use Database\Seeders\Support\LearnerProfile;
use Database\Seeders\Support\LearnerProfiles;
use Illuminate\Database\Seeder;

/**
 * Seeds the feedback teachers wrote to their learners, chosen from the learners' actual results: praise for high
 * scores, encouragement for improvement, guidance for middling scores and concern for low scores or learners who
 * stopped playing. Most feedback is shared with guardians, and guardians have read about half of it.
 */
class FeedbackSeeder extends Seeder
{
    /**
     * The share of each feedback type in a teacher's feedback.
     */
    private const TYPE_SHARES = ['praise' => 0.32, 'encouragement' => 0.26, 'guidance' => 0.24, 'concern' => 0.18];

    /**
     * The probability that feedback of each type is shared with guardians.
     */
    private const VISIBILITY = ['praise' => 0.95, 'encouragement' => 0.95, 'guidance' => 0.85, 'concern' => 0.8];

    /**
     * The most feedback messages one teacher wrote to the same learner.
     */
    private const MESSAGES_PER_LEARNER = 3;

    /**
     * Results synced less than this many seconds ago have not been answered with feedback yet.
     */
    private const RESPONSE_DELAY = 2 * DemoClock::HOUR;

    /**
     * The seeded random stream of this seeder.
     */
    private DemoRandom $random;

    /**
     * The anchored clock of this run.
     */
    private DemoClock $clock;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->random = DemoRandom::stream('feedback');
        $this->clock = DemoClock::start();

        $activeYear = SchoolYear::query()->where('name', SchoolCatalog::ACTIVE_SCHOOL_YEAR)->sole();
        $classrooms = Classroom::query()->active()->whereBelongsTo($activeYear)->orderBy('id')->get(['id', 'teacher_id']);
        $quizzes = Quiz::query()->get(['id', 'teacher_id', 'title', 'learning_module_id'])->keyBy('id');
        $moduleCodes = LearningModule::query()->pluck('competency_code', 'id');
        $attempts = QuizAttempt::query()
            ->whereIn('classroom_id', $classrooms->modelKeys())
            ->orderBy('student_id')
            ->orderBy('quiz_id')
            ->orderBy('attempt_number')
            ->toBase()
            ->get(['id', 'quiz_id', 'student_id', 'classroom_id', 'attempt_number', 'percentage', 'passed', 'synced_at']);
        $names = User::query()->toBase()->pluck('name', 'id');
        $profiles = LearnerProfiles::load($this->clock->now);
        $withGuardian = $this->learnersWithActiveGuardian();
        $demoChildren = User::query()->where('email', PeopleCatalog::DEMO_PARENT_EMAIL)->sole()->linkedStudents()->pluck('users.id')->all();
        $enrollments = (new Classroom)->students()->newPivotStatement()
            ->whereIn('classroom_id', $classrooms->modelKeys())
            ->orderBy('classroom_id')
            ->orderBy('student_id')
            ->get(['classroom_id', 'student_id']);

        $teacherIds = User::query()->toBase()->pluck('id', 'email');
        $rows = [];

        foreach (FeedbackCatalog::MESSAGES_PER_TEACHER as $teacherKey => $target) {
            $teacherId = (int) $teacherIds[PeopleCatalog::teacherEmail($teacherKey)];
            $teacherClassrooms = $classrooms->where('teacher_id', $teacherId)->modelKeys();
            $candidates = array_filter([
                ...$this->resultCandidates($attempts->whereIn('classroom_id', $teacherClassrooms)->all(), $quizzes->all(), $moduleCodes->all()),
                ...$this->inactivityCandidates($enrollments->whereIn('classroom_id', $teacherClassrooms)->all(), $profiles),
            ], fn (array $candidate): bool => $candidate['after'] <= $this->clock->now - self::RESPONSE_DELAY);

            foreach ($this->choose(array_values($candidates), $target, $demoChildren) as $candidate) {
                $rows[] = $this->feedbackRow($teacherId, $candidate, (string) $names[$candidate['student_id']], isset($withGuardian[$candidate['student_id']]));
            }
        }

        usort($rows, fn (array $a, array $b): int => [$a['created_at'], $a['teacher_id']] <=> [$b['created_at'], $b['teacher_id']]);

        BulkInsert::models(LearnerFeedback::class, $rows);
    }

    /**
     * Get the learners who have at least one active guardian account (someone who can read shared feedback).
     *
     * @return array<int, true>
     */
    private function learnersWithActiveGuardian(): array
    {
        $activeGuardianIds = User::query()->active()->toBase()->pluck('id')->all();

        return (new User)->linkedStudents()->newPivotStatement()
            ->whereIn('guardian_id', $activeGuardianIds)
            ->pluck('student_id')
            ->mapWithKeys(fn (mixed $studentId): array => [(int) $studentId => true])
            ->all();
    }

    /**
     * Find feedback-worthy results: the latest attempt of each learner at each of the teacher's quizzes.
     *
     * @param  array<int, object>  $attempts
     * @param  array<int, Quiz>  $quizzes
     * @param  array<int, string>  $moduleCodes
     * @return list<array<string, mixed>>
     */
    private function resultCandidates(array $attempts, array $quizzes, array $moduleCodes): array
    {
        $byLearnerAndQuiz = [];

        foreach ($attempts as $attempt) {
            $byLearnerAndQuiz[$attempt->student_id.'|'.$attempt->quiz_id][] = $attempt;
        }

        $candidates = [];

        foreach ($byLearnerAndQuiz as $quizAttempts) {
            $latest = $quizAttempts[count($quizAttempts) - 1];
            $previous = $quizAttempts[count($quizAttempts) - 2] ?? null;
            $percentage = (float) $latest->percentage;
            $previousPercentage = $previous === null ? null : (float) $previous->percentage;

            $type = match (true) {
                $previous !== null && (bool) $latest->passed && ! (bool) $previous->passed => 'retake',
                $previousPercentage !== null && $percentage - $previousPercentage >= 10 => 'encouragement',
                $percentage >= 92 => 'praise',
                $percentage < 70 => 'concern',
                $percentage < 82 => 'guidance',
                default => null,
            };

            if ($type === null) {
                continue;
            }

            $quiz = $quizzes[$latest->quiz_id];
            $module = $quiz->learning_module_id === null ? null : ContentCatalog::moduleByCode($moduleCodes[$quiz->learning_module_id]);

            $candidates[] = [
                'type' => $type === 'retake' ? 'encouragement' : $type,
                'template' => $type === 'retake' ? 1 : null,
                'student_id' => (int) $latest->student_id,
                'quiz_id' => (int) $latest->quiz_id,
                'attempt_id' => (int) $latest->id,
                'classroom_id' => (int) $latest->classroom_id,
                'after' => DemoClock::at($latest->synced_at),
                'values' => [
                    ':quiz' => $quiz->title,
                    ':score' => $this->percentText($percentage),
                    ':previous' => $previousPercentage === null ? '' : $this->percentText($previousPercentage),
                    ':topic' => $module['topic'] ?? 'this quarter\'s lessons',
                    ':world' => $module['world'] ?? 'NutriMind',
                ],
            ];
        }

        return $candidates;
    }

    /**
     * Find learners in the teacher's classrooms who stopped playing a while ago.
     *
     * @param  array<int, object>  $enrollments
     * @param  array<int, LearnerProfile>  $profiles
     * @return list<array<string, mixed>>
     */
    private function inactivityCandidates(array $enrollments, array $profiles): array
    {
        $candidates = [];

        foreach ($enrollments as $enrollment) {
            $profile = $profiles[$enrollment->student_id];

            if ($profile->inactiveSince === null) {
                continue;
            }

            $candidates[] = [
                'type' => 'inactive',
                'template' => null,
                'student_id' => (int) $enrollment->student_id,
                'quiz_id' => null,
                'attempt_id' => null,
                'classroom_id' => (int) $enrollment->classroom_id,
                'after' => $profile->inactiveSince + $this->random->int(3, 6) * DemoClock::DAY,
                'values' => [':date' => date('F j', $profile->inactiveSince)],
            ];
        }

        return $candidates;
    }

    /**
     * Choose a teacher's feedback from the candidates: the type mix first, the demo family's children first within
     * each type, at most one message per quiz result and a few per learner.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @param  list<int>  $demoChildren
     * @return list<array<string, mixed>>
     */
    private function choose(array $candidates, int $target, array $demoChildren): array
    {
        $pools = [];

        foreach ($this->random->shuffle($candidates) as $candidate) {
            $type = $candidate['type'] === 'inactive' ? 'concern' : $candidate['type'];
            $pools[$type][in_array($candidate['student_id'], $demoChildren, true) ? 0 : 1][] = $candidate;
        }

        $pools = array_map(fn (array $pool): array => [...($pool[0] ?? []), ...($pool[1] ?? [])], $pools);
        $chosen = [];
        $perLearner = [];

        $take = function (array $pool, int $count) use (&$chosen, &$perLearner): void {
            foreach ($pool as $candidate) {
                if ($count <= 0) {
                    return;
                }

                $key = $candidate['student_id'].'|'.($candidate['quiz_id'] ?? 'inactive');

                if (isset($chosen[$key]) || ($perLearner[$candidate['student_id']] ?? 0) >= self::MESSAGES_PER_LEARNER) {
                    continue;
                }

                $chosen[$key] = $candidate;
                $perLearner[$candidate['student_id']] = ($perLearner[$candidate['student_id']] ?? 0) + 1;
                $count--;
            }
        };

        foreach (self::TYPE_SHARES as $type => $share) {
            $take($pools[$type] ?? [], (int) round($target * $share));
        }

        foreach (array_keys(self::TYPE_SHARES) as $type) {
            $take($pools[$type] ?? [], $target - count($chosen));
        }

        return array_values($chosen);
    }

    /**
     * Build a feedback row the teacher wrote within a few days after the result (and before now).
     *
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function feedbackRow(int $teacherId, array $candidate, string $fullName, bool $hasGuardian): array
    {
        $after = $candidate['after'];
        $writtenAt = DemoClock::workTime($this->random, DemoClock::startOfDay($after) + $this->random->int(0, 2) * DemoClock::DAY);
        $writtenAt = $writtenAt < $after + DemoClock::HOUR ? $after + $this->random->int(1, 3) * DemoClock::HOUR : $writtenAt;
        $writtenAt = min($writtenAt, $this->clock->now - 15 * 60);

        $type = $candidate['type'] === 'inactive' ? 'concern' : $candidate['type'];
        $templates = FeedbackCatalog::MESSAGES[$candidate['type']];
        $template = $candidate['template'] !== null
            ? $templates[$candidate['template']]
            : $this->random->pick($candidate['type'] === 'encouragement' && $candidate['values'][':previous'] !== ''
                ? [$templates[0]]
                : array_values(array_diff_key($templates, $candidate['type'] === 'encouragement' ? [0 => true, 1 => true] : [])));

        $isVisible = $this->random->chance(self::VISIBILITY[$type]);
        $readAt = $isVisible && $hasGuardian && $this->random->chance(0.5)
            ? min($writtenAt + $this->random->int(60, 96 * 60) * 60, $this->clock->now - 5 * 60)
            : null;

        return [
            'teacher_id' => $teacherId,
            'student_id' => $candidate['student_id'],
            'classroom_id' => $candidate['classroom_id'],
            'quiz_attempt_id' => $candidate['attempt_id'],
            'type' => FeedbackType::from($type)->value,
            'message' => strtr($template, [':first' => PeopleCatalog::firstNameOf($fullName), ...$candidate['values']]),
            'is_visible_to_guardians' => $isVisible,
            'read_at' => $readAt === null || $readAt <= $writtenAt ? null : DemoClock::format($readAt),
            'created_at' => DemoClock::format($writtenAt),
            'updated_at' => DemoClock::format($writtenAt),
        ];
    }

    /**
     * Format a percentage the way teachers write it ("90", "87.5").
     */
    private function percentText(float $percentage): string
    {
        $rounded = round($percentage, 1);

        return $rounded === floor($rounded) ? (string) (int) $rounded : number_format($rounded, 1);
    }
}
