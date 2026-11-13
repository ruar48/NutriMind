<?php

use App\Enums\GradeLevel;
use App\Enums\GuardianRelationship;
use App\Enums\Sex;
use App\Models\Classroom;
use App\Services\StudentImport\RowValidator;
use Tests\Feature\Services\StudentImport\Support\StudentImportCsv;

it('normalizes a valid row', function () {
    $this->travelTo('2026-11-13 10:00:00');

    $row = app(RowValidator::class)->validate(7, StudentImportCsv::row([
        'first_name' => '  Ana   Marie ',
        'last_name' => 'Santos',
        'lrn' => '136742200002',
        'grade_level' => 'Grade 6',
        'sex' => 'F',
        'birthdate' => '3/21/2014',
        'username' => 'AnaSantos_6',
        'guardian_name' => 'Roberto  Santos',
        'guardian_email' => 'Roberto.Santos@Example.COM',
        'guardian_relationship' => 'Father',
    ]), null, usesNameParts: true);

    expect($row)
        ->number->toBe(7)
        ->name->toBe('Ana Marie Santos')
        ->firstName->toBe('Ana Marie')
        ->lastName->toBe('Santos')
        ->lrn->toBe('136742200002')
        ->grade->toBe(GradeLevel::Six)
        ->sex->toBe(Sex::Female)
        ->birthdate->toBe('2014-03-21')
        ->username->toBe('AnaSantos_6')
        ->guardianName->toBe('Roberto Santos')
        ->guardianEmail->toBe('roberto.santos@example.com')
        ->guardianRelationship->toBe(GuardianRelationship::Father)
        ->errors->toBe([]);
});

it('leaves optional cells empty and defaults the relationship to guardian', function () {
    $row = app(RowValidator::class)->validate(2, ['name' => 'Juan Dela Cruz', 'grade_level' => '5'], null, usesNameParts: false);

    expect($row)
        ->name->toBe('Juan Dela Cruz')
        ->firstName->toBeNull()
        ->lrn->toBeNull()
        ->sex->toBeNull()
        ->birthdate->toBeNull()
        ->username->toBeNull()
        ->guardianName->toBeNull()
        ->guardianEmail->toBeNull()
        ->guardianRelationship->toBe(GuardianRelationship::Guardian)
        ->errors->toBe([]);
});

it('accepts each spelling of a valid value', function (string $column, string $value, mixed $expected) {
    $this->travelTo('2026-11-13 10:00:00');

    $row = app(RowValidator::class)->validate(2, StudentImportCsv::row([$column => $value]), null, usesNameParts: true);

    $property = match ($column) {
        'grade_level' => 'grade',
        'guardian_relationship' => 'guardianRelationship',
        default => $column,
    };

    expect($row->errors)->toBe([])
        ->and($row->{$property})->toBe($expected);
})->with([
    'grade 5' => ['grade_level', '5', GradeLevel::Five],
    'Grade 6' => ['grade_level', 'Grade 6', GradeLevel::Six],
    'grade6 lowercase' => ['grade_level', 'grade6', GradeLevel::Six],
    'sex male' => ['sex', 'MALE', Sex::Male],
    'sex m' => ['sex', 'm', Sex::Male],
    'sex female' => ['sex', 'Female', Sex::Female],
    'iso birthdate' => ['birthdate', '2015-06-15', '2015-06-15'],
    'us birthdate' => ['birthdate', '06/15/2015', '2015-06-15'],
    'leap day' => ['birthdate', '2016-02-29', '2016-02-29'],
    'yesterday' => ['birthdate', '2026-11-12', '2026-11-12'],
    'relationship any case' => ['guardian_relationship', 'GrandParent', GuardianRelationship::Grandparent],
    'username with dash' => ['username', 'juan-dc_5', 'juan-dc_5'],
    'thirty character username' => ['username', str_repeat('a', 30), str_repeat('a', 30)],
]);

