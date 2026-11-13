<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ReportType: string
{
    use HasOptions;

    case StudentPerformance = 'student-performance';
    case QuizResults = 'quiz-results';
    case MissionProgress = 'mission-progress';
    case ClassroomSummary = 'classroom-summary';
    case UserAccounts = 'user-accounts';

    /**
     * Get the human-readable title of the report.
     */
    public function label(): string
    {
        return match ($this) {
            self::StudentPerformance => 'Learner performance',
            self::QuizResults => 'Quiz results',
            self::MissionProgress => 'Mission progress',
            self::ClassroomSummary => 'Classroom summary',
            self::UserAccounts => 'User accounts',
        };
    }

    /**
     * Get a one-sentence summary of what the report contains.
     */
    public function description(): string
    {
        return match ($this) {
            self::StudentPerformance => 'Average scores, performance bands, quizzes taken and passed, and levels for each learner.',
            self::QuizResults => 'Attempts, participation, average score and pass rate for each published quiz.',
            self::MissionProgress => 'Eligible learners, completions, completion rate and points awarded for each mission.',
            self::ClassroomSummary => 'Enrollment, average score, quiz completion and recent activity for each classroom.',
            self::UserAccounts => 'Every account with its role, status, contact details and last sign-in.',
        };
    }

    /**
     * Get the palette key that represents the report.
     */
    public function color(): string
    {
        return match ($this) {
            self::StudentPerformance => 'leaf',
            self::QuizResults => 'sky',
            self::MissionProgress => 'carrot',
            self::ClassroomSummary => 'grape',
            self::UserAccounts => 'indigo',
        };
    }

    /**
     * Get the icon name that represents the report.
     */
    public function icon(): string
    {
        return match ($this) {
            self::StudentPerformance => 'trending-up',
            self::QuizResults => 'clipboard-check',
            self::MissionProgress => 'flag',
            self::ClassroomSummary => 'school',
            self::UserAccounts => 'users',
        };
    }

    /**
     * Determine whether teachers may generate the report (scoped to their own classrooms).
     */
    public function availableToTeachers(): bool
    {
        return $this !== self::UserAccounts;
    }
}
