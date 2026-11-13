<?php

use App\Enums\GradeLevel;
use App\Enums\PerformanceBand;
use App\Enums\UserRole;
use App\Models\Subject;
use App\Services\Reports\ColumnType;
use Carbon\CarbonImmutable;

it('formats cell values for the screen and the print view', function (ColumnType $type, mixed $value, string $expected) {
    expect($type->display($value))->toBe($expected);
})->with([
    'text' => [ColumnType::Text, 'Ana Cruz', 'Ana Cruz'],
    'blank text' => [ColumnType::Text, '  ', '—'],
    'number' => [ColumnType::Number, 12840, '12,840'],
    'zero' => [ColumnType::Number, 0, '0'],
    'score' => [ColumnType::Score, 86.45, '86.5%'],
    'whole score' => [ColumnType::Score, 75.0, '75%'],
    'missing score' => [ColumnType::Score, null, '—'],
    'rate' => [ColumnType::Rate, 66.7, '66.7%'],
    'band' => [ColumnType::Band, PerformanceBand::VerySatisfactory, 'Very Satisfactory'],
    'badge' => [ColumnType::Badge, GradeLevel::Six, 'Grade 6'],
    'missing badge' => [ColumnType::Badge, null, '—'],
    'subject' => [ColumnType::Subject, new Subject(['name' => 'Health']), 'Health'],
    'level' => [ColumnType::Level, 7, '7'],
    'date' => [ColumnType::Date, CarbonImmutable::parse('2026-11-13 10:00'), 'Nov 13, 2026'],
    'datetime' => [ColumnType::DateTime, CarbonImmutable::parse('2026-11-13 14:05'), 'Nov 13, 2026 2:05 PM'],
    'single-day range' => [ColumnType::DateRange, [CarbonImmutable::parse('2026-11-10 08:00'), CarbonImmutable::parse('2026-11-10 17:00')], 'Nov 10, 2026'],
    'range' => [ColumnType::DateRange, [CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-12')], 'Nov 10, 2026 – Nov 12, 2026'],
    'list' => [ColumnType::List, ['Health 5 – Rizal', 'Science 5 – Rizal'], 'Health 5 – Rizal, Science 5 – Rizal'],
    'empty list' => [ColumnType::List, [], '—'],
    'active' => [ColumnType::Status, true, 'Active'],
    'inactive' => [ColumnType::Status, false, 'Inactive'],
]);

it('shows the column\'s empty text for a missing value', function () {
    expect(ColumnType::DateTime->display(null, 'Never'))->toBe('Never')
        ->and(ColumnType::DateRange->display(null, 'No due date'))->toBe('No due date');
});

it('converts cell values for the CSV export', function (ColumnType $type, mixed $value, string|int|float|null $expected) {
    expect($type->csv($value))->toBe($expected);
})->with([
    'text' => [ColumnType::Text, 'Ana Cruz', 'Ana Cruz'],
    'blank text' => [ColumnType::Text, '', null],
    'number' => [ColumnType::Number, 12840, 12840],
    'score' => [ColumnType::Score, 86.45, 86.5],
    'missing score' => [ColumnType::Score, null, null],
    'band' => [ColumnType::Band, PerformanceBand::NoData, 'No data'],
    'badge' => [ColumnType::Badge, UserRole::Parent, 'Parent / Guardian'],
    'subject' => [ColumnType::Subject, new Subject(['name' => 'Science']), 'Science'],
    'level' => [ColumnType::Level, 3, 3],
    'date' => [ColumnType::Date, CarbonImmutable::parse('2026-11-13 10:00'), '2026-11-13'],
    'datetime' => [ColumnType::DateTime, CarbonImmutable::parse('2026-11-13 14:05'), '2026-11-13 14:05:00'],
    'missing datetime' => [ColumnType::DateTime, null, null],
    'single-day range' => [ColumnType::DateRange, [CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-10')], '2026-11-10'],
    'range' => [ColumnType::DateRange, [CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-12')], '2026-11-10 to 2026-11-12'],
    'list' => [ColumnType::List, ['Health 5 – Rizal', 'Science 5 – Rizal'], 'Health 5 – Rizal; Science 5 – Rizal'],
    'status' => [ColumnType::Status, false, 'Inactive'],
]);

it('right-aligns numeric columns only', function (ColumnType $type, string $align) {
    expect($type->align())->toBe($align)
        ->and($type->isNumeric())->toBe($align === 'right');
})->with([
    [ColumnType::Number, 'right'],
    [ColumnType::Score, 'right'],
    [ColumnType::Rate, 'right'],
    [ColumnType::Level, 'right'],
    [ColumnType::Text, 'left'],
    [ColumnType::Band, 'left'],
    [ColumnType::List, 'left'],
]);
