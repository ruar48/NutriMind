<?php

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\LearnerProgressService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Tests\Fixtures\LearnerProgressFixture;

beforeEach(function () {
    $this->lazyLoadingWasPrevented = Model::preventsLazyLoading();
    Model::preventLazyLoading();
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
});

afterEach(function () {
    Model::preventLazyLoading($this->lazyLoadingWasPrevented);
});

it('lists the attempts with score, result, subject and offline play without links by default', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::create()->student);

    $view = $this->blade('<x-learner.recent-attempts :attempts="$attempts" />', ['attempts' => $summary->recentAttempts]);

    $view->assertSeeText('Recent quiz attempts')
        ->assertSeeTextInOrder([
            'Healthy Snacks', '70%', 'Not passed', 'Health', 'Played offline', 'Attempt 1 · 7 / 10 points',
            'Healthy Plate', '80%', 'Passed', 'Health', 'Attempt 2 · 8 / 10 points',
            'Digestion', '95%', 'Passed', 'Science',
            'Healthy Plate', '60%', 'Not passed',
            'Fractions of a Plate', '50%', 'Not passed', 'Mathematics',
        ])
        ->assertSee('title="Nov 11, 2026 3:00 PM"', false)
        ->assertDontSee('<a href', false);
});

it('links each attempt with the attempt URL closure', function () {
    $fixture = LearnerProgressFixture::create();
    $summary = app(LearnerProgressService::class)->summarize($fixture->student);

    $view = $this->blade('<x-learner.recent-attempts :attempts="$attempts" :attempt-url="$attemptUrl" />', [
        'attempts' => $summary->recentAttempts,
        'attemptUrl' => fn (QuizAttempt $attempt): string => '/teacher/attempts/'.$attempt->id,
    ]);

    $view->assertSee('href="/teacher/attempts/'.$fixture->attempts['healthySnacks']->id.'"', false)
        ->assertSee('href="/teacher/attempts/'.$fixture->attempts['fractionsBest']->id.'"', false);
});

it('accepts an inline arrow function as the attempt URL', function () {
    $fixture = LearnerProgressFixture::create();
    $summary = app(LearnerProgressService::class)->summarize($fixture->student);

    $view = $this->blade(
        '<x-learner.recent-attempts :attempts="$attempts" :attempt-url="fn ($attempt) => \'/parent/attempts/\'.$attempt->id" />',
        ['attempts' => $summary->recentAttempts],
    );

    $view->assertSee('href="/parent/attempts/'.$fixture->attempts['digestion']->id.'"', false);
});

it('ignores an attempt URL that is not a closure', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::create()->student);

    $view = $this->blade('<x-learner.recent-attempts :attempts="$attempts" attempt-url="strtoupper" />', ['attempts' => $summary->recentAttempts]);

    $view->assertDontSee('<a href', false);
});

it('lists one page of a paginated attempt history', function () {
    $fixture = LearnerProgressFixture::create();
    $attempts = $fixture->student->quizAttempts()->submitted()->with('quiz.subject')
        ->latest('submitted_at')->orderByDesc('id')->paginate(2);

    $view = $this->blade('<x-learner.recent-attempts :attempts="$attempts" title="Quiz attempts" />', ['attempts' => $attempts]);

    $view->assertSeeText('Quiz attempts')
        ->assertSeeTextInOrder(['Healthy Snacks', 'Healthy Plate'])
        ->assertDontSeeText('Digestion');
});

it('accepts a custom description, header actions and root attributes', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::newcomer());

    $view = $this->blade(
        '<x-learner.recent-attempts :attempts="$attempts" description="Only your quizzes" class="lg:col-span-2"><x-slot:actions><a href="/all">View all</a></x-slot:actions></x-learner.recent-attempts>',
        ['attempts' => $summary->recentAttempts],
    );

    $view->assertSeeText('Only your quizzes')
        ->assertDontSeeText('The latest quizzes the learner submitted')
        ->assertSee('<a href="/all">View all</a>', false)
        ->assertSee('lg:col-span-2', false);
});

it('shows an empty state when the learner has not submitted a quiz', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::newcomer());

    $view = $this->blade('<x-learner.recent-attempts :attempts="$attempts" />', ['attempts' => $summary->recentAttempts]);

    $view->assertSeeText('No quiz attempts yet')
        ->assertSeeText('Quizzes the learner submits in the game appear here.');
});

it('escapes quiz titles', function () {
    $fixture = LearnerProgressFixture::create();
    Quiz::query()->whereKey($fixture->digestionQuiz->id)->update(['title' => '<img src=x onerror=alert(1)>']);
    $summary = app(LearnerProgressService::class)->summarize($fixture->student);

    $view = $this->blade('<x-learner.recent-attempts :attempts="$attempts" />', ['attempts' => $summary->recentAttempts]);

    $view->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false);
});
