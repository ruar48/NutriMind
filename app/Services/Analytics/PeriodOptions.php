<?php

namespace App\Services\Analytics;

use App\Models\AcademicTerm;
use App\Models\SchoolYear;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The period filter shared by the analytics pages: the choices, their validation and the period they select.
 *
 * Choices: rolling windows (7, 30, 90 days), the current quarter, the other quarters of the active school year and
 * a custom range of at most MAX_CUSTOM_DAYS days. A custom range with a missing end ends today; a missing start
 * makes it CUSTOM_DEFAULT_DAYS long, so choosing "Custom range" alone never fails.
 */
class PeriodOptions
{
    /**
     * The period shown when none is chosen.
     */
    public const DEFAULT = '30d';

    /**
     * The value of the current-quarter choice.
     */
    public const CURRENT_QUARTER = 'quarter';

    /**
     * The value of the custom date range choice.
     */
    public const CUSTOM = 'custom';

    /**
     * The longest custom range, in days.
     */
    public const MAX_CUSTOM_DAYS = 366;

    /**
     * The length of a custom range whose start date is left empty, in days.
     */
    public const CUSTOM_DEFAULT_DAYS = 30;

    /**
     * The rolling windows offered, in days.
     */
    private const ROLLING_DAYS = [7, 30, 90];

    /**
     * Get the period choices as value => label, in display order.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        [$terms, $currentTerm] = $this->terms();

        $options = [];

        foreach (self::ROLLING_DAYS as $days) {
            $options[$days.'d'] = 'Last '.$days.' days';
        }

        if ($currentTerm !== null) {
            $options[self::CURRENT_QUARTER] = 'Current quarter · '.$currentTerm->name;
        }

        foreach ($terms as $term) {
            if ($term->isNot($currentTerm)) {
                $options['term-'.$term->getKey()] = $term->name.' · '.$term->starts_on->format('M j').' – '.$term->ends_on->format('M j, Y');
            }
        }

        $options[self::CUSTOM] = 'Custom range';

        return $options;
    }

    /**
     * Get the validation rules of the period, from and to fields.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(array_keys($this->options()))],
            'from' => ['exclude_unless:period,'.self::CUSTOM, 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['exclude_unless:period,'.self::CUSTOM, 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * Get the messages of the period rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.string' => 'Choose a period from the list.',
            'period.in' => 'Choose a period from the list.',
            'from.date_format' => 'Enter a valid start date.',
            'from.before_or_equal' => 'The start date cannot be in the future.',
            'to.date_format' => 'Enter a valid end date.',
            'to.before_or_equal' => 'The end date cannot be in the future.',
        ];
    }

    /**
     * Check the order and length of a custom range once its dates are valid.
     */
    public function validateCustomRange(Validator $validator, mixed $period, mixed $from, mixed $to): void
    {
        if ($period !== self::CUSTOM || $validator->errors()->hasAny(['period', 'from', 'to'])) {
            return;
        }

        [$start, $end] = $this->customDates($from, $to);

        if ($end->lessThan($start)) {
            $validator->errors()->add('to', 'The end date must be on or after the start date.');

            return;
        }

        if (AnalyticsPeriod::between($start, $end, self::CUSTOM)->days() > self::MAX_CUSTOM_DAYS) {
            $validator->errors()->add('to', 'Choose a date range of '.self::MAX_CUSTOM_DAYS.' days or fewer.');
        }
    }

    /**
     * Get the period selected by validated filter values (the default period when none is chosen).
     */
    public function resolve(?string $period, ?string $from = null, ?string $to = null): AnalyticsPeriod
    {
        if ($period === self::CUSTOM) {
            [$start, $end] = $this->customDates($from, $to);

            return AnalyticsPeriod::between($start, $end, self::CUSTOM);
        }

        if ($period !== null && preg_match('/^(\d+)d$/', $period, $match) === 1 && in_array((int) $match[1], self::ROLLING_DAYS, true)) {
            return AnalyticsPeriod::lastDays((int) $match[1]);
        }

        if ($period === self::CURRENT_QUARTER || ($period !== null && str_starts_with($period, 'term-'))) {
            [$terms, $currentTerm] = $this->terms();

            $term = $period === self::CURRENT_QUARTER
                ? $currentTerm
                : $terms->first(fn (AcademicTerm $term): bool => 'term-'.$term->getKey() === $period);

            if ($term !== null) {
                return AnalyticsPeriod::between($term->starts_on, $term->ends_on, $period, $term->name);
            }
        }

        return AnalyticsPeriod::lastDays(30, self::DEFAULT);
    }

    /**
     * Get the dates of a custom range, filling a missing end with today and a missing start with the default length.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function customDates(mixed $from, mixed $to): array
    {
        $end = $this->parseDate($to) ?? now()->toImmutable()->startOfDay();
        $start = $this->parseDate($from) ?? $end->subDays(self::CUSTOM_DEFAULT_DAYS - 1);

        return [$start, $end];
    }

    /**
     * Parse a Y-m-d date in the app timezone, or null when the value is not one.
     */
    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, config('app.timezone'));

        return $date === false ? null : $date;
    }

    /**
     * Get the terms of the active school year (in order) and the active one among them.
     *
     * @return array{0: Collection<int, AcademicTerm>, 1: AcademicTerm|null}
     */
    private function terms(): array
    {
        $terms = SchoolYear::current()?->terms ?? new Collection;

        $currentTerm = $terms
            ->filter(fn (AcademicTerm $term): bool => $term->is_active)
            ->sortByDesc(fn (AcademicTerm $term): array => [$term->starts_on->getTimestamp(), $term->getKey()])
            ->first();

        return [$terms, $currentTerm];
    }
}
