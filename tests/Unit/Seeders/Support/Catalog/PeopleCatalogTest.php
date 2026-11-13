<?php

use Database\Seeders\Support\Catalog\PeopleCatalog;
use Database\Seeders\Support\Catalog\SchoolCatalog;
use Database\Seeders\Support\LearnerArchetype;

it('builds lowercase, alpha-dash usernames from first and family names', function (string $firstName, string $surname, string $username) {
    expect(PeopleCatalog::usernameFor($firstName, $surname))->toBe($username);
})->with([
    'simple names' => ['Sofia', 'Navarro', 'sofia_navarro'],
    'two-word names' => ['Jhon Lloyd', 'Dela Cruz', 'jhonlloyd_delacruz'],
    'abbreviated first names' => ['Ma. Teresa', 'Del Rosario', 'materesa_delrosario'],
    'names with accents and ñ' => ['Niño', 'Peñaflor', 'nino_penaflor'],
    'long names are capped' => ['Mark Anthony', 'Dimaculangan Panganiban', 'markanthony_dimaculanganpa'],
]);

it('takes the first name off a full name, including multi-word family names', function (string $fullName, string $firstName) {
    expect(PeopleCatalog::firstNameOf($fullName))->toBe($firstName);
})->with([
    'one-word names' => ['Sofia Navarro', 'Sofia'],
    'two-word first name' => ['Rose Ann Lim', 'Rose Ann'],
    'two-word family name' => ['Juan Carlo Dela Cruz', 'Juan Carlo'],
    'family name with a particle' => ['Ella Del Rosario', 'Ella'],
    'unknown family name' => ['Kim Smith', 'Kim'],
]);

it('plans about 140 learners in three Grade 5 and three Grade 6 sections', function () {
    $sections = collect(PeopleCatalog::SECTIONS);

    expect($sections->sum('size'))->toBe(140);
    expect($sections->where('grade', 5)->keys()->all())->toBe(['Sampaguita', 'Ilang-Ilang', 'Gumamela']);
    expect($sections->where('grade', 6)->keys()->all())->toBe(['Narra', 'Molave', 'Mahogany']);
});

it('opens classrooms for two to four subjects in every section, each taught by a teacher of that subject', function () {
    $classrooms = collect(PeopleCatalog::classrooms());
    $teachers = PeopleCatalog::teachers();

    expect($classrooms)->toHaveCount(17);

    foreach (array_keys(PeopleCatalog::SECTIONS) as $section) {
        $subjects = $classrooms->where('section', $section)->pluck('subject');

        expect($subjects->count())->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(4);
        expect($subjects->unique())->toHaveCount($subjects->count());
    }

    foreach ($classrooms as $classroom) {
        expect($teachers[$classroom['teacher']]['subjects'])->toContain($classroom['subject']);
    }

    expect($classrooms->pluck('mode')->unique())->toHaveCount(3);
});

it('assigns one or two subjects to each working teacher and leaves one new teacher without subjects', function () {
    $teachers = collect(PeopleCatalog::teachers());
    $active = $teachers->whereNull('deactivated_at');

    expect($teachers->firstWhere('email', PeopleCatalog::DEMO_TEACHER_EMAIL)['subjects'])->toBe(['HEALTH', 'SCIENCE']);
    expect($active->filter(fn (array $teacher): bool => $teacher['subjects'] === [])->keys()->all())->toBe(['aquino']);
    expect($active->reject(fn (array $teacher): bool => $teacher['subjects'] === [])->every(fn (array $teacher): bool => count($teacher['subjects']) <= 2))->toBeTrue();
    expect($teachers->whereNotNull('deactivated_at')->keys()->all())->toBe(['gutierrez']);

    foreach ($teachers->flatMap(fn (array $teacher): array => $teacher['subjects']) as $subject) {
        expect(SchoolCatalog::subjects())->toHaveKey($subject);
    }
});

it('puts the demo parent\'s children in different grades and fixes their profiles', function () {
    $sections = array_column(PeopleCatalog::DEMO_FAMILY['children'], 'section');
    $grades = array_map(fn (string $section): int => PeopleCatalog::SECTIONS[$section]['grade'], $sections);

    expect($grades)->toBe([5, 6]);
    expect(PeopleCatalog::DEMO_FAMILY['guardian']['email'])->toBe(PeopleCatalog::DEMO_PARENT_EMAIL);
    expect(PeopleCatalog::profileOverrides())->toBe([
        'sofia_navarro' => ['archetype' => LearnerArchetype::Strong, 'growth' => 0.004],
        'miguel_navarro' => ['archetype' => LearnerArchetype::Developing, 'growth' => 0.018],
    ]);
});

it('keeps the reserved family names out of the shared name pool', function () {
    $reserved = [PeopleCatalog::DEMO_FAMILY['surname'], ...array_column(PeopleCatalog::LATE_ENROLLEES, 'surname')];

    expect(array_intersect($reserved, PeopleCatalog::SURNAMES))->toBeEmpty();
    expect(array_unique(PeopleCatalog::SURNAMES))->toHaveCount(count(PeopleCatalog::SURNAMES));
    expect(array_intersect(PeopleCatalog::BOY_NAMES, PeopleCatalog::GIRL_NAMES))->toBeEmpty();
});

it('holds Home Economics and PE in their own venues and other subjects in the homeroom', function (string $subject, string $room) {
    expect(PeopleCatalog::roomFor($subject, 'Narra'))->toBe($room);
})->with([
    'Health' => ['HEALTH', 'Room 201'],
    'Science' => ['SCIENCE', 'Room 201'],
    'EPP – Home Economics' => ['EPP-HE', 'Home Economics Room'],
    'Physical Education' => ['PE', 'Covered Court'],
]);
