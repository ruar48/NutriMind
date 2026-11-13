<?php

use App\Enums\GradeLevel;
use App\Enums\MissionType;
use App\Services\Content\ListFilter;
use Illuminate\Http\Request;

it('reads a trimmed search term and ignores blank or malformed ones', function (mixed $value, ?string $expected) {
    expect(ListFilter::search(Request::create('/', 'GET', ['search' => $value])))->toBe($expected);
})->with([
    'term' => ['  snack shack ', 'snack shack'],
    'blank' => ['   ', null],
    'array' => [['snack'], null],
]);

it('limits the search term to 100 characters', function () {
    expect(ListFilter::search(Request::create('/', 'GET', ['search' => str_repeat('a', 150)])))->toHaveLength(100);
});

it('reads a positive identifier', function (mixed $value, ?int $expected) {
    expect(ListFilter::id(Request::create('/', 'GET', ['subject' => $value]), 'subject'))->toBe($expected);
})->with([
    'digits' => ['12', 12],
    'zero' => ['0', null],
    'negative' => ['-3', null],
    'text' => ['abc', null],
    'decimal' => ['1.5', null],
    'array' => [['1'], null],
]);

it('reads a value from an allow-list', function () {
    $request = Request::create('/', 'GET', ['status' => 'draft', 'quarter' => 'nine']);

    expect(ListFilter::choice($request, 'status', ['published', 'draft']))->toBe('draft')
        ->and(ListFilter::choice($request, 'quarter', ['1', '2', '3', '4']))->toBeNull()
        ->and(ListFilter::choice($request, 'missing', ['x']))->toBeNull();
});

it('reads string- and integer-backed enum cases', function () {
    $request = Request::create('/', 'GET', ['type' => 'weekly', 'grade' => '6', 'bad_grade' => '6abc', 'bad_type' => 'monthly']);

    expect(ListFilter::enum($request, 'type', MissionType::class))->toBe(MissionType::Weekly)
        ->and(ListFilter::enum($request, 'grade', GradeLevel::class))->toBe(GradeLevel::Six)
        ->and(ListFilter::enum($request, 'bad_grade', GradeLevel::class))->toBeNull()
        ->and(ListFilter::enum($request, 'bad_type', MissionType::class))->toBeNull()
        ->and(ListFilter::enum($request, 'missing', MissionType::class))->toBeNull();
});
