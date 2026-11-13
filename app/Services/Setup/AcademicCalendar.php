<?php

namespace App\Services\Setup;

use App\Models\AcademicTerm;
use App\Models\SchoolYear;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * School-year and quarter bookkeeping for the administrator's academic setup.
 *
 * Exactly one school year is active at a time, and only one quarter (of the active year) is the current
 * quarter. Date comparisons happen in PHP because seeded and Eloquent-written date columns use different
 * string formats ("2026-06-15" and "2026-06-15 00:00:00"), which string comparisons in SQL would mix up.
 */
class AcademicCalendar
{
    /**
     * The number of quarters a new school year is split into.
     */
    public const QUARTERS_PER_YEAR = 4;

    /**
     * The most terms one school year may have (the highest allowed sequence).
     */
    public const MAX_TERMS = 8;

    /**
     * Split a date range into four consecutive, non-overlapping quarters that cover it completely.
     *
     * The days that do not divide evenly go to the first quarters, one each.
     *
     * @return list<array{name: string, sequence: int, starts_on: string, ends_on: string}>
     */
    public function quarterPlan(DateTimeInterface $startsOn, DateTimeInterface $endsOn): array
    {
        $start = CarbonImmutable::parse($startsOn->format('Y-m-d'));
        $end = CarbonImmutable::parse($endsOn->format('Y-m-d'));
        $days = (int) $start->diffInDays($end) + 1;
        $baseLength = intdiv($days, self::QUARTERS_PER_YEAR);
        $extraDays = $days % self::QUARTERS_PER_YEAR;

        $plan = [];
        $cursor = $start;

        for ($index = 0; $index < self::QUARTERS_PER_YEAR; $index++) {
            $length = $baseLength + ($index < $extraDays ? 1 : 0);
            $quarterEnd = $cursor->addDays($length - 1);

            $plan[] = [
                'name' => self::quarterName($index + 1),
                'sequence' => $index + 1,
                'starts_on' => $cursor->toDateString(),
                'ends_on' => $quarterEnd->toDateString(),
            ];

            $cursor = $quarterEnd->addDay();
        }

        return $plan;
    }

    /**
     * Create the four quarters of a school year from its date range.
     *
     * @return Collection<int, AcademicTerm>
     */
    public function createQuarters(SchoolYear $schoolYear): Collection
    {
        return collect($this->quarterPlan($schoolYear->starts_on, $schoolYear->ends_on))
            ->map(fn (array $quarter): AcademicTerm => $schoolYear->terms()->create($quarter));
    }

    /**
     * Make the school year the only active one and pick its current quarter.
     *
     * Every other school year and every quarter of another year is deactivated. When none of the year's
     * quarters is active yet, the quarter whose dates include today becomes the current one (if any).
     * Returns the year's current quarter afterwards, or null when it has none.
     */
    public function activateSchoolYear(SchoolYear $schoolYear): ?AcademicTerm
    {
        return DB::transaction(function () use ($schoolYear): ?AcademicTerm {
            SchoolYear::query()->whereKeyNot($schoolYear->getKey())->where('is_active', true)->update(['is_active' => false]);
            $schoolYear->forceFill(['is_active' => true])->save();

            AcademicTerm::query()
                ->where('school_year_id', '!=', $schoolYear->getKey())
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $terms = $schoolYear->terms()->get();
            $currentTerm = $terms->firstWhere('is_active', true);

            if ($currentTerm instanceof AcademicTerm) {
                return $currentTerm;
            }

            $todaysTerm = $terms->first(fn (AcademicTerm $term): bool => $this->includesToday($term->starts_on, $term->ends_on));
            $todaysTerm?->forceFill(['is_active' => true])->save();

            return $todaysTerm;
        });
    }

    /**
     * Make the quarter the only current quarter.
     */
    public function activateTerm(AcademicTerm $term): void
    {
        DB::transaction(function () use ($term): void {
            AcademicTerm::query()->whereKeyNot($term->getKey())->where('is_active', true)->update(['is_active' => false]);
            $term->forceFill(['is_active' => true])->save();
        });
    }

    /**
     * Get the first school year (by start date) whose dates overlap the range, ignoring one school year.
     */
    public function overlappingSchoolYear(DateTimeInterface $startsOn, DateTimeInterface $endsOn, ?int $ignoreId = null): ?SchoolYear
    {
        return SchoolYear::query()
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get()
            ->first(fn (SchoolYear $schoolYear): bool => $this->overlaps($schoolYear->starts_on, $schoolYear->ends_on, $startsOn, $endsOn));
    }

    /**
     * Get the first quarter of the school year (in sequence order) whose dates overlap the range, ignoring one quarter.
     */
    public function overlappingTerm(SchoolYear $schoolYear, DateTimeInterface $startsOn, DateTimeInterface $endsOn, ?int $ignoreId = null): ?AcademicTerm
    {
        return $schoolYear->terms()
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->get()
            ->first(fn (AcademicTerm $term): bool => $this->overlaps($term->starts_on, $term->ends_on, $startsOn, $endsOn));
    }

    /**
     * Determine whether two inclusive date ranges share at least one day.
     */
    public function overlaps(DateTimeInterface $firstStart, DateTimeInterface $firstEnd, DateTimeInterface $secondStart, DateTimeInterface $secondEnd): bool
    {
        return $firstStart->format('Y-m-d') <= $secondEnd->format('Y-m-d')
            && $secondStart->format('Y-m-d') <= $firstEnd->format('Y-m-d');
    }

    /**
     * Determine whether an inclusive date range includes today (in the application timezone).
     */
    public function includesToday(DateTimeInterface $startsOn, DateTimeInterface $endsOn): bool
    {
        $today = today()->toDateString();

        return $startsOn->format('Y-m-d') <= $today && $today <= $endsOn->format('Y-m-d');
    }

    /**
     * Get the conventional name of a quarter ("1st Quarter", "2nd Quarter", …).
     */
    public static function quarterName(int $sequence): string
    {
        $suffix = match (true) {
            in_array($sequence % 100, [11, 12, 13], true) => 'th',
            $sequence % 10 === 1 => 'st',
            $sequence % 10 === 2 => 'nd',
            $sequence % 10 === 3 => 'rd',
            default => 'th',
        };

        return $sequence.$suffix.' Quarter';
    }
}
