<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicTerm>
 */
class AcademicTermFactory extends Factory
{
    /**
     * The number of quarters a school year is divided into.
     */
    private const QUARTERS = 4;

    /**
     * The last sequence handed out per school year by this factory instance.
     *
     * Models of one create() call are all made before any is saved, so the database alone cannot number them.
     *
     * @var array<int, int>
     */
    private array $lastSequences = [];

    /**
     * Define the model's default state: the next quarter of the school year, dated inside it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_year_id' => SchoolYear::factory(),
            'sequence' => fn (array $attributes): int => $this->nextSequence((int) $attributes['school_year_id']),
            'name' => fn (array $attributes): string => $this->ordinal((int) $attributes['sequence']).' Quarter',
            'starts_on' => fn (array $attributes): string => $this->quarterDates($attributes)[0],
            'ends_on' => fn (array $attributes): string => $this->quarterDates($attributes)[1],
            'is_active' => false,
        ];
    }

    /**
     * Indicate that the term is the active one.
     */
    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    /**
     * Get the sequence that places a new term after the school year's existing terms.
     */
    private function nextSequence(int $schoolYearId): int
    {
        $this->lastSequences[$schoolYearId] ??= (int) AcademicTerm::query()->where('school_year_id', $schoolYearId)->max('sequence');

        return ++$this->lastSequences[$schoolYearId];
    }

    /**
     * Get the first and last day of the quarter within its school year.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: string, 1: string}
     */
    private function quarterDates(array $attributes): array
    {
        $schoolYear = SchoolYear::query()->findOrFail($attributes['school_year_id']);

        $quarterLength = intdiv((int) $schoolYear->starts_on->diffInDays($schoolYear->ends_on) + 1, self::QUARTERS);
        $quarterIndex = min(max((int) $attributes['sequence'], 1), self::QUARTERS) - 1;

        $startsOn = $schoolYear->starts_on->copy()->addDays($quarterIndex * $quarterLength);
        $endsOn = $quarterIndex === self::QUARTERS - 1
            ? $schoolYear->ends_on->copy()
            : $startsOn->copy()->addDays($quarterLength - 1);

        return [$startsOn->toDateString(), $endsOn->toDateString()];
    }

    /**
     * Format a number as an English ordinal ("1st", "2nd", "3rd", "4th").
     */
    private function ordinal(int $number): string
    {
        $suffix = match (true) {
            in_array($number % 100, [11, 12, 13], true) => 'th',
            $number % 10 === 1 => 'st',
            $number % 10 === 2 => 'nd',
            $number % 10 === 3 => 'rd',
            default => 'th',
        };

        return $number.$suffix;
    }
}
