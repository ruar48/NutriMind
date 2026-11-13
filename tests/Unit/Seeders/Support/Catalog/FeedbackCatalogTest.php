<?php

use Database\Seeders\Support\Catalog\FeedbackCatalog;
use Database\Seeders\Support\Catalog\PeopleCatalog;
use Database\Seeders\Support\Catalog\QuizCatalog;

it('splits 150 feedback messages among the teachers who handle classrooms', function () {
    $teachersWithClassrooms = array_values(array_unique(array_column(PeopleCatalog::classrooms(), 'teacher')));

    expect(array_sum(FeedbackCatalog::MESSAGES_PER_TEACHER))->toBe(150);
    expect(array_keys(FeedbackCatalog::MESSAGES_PER_TEACHER))->toEqualCanonicalizing($teachersWithClassrooms);
});

it('has messages for every feedback type and for learners who stopped playing', function () {
    expect(array_keys(FeedbackCatalog::MESSAGES))->toBe(['praise', 'encouragement', 'guidance', 'concern', 'inactive']);
});

it('addresses every message to the learner and keeps it short', function () {
    foreach (collect(FeedbackCatalog::MESSAGES)->flatten() as $message) {
        expect($message)->toContain(':first');
        expect(mb_strlen($message))->toBeLessThanOrEqual(400);
    }
});

it('uses only the placeholders the feedback seeder fills in', function () {
    $placeholders = [':first', ':quiz', ':score', ':previous', ':topic', ':world', ':date'];

    foreach (collect(FeedbackCatalog::MESSAGES)->flatten() as $message) {
        preg_match_all('/:[a-z]+/', $message, $matches);

        expect(array_diff($matches[0], $placeholders))->toBeEmpty();
    }

    expect(FeedbackCatalog::MESSAGES['encouragement'][0])->toContain(':previous');
    expect(collect(FeedbackCatalog::MESSAGES['inactive'])->every(fn (string $message): bool => str_contains($message, ':date')))->toBeTrue();
});

it('reads naturally with every quiz title', function () {
    $titles = collect(QuizCatalog::groups())->flatMap(fn (array $group): array => array_column($group['quizzes'], 'title'));

    expect($titles->filter(fn (string $title): bool => str_starts_with($title, 'The ')))->toBeEmpty();
});
