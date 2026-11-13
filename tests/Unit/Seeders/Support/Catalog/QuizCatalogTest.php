<?php

use Database\Seeders\Support\Catalog\ContentCatalog;
use Database\Seeders\Support\Catalog\PeopleCatalog;
use Database\Seeders\Support\Catalog\QuizCatalog;

it('plans quizzes only for classrooms the teacher handles', function () {
    $classrooms = collect(PeopleCatalog::classrooms());

    foreach (QuizCatalog::groups() as $group) {
        foreach ($group['sections'] as $section) {
            expect($classrooms->where('section', $section)->where('subject', $group['subject'])->where('teacher', $group['teacher']))->toHaveCount(1);
            expect(PeopleCatalog::SECTIONS[$section]['grade'])->toBe($group['grade']);
        }
    }
});

it('assigns three to five quizzes to every classroom', function () {
    foreach (PeopleCatalog::classrooms() as $classroom) {
        $assigned = collect(QuizCatalog::groups())
            ->filter(fn (array $group): bool => $group['subject'] === $classroom['subject'] && in_array($classroom['section'], $group['sections'], true))
            ->flatMap(fn (array $group): array => array_filter($group['quizzes'], fn (array $quiz): bool => $quiz['assigned']));

        expect($assigned->count())->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(5);
    }
});

it('plans quizzes of eight to twelve questions due within the last eight weeks and the next two', function () {
    $quizzes = collect(QuizCatalog::groups())->flatMap(fn (array $group): array => $group['quizzes']);

    foreach ($quizzes as $quiz) {
        expect($quiz['questions'])->toBeGreaterThanOrEqual(8)->toBeLessThanOrEqual(12);
        expect($quiz['due'])->toBeGreaterThanOrEqual(-56)->toBeLessThanOrEqual(15);
    }

    expect($quizzes->countBy('status')->all())->toBe(['closed' => 15, 'published' => 30, 'draft' => 5]);
});

it('keeps drafts out of the past and published quizzes reviewed', function () {
    $quizzes = collect(QuizCatalog::groups())->flatMap(fn (array $group): array => $group['quizzes']);

    expect($quizzes->where('status', 'draft')->every(fn (array $quiz): bool => $quiz['due'] > 0))->toBeTrue();
    expect($quizzes->where('status', 'closed')->every(fn (array $quiz): bool => $quiz['due'] < -28))->toBeTrue();
    expect($quizzes->where('status', '!=', 'draft')->every(fn (array $quiz): bool => $quiz['reviewed'] && $quiz['assigned']))->toBeTrue();
    expect($quizzes->where('status', 'draft')->where('reviewed', false))->toHaveCount(3);
    expect($quizzes->where('assigned', false)->pluck('title')->all())->toBe(['Science 6 Long Test']);
});

it('focuses quizzes on modules of their own subject and grade', function () {
    foreach (QuizCatalog::groups() as $group) {
        foreach ($group['quizzes'] as $quiz) {
            foreach ($quiz['focus'] as $code) {
                $module = ContentCatalog::moduleByCode($code);

                expect([$module['subject'], $module['grade'], $module['published']])->toBe([$group['subject'], $group['grade'], true]);
            }
        }
    }
});

it('gives every teacher distinct quiz titles', function () {
    $titles = collect(QuizCatalog::groups())
        ->flatMap(fn (array $group): array => array_map(fn (array $quiz): string => $group['teacher'].'|'.$quiz['title'], $group['quizzes']))
        ->push(PeopleCatalog::archivedClassroom()['teacher'].'|'.QuizCatalog::archivedQuiz()['title']);

    expect($titles->unique())->toHaveCount($titles->count());
});

it('dates last year\'s archived quiz inside last year\'s 4th Quarter', function () {
    $quiz = QuizCatalog::archivedQuiz();

    expect($quiz['created_at'])->toBeLessThan($quiz['published_at']);
    expect($quiz['published_at'])->toBeLessThan($quiz['available_from']);
    expect($quiz['available_from'])->toBeLessThan($quiz['due_at']);
    expect($quiz['due_at'])->toBeLessThan($quiz['closed_at']);
    expect(substr($quiz['due_at'], 0, 10))->toBeGreaterThanOrEqual('2026-02-02')->toBeLessThanOrEqual('2026-03-31');
});
