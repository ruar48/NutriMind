<?php

namespace App\Services\Reports;

use App\Enums\GradeLevel;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;

/**
 * The validated filters of a report request (see App\Http\Requests\Reports\FilterReportRequest).
 *
 * Classroom reports use classroomId, subjectId, gradeLevel, teacherId (administrators only) and the period;
 * the user accounts report uses role, status and the period (account creation date).
 */
final readonly class ReportFilters
{
    /**
     * The account status filter options.
     *
     * @var array<string, string>
     */
    public const STATUSES = ['active' => 'Active', 'inactive' => 'Inactive'];

    public function __construct(
        public ?int $classroomId = null,
        public ?int $subjectId = null,
        public ?GradeLevel $gradeLevel = null,
        public ?int $teacherId = null,
        /** The first day of the period (start of day, app timezone). */
        public ?CarbonImmutable $from = null,
        /** The last day of the period (end of day, app timezone). */
        public ?CarbonImmutable $to = null,
        public ?UserRole $role = null,
        /** "active", "inactive" or null for every account. */
        public ?string $status = null,
    ) {}

    /**
     * Build the filters from validated input (keys classroom_id, subject_id, grade_level, teacher_id, from, to,
     * role, status; dates as Y-m-d).
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(array $input): self
    {
        return new self(
            classroomId: self::integer($input['classroom_id'] ?? null),
            subjectId: self::integer($input['subject_id'] ?? null),
            gradeLevel: filled($input['grade_level'] ?? null) ? GradeLevel::from((int) $input['grade_level']) : null,
            teacherId: self::integer($input['teacher_id'] ?? null),
            from: filled($input['from'] ?? null) ? CarbonImmutable::createFromFormat('!Y-m-d', $input['from'])->startOfDay() : null,
            to: filled($input['to'] ?? null) ? CarbonImmutable::createFromFormat('!Y-m-d', $input['to'])->endOfDay() : null,
            role: filled($input['role'] ?? null) ? UserRole::from($input['role']) : null,
            status: array_key_exists($input['status'] ?? '', self::STATUSES) ? $input['status'] : null,
        );
    }

    /**
     * Determine whether a period limits the report.
     */
    public function hasPeriod(): bool
    {
        return $this->from !== null || $this->to !== null;
    }

    /**
     * Describe the period, e.g. "Nov 1, 2026 – Nov 13, 2026", "From Nov 1, 2026" or "Until Nov 13, 2026".
     */
    public function periodLabel(): ?string
    {
        return match (true) {
            $this->from !== null && $this->to !== null => $this->from->format('M j, Y').' – '.$this->to->format('M j, Y'),
            $this->from !== null => 'From '.$this->from->format('M j, Y'),
            $this->to !== null => 'Until '.$this->to->format('M j, Y'),
            default => null,
        };
    }

    /**
     * Convert an optional integer-like input value.
     */
    private static function integer(mixed $value): ?int
    {
        return filled($value) ? (int) $value : null;
    }
}