it('reports each invalid cell', function (array $overrides, string $message) {
    $this->travelTo('2026-11-13 10:00:00');

    $row = app(RowValidator::class)->validate(2, StudentImportCsv::row($overrides), null, usesNameParts: true);

    expect($row->errors)->toBe([$message]);
})->with([
    'blank first name' => [['first_name' => ' '], 'First name is required.'],
    'blank last name' => [['last_name' => ''], 'Last name is required.'],
    'long first name' => [['first_name' => str_repeat('a', 61)], 'First name must not be longer than 60 characters.'],
    'long last name' => [['last_name' => str_repeat('b', 61)], 'Last name must not be longer than 60 characters.'],
    'short LRN' => [['lrn' => '13674221'], 'LRN must be exactly 12 digits.'],
    'LRN with letters' => [['lrn' => '13674221000A'], 'LRN must be exactly 12 digits.'],
    'LRN in scientific notation' => [['lrn' => '1.36742E+11'], 'LRN must be exactly 12 digits. Format the LRN column as Text in your spreadsheet so it keeps every digit.'],
    'blank grade' => [['grade_level' => ''], 'Grade level is required.'],
    'grade 4' => [['grade_level' => '4'], 'Grade level must be 5 or 6.'],
    'grade in words' => [['grade_level' => 'five'], 'Grade level must be 5 or 6.'],
    'unknown sex' => [['sex' => 'x'], 'Sex must be male or female (M or F).'],
    'birthdate with dots' => [['birthdate' => '15.06.2015'], 'Birthdate must be a date like 2015-06-15 or 06/15/2015.'],
    'impossible birthdate' => [['birthdate' => '2015-02-30'], 'Birthdate must be a date like 2015-06-15 or 06/15/2015.'],
    'day-first birthdate' => [['birthdate' => '15/06/2015'], 'Birthdate must be a date like 2015-06-15 or 06/15/2015.'],
    'birthdate today' => [['birthdate' => '2026-11-13'], 'Birthdate must be before today.'],
    'future birthdate' => [['birthdate' => '12/25/2027'], 'Birthdate must be before today.'],
    'username with a space' => [['username' => 'juan dc'], 'Username may only contain letters, numbers, dashes and underscores.'],
    'username with an accent' => [['username' => 'niño'], 'Username may only contain letters, numbers, dashes and underscores.'],
    'long username' => [['username' => str_repeat('a', 31)], 'Username must not be longer than 30 characters.'],
    'long guardian name' => [['guardian_name' => str_repeat('c', 121)], 'Guardian name must not be longer than 120 characters.'],
    'invalid guardian email' => [['guardian_email' => 'maria@'], 'Guardian email must be a valid email address.'],
    'guardian email without domain dot' => [['guardian_email' => 'maria@localhost'], 'Guardian email must be a valid email address.'],
    'unknown relationship' => [['guardian_relationship' => 'aunt'], 'Guardian relationship must be mother, father, guardian, grandparent, sibling, relative or other.'],
]);

it('validates the single name column', function (string $name, ?string $expected, array $errors) {
    $row = app(RowValidator::class)->validate(2, ['name' => $name, 'grade_level' => '5'], null, usesNameParts: false);

    expect($row->name)->toBe($expected)
        ->and($row->errors)->toBe($errors);
})->with([
    'squished name' => ['  Dela  Cruz, Juan ', 'Dela Cruz, Juan', []],
    'blank name' => ['', null, ['Name is required.']],
    'name of 120 characters' => [str_repeat('a', 120), str_repeat('a', 120), []],
    'name of 121 characters' => [str_repeat('a', 121), null, ['Name must not be longer than 120 characters.']],
]);

it('lists every problem of a row in column order', function () {
    $row = app(RowValidator::class)->validate(2, StudentImportCsv::row([
        'first_name' => '',
        'lrn' => '12',
        'sex' => 'unknown',
        'guardian_email' => 'not-an-email',
    ]), null, usesNameParts: true);

    expect($row->errors)->toBe([
        'First name is required.',
        'LRN must be exactly 12 digits.',
        'Sex must be male or female (M or F).',
        'Guardian email must be a valid email address.',
    ])->and($row->displayName())->toBe('Dela Cruz');
});

it('uses the classroom grade for blank grade cells', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Six]);

    $row = app(RowValidator::class)->validate(2, StudentImportCsv::row(['grade_level' => '']), $classroom, usesNameParts: true);

    expect($row->grade)->toBe(GradeLevel::Six)
        ->and($row->errors)->toBe([]);
});

it('accepts the classroom grade and rejects other grades', function (string $grade, array $errors) {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);

    $row = app(RowValidator::class)->validate(2, StudentImportCsv::row(['grade_level' => $grade]), $classroom, usesNameParts: true);

    expect($row->errors)->toBe($errors);
})->with([
    'same grade' => ['Grade 5', []],
    'other grade' => ['6', ['Grade level does not match this classroom (Grade 5).']],
    'invalid grade' => ['7', ['Grade level must be 5 or 6.']],
]);
