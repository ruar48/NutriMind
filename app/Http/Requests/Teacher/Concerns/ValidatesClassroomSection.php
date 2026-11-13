<?php

namespace App\Http\Requests\Teacher\Concerns;

use App\Enums\GradeLevel;
use App\Models\Classroom;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * One classroom per school year, subject, grade and section (sections compared ignoring case and extra spaces).
 */
trait ValidatesClassroomSection
{
    /**
     * Determine whether another classroom of the school year already uses the subject, grade and section.
     *
     * Archived classrooms count: restoring one must never create a duplicate.
     */
    protected function sectionIsTaken(int $schoolYearId, int $subjectId, GradeLevel $grade, string $section, ?int $ignoreClassroomId = null): bool
    {
        $wanted = $this->normalizedSection($section);

        return Classroom::query()
            ->where('school_year_id', $schoolYearId)
            ->where('subject_id', $subjectId)
            ->where('grade_level', $grade)
            ->when($ignoreClassroomId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreClassroomId))
            ->pluck('section')
            ->contains(fn (string $existing): bool => $this->normalizedSection($existing) === $wanted);
    }

    /**
     * Get the message shown when the section is taken, e.g. "A Health Grade 5 – Sampaguita classroom already exists this school year."
     */
    protected function duplicateSectionMessage(Subject $subject, GradeLevel $grade, string $section): string
    {
        return sprintf('A %s %s – %s classroom already exists this school year.', $subject->name, $grade->label(), Str::squish($section));
    }

    /**
     * Normalize a section name for comparison.
     */
    private function normalizedSection(string $section): string
    {
        return mb_strtolower(Str::squish($section));
    }
}
