<?php

namespace App\Services\Content;

use App\Enums\GradeLevel;
use App\Models\LearningModule;
use App\Models\Subject;
use Illuminate\Support\Collection;

/**
 * One competency that teachers plan to teach (a subject, grade and competency code) and the game content
 * that matches it.
 */
final readonly class CompetencyCoverage
{
    /**
     * @param  Collection<int, LearningModule>  $modules  the modules with the same subject, grade and code
     */
    public function __construct(
        public Subject $subject,
        public GradeLevel $grade,
        public string $code,
        public ?string $learningCompetency,
        public ?int $quarter,
        public int $plansCount,
        public int $teachersCount,
        public Collection $modules,
        public int $publishedActivitiesCount,
        public int $missionsCount,
        public int $quizzesCount,
        public CoverageStatus $status,
    ) {}

    /**
     * Get the query string that prefills the "new module" form with this competency.
     *
     * @return array{subject_id: int, grade_level: int, competency_code: string, quarter?: int, learning_competency?: string}
     */
    public function modulePrefill(): array
    {
        return array_filter([
            'subject_id' => (int) $this->subject->getKey(),
            'grade_level' => $this->grade->value,
            'quarter' => $this->quarter,
            'competency_code' => $this->code,
            'learning_competency' => $this->learningCompetency,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
