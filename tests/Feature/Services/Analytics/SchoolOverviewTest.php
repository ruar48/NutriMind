<?php

use App\Models\AcademicTerm;
use App\Models\Classroom;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use App\Services\Analytics\SchoolOverview;
use Carbon\CarbonImmutable;

it('counts the active accounts per role and the non-archived classrooms', function () {
    Classroom::factory()->count(2)->create();
    Classroom::factory()->archived()->create();
    User::factory()->student()->count(3)->create();
    User::factory()->student()->inactive()->create();
    User::factory()->parent()->count(2)->create();
    User::factory()->admin()->create();
    User::factory()->teacher()->inactive()->create();

    expect(app(SchoolOverview::class)->headcounts())->toBe([
        'learners' => 3,
        'teachers' => 3,
        'parents' => 2,
        'administrators' => 1,
        'classrooms' => 2,
    ]);
});

describe('setup checklist', function () {
    it('lists every step as missing for a new school', function () {
        $overview = app(SchoolOverview::class);

        $steps = $overview->setupChecklist($overview->headcounts(), null);

        expect(array_column($steps, 'done', 'key'))->toBe([
            'school-year' => false,
            'quarter' => false,
            'subjects' => false,
            'subject-assignments' => false,
            'enrollment' => false,
        ])
            ->and(array_column($steps, 'action', 'key'))->toMatchArray(['subject-assignments' => 'Add a teacher', 'enrollment' => 'Import learners'])
            ->and(array_column($steps, 'url', 'key'))->toMatchArray([
                'school-year' => route('admin.school-years.index'),
                'subject-assignments' => route('admin.users.create'),
                'enrollment' => route('admin.students.import.create'),
            ]);
    });

    it('counts the teachers without subjects and the learners without classrooms', function () {
        SchoolYear::factory()->active()->create();
        Subject::factory()->create();
        User::factory()->teacher()->count(2)->create();
        User::factory()->teaching()->create();
        $archived = Classroom::factory()->archived()->create();
        User::factory()->student()->enrolledIn($archived)->create();
        User::factory()->student()->enrolledIn(Classroom::factory()->create())->create();
        $overview = app(SchoolOverview::class);

        $steps = collect($overview->setupChecklist($overview->headcounts(), null))->keyBy('key');

        expect($steps['school-year']['done'])->toBeTrue()
            ->and($steps['subjects']['done'])->toBeTrue()
            ->and($steps['subject-assignments'])->done->toBeFalse()->description->toBe('2 teachers have no subject yet and cannot create classrooms or quizzes.')->url->toBe(route('admin.subject-assignments.index'))
            ->and($steps['enrollment'])->done->toBeFalse()->description->toBe('1 learner is not in any classroom, so their game progress reaches no teacher.');
    });

    it('marks every step done once the school is set up', function () {
        $schoolYear = SchoolYear::factory()->active()->create();
        $term = AcademicTerm::factory()->for($schoolYear)->active()->create();
        $classroom = Classroom::factory()->create();
        User::factory()->student()->enrolledIn($classroom)->create();
        $overview = app(SchoolOverview::class);

        $steps = $overview->setupChecklist($overview->headcounts(), $term);

        expect(array_unique(array_column($steps, 'done')))->toBe([true])
            ->and(collect($steps)->firstWhere('key', 'quarter')['url'])->toBe(route('admin.school-years.show', $schoolYear));
    });
});

it('lists the staff and parents who signed in most recently', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    $older = User::factory()->teacher()->create(['last_login_at' => now()->subDay()]);
    $newest = User::factory()->parent()->create(['last_login_at' => now()->subHour()]);
    User::factory()->admin()->create(['last_login_at' => null]);
    User::factory()->student()->create()->forceFill(['last_login_at' => now()])->save();

    expect(app(SchoolOverview::class)->recentSignIns()->modelKeys())->toBe([$newest->id, $older->id])
        ->and(app(SchoolOverview::class)->recentSignIns(1)->modelKeys())->toBe([$newest->id]);
});

it('summarizes the moderation queue with the latest unresolved reports', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    ForumThread::factory()->pending()->count(2)->create();
    ForumReport::factory()->resolved()->create();
    $older = ForumReport::factory()->create(['created_at' => now()->subDay()]);
    $newer = ForumReport::factory()->create(['created_at' => now()]);

    $queue = app(SchoolOverview::class)->moderationQueue();

    expect($queue['reports'])->toBe(2)
        ->and($queue['pendingThreads'])->toBe(2)
        ->and($queue['latestReports']->modelKeys())->toBe([$newer->id, $older->id])
        ->and($queue['latestReports']->first()->relationLoaded('reporter'))->toBeTrue();
});
