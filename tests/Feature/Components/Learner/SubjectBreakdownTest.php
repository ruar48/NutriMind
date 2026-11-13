<?php

use App\Models\Subject;
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

it('lists each classroom subject with its score, quizzes, missions and activities', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::create()->student);

    $view = $this->blade('<x-learner.subject-breakdown :subjects="$subjects" />', ['subjects' => $summary->subjectBreakdown]);

    $view->assertSeeText('Progress by subject')
        ->assertSeeTextInOrder([
            'Health', '75%', 'Quizzes taken', '2', 'Missions done', '1', 'Activities', '1 / 2',
            'Science', '95%', 'Quizzes taken', '1', 'Missions done', '1', 'Activities', '1 / 2',
        ])
        ->assertDontSeeText('Mathematics')
        ->assertSee('aria-valuenow="1"', false)
        ->assertSee('aria-valuemax="2"', false);
});

it('shows an empty state when the learner has no classroom subjects', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::newcomer());

    $view = $this->blade('<x-learner.subject-breakdown :subjects="$subjects" />', ['subjects' => $summary->subjectBreakdown]);

    $view->assertSeeText('No subjects yet')
        ->assertSeeText('Subjects appear here once the learner is enrolled in a classroom.');
});

it('accepts a custom title, description, header actions and root attributes', function () {
    $view = $this->blade(
        '<x-learner.subject-breakdown :subjects="[]" title="Your subjects" description="Health only" class="xl:col-span-2"><x-slot:actions><a href="/subjects">Subjects</a></x-slot:actions></x-learner.subject-breakdown>',
    );

    $view->assertSeeText('Your subjects')
        ->assertSeeText('Health only')
        ->assertDontSeeText('Progress by subject')
        ->assertSee('<a href="/subjects">Subjects</a>', false)
        ->assertSee('xl:col-span-2', false);
});

it('shows a dash for a subject without quiz attempts and no bar without activities', function () {
    $subjects = [[
        'subject' => Subject::factory()->create(['name' => 'Mathematics']),
        'averageScore' => null,
        'quizzesTaken' => 0,
        'missionsCompleted' => 0,
        'activitiesCompleted' => 0,
        'activitiesTotal' => 0,
    ]];

    $view = $this->blade('<x-learner.subject-breakdown :subjects="$subjects" />', ['subjects' => $subjects]);

    $view->assertSeeTextInOrder(['Mathematics', '—', '0 / 0'])
        ->assertDontSee('role="progressbar"', false);
});
