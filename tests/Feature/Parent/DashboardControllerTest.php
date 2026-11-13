<?php

use App\Enums\GradeLevel;
use App\Enums\GuardianRelationship;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\LearnerFeedback;
use App\Models\MissionProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Reward;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\RolePermissions;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Create a learner with a known profile, linked to a new or given parent.
 *
 * @param  array<string, mixed>  $profile
 */
function dashboardChild(string $name, GradeLevel $grade, array $profile = []): User
{
    $child = User::factory()->studentWithoutProfile()->create(['name' => $name]);
    StudentProfile::factory()->for($child, 'user')->create(['grade_level' => $grade, ...$profile]);

    return $child;
}

it('sends guests to the sign-in page', function () {
    $this->get(route('parent.dashboard'))->assertRedirectToRoute('login');
});

it('forbids accounts of the other portals', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('parent.dashboard'))
        ->assertForbidden();
})->with([
    'administrator' => UserRole::Admin,
    'teacher' => UserRole::Teacher,
    'learner' => UserRole::Student,
]);

it('asks a parent without linked learners to contact the school', function () {
    $parent = User::factory()->parent()->create(['name' => 'Ana Navarro', 'email' => 'ana@family.test']);

    $this->actingAs($parent)
        ->get(route('parent.dashboard'))
        ->assertOk()
        ->assertViewIs('parent.dashboard')
        ->assertSeeText('Ask the school to link your child')
        ->assertSeeText('ana@family.test')
        ->assertDontSee('data-child-card', false)
        ->assertDontSee('data-week-highlights', false)
        ->assertDontSee('data-dashboard-feedback', false);
});

it('greets the parent by first name with the time of day', function (string $time, string $greeting) {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 '.$time));
    $parent = User::factory()->parent()->create(['name' => 'Ana Navarro']);

    $this->actingAs($parent)
        ->get(route('parent.dashboard'))
        ->assertOk()
        ->assertViewHas('greeting', $greeting.', Ana');
})->with([
    'morning' => ['08:30:00', 'Good morning'],
    'afternoon' => ['13:00:00', 'Good afternoon'],
    'evening' => ['19:45:00', 'Good evening'],
]);

it('shows a card with the progress of each linked child only', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    $sofia = dashboardChild('Sofia Navarro', GradeLevel::Five, ['total_points' => 1240, 'last_active_at' => now()->subHours(2)]);
    $miguel = dashboardChild('Miguel Navarro', GradeLevel::Six, ['total_points' => 0, 'last_active_at' => null, 'last_synced_at' => null]);
    $stranger = dashboardChild('Paolo Reyes', GradeLevel::Five);
    $parent = User::factory()->parent()
        ->guardianOf($sofia, GuardianRelationship::Mother)
        ->guardianOf($miguel, GuardianRelationship::Mother)
        ->create();
    User::factory()->parent()->guardianOf($stranger)->create();

    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five, 'section' => 'Sampaguita', 'name' => 'Health 5 – Sampaguita']);
    $classroom->students()->attach($sofia);
    $archived = Classroom::factory()->archived()->create(['grade_level' => GradeLevel::Five, 'section' => 'Rosal']);
    $archived->students()->attach($sofia);

    $plateQuiz = Quiz::factory()->published()->create();
    $snackQuiz = Quiz::factory()->published()->create();
    QuizAttempt::factory()->for($plateQuiz)->for($sofia, 'student')->create(['score' => 6, 'max_score' => 10, 'percentage' => 60, 'passed' => false]);
    QuizAttempt::factory()->for($plateQuiz)->for($sofia, 'student')->create(['attempt_number' => 2, 'score' => 8, 'max_score' => 10, 'percentage' => 80, 'passed' => true]);
    QuizAttempt::factory()->for($snackQuiz)->for($sofia, 'student')->create(['score' => 7, 'max_score' => 10, 'percentage' => 70, 'passed' => false]);
    QuizAttempt::factory()->inProgress()->for($snackQuiz)->for($sofia, 'student')->create(['attempt_number' => 2]);
    MissionProgress::factory()->completed()->count(2)->for($sofia, 'student')->create();
    MissionProgress::factory()->inProgress()->for($sofia, 'student')->create();

    $response = $this->actingAs($parent)->get(route('parent.dashboard'));

    $response->assertOk()
        ->assertSeeInOrder(['Miguel Navarro', 'Sofia Navarro'])
        ->assertDontSeeText('Paolo Reyes')
        ->assertSeeText('Grade 5 · Section Sampaguita')
        ->assertDontSeeText('Rosal')
        ->assertSeeText('Grade 6 · Not enrolled in a class yet')
        ->assertSeeText('Has not played NutriMind yet')
        ->assertSeeTextInOrder(['Average', 'Fairly Satisfactory'])
        ->assertSee('href="'.route('parent.children.show', $sofia).'"', false);
    $response->assertViewHas('cards', function (array $cards) use ($sofia, $miguel): bool {
        $byChild = collect($cards)->keyBy(fn (array $card): int => $card['student']->getKey());

        return $byChild->keys()->sort()->values()->all() === collect([$sofia->id, $miguel->id])->sort()->values()->all()
            && $byChild[$sofia->id]['stats']['totalPoints'] === 1240
            && $byChild[$sofia->id]['stats']['levelProgress'] === ['level' => 3, 'into' => 240, 'needed' => 500, 'percent' => 48.0]
            && $byChild[$sofia->id]['stats']['averageScore'] === 75.0
            && $byChild[$sofia->id]['stats']['quizzesTaken'] === 2
            && $byChild[$sofia->id]['stats']['missionsCompleted'] === 2
            && $byChild[$sofia->id]['stats']['lastActiveAt']->equalTo(now()->subHours(2))
            && $byChild[$miguel->id]['stats']['averageScore'] === null
            && $byChild[$miguel->id]['stats']['quizzesTaken'] === 0;
    });
});

it('flags a child whose game has not synced for more than a week', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    $stale = dashboardChild('Carlo Manalastas', GradeLevel::Five, ['last_active_at' => now()->subDays(10), 'last_synced_at' => now()->subDays(8)]);
    $fresh = dashboardChild('Sofia Navarro', GradeLevel::Five, ['last_active_at' => now()->subDay(), 'last_synced_at' => now()->subDay()]);
    $parent = User::factory()->parent()->guardianOf($stale)->guardianOf($fresh)->create();

    $response = $this->actingAs($parent)->get(route('parent.dashboard'));

    $response->assertOk()->assertSeeTextInOrder(['Carlo Manalastas', 'Sync overdue', 'Sofia Navarro']);
    $response->assertViewHas('cards', fn (array $cards): bool => $cards[0]['stats']['syncOverdue'] === true
        && $cards[1]['stats']['syncOverdue'] === false);
});

it('counts this week’s quizzes, rewards and missions since Monday', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    $sofia = dashboardChild('Sofia Navarro', GradeLevel::Five);
    $miguel = dashboardChild('Miguel Navarro', GradeLevel::Six);
    $parent = User::factory()->parent()->guardianOf($sofia)->guardianOf($miguel)->create();

    $quizA = Quiz::factory()->published()->create();
    $quizB = Quiz::factory()->published()->create();
    $quizC = Quiz::factory()->published()->create();
    QuizAttempt::factory()->for($quizA)->for($sofia, 'student')->create(['submitted_at' => '2026-11-09 00:00:00']);
    QuizAttempt::factory()->for($quizA)->for($sofia, 'student')->create(['attempt_number' => 2, 'submitted_at' => '2026-11-11 15:00:00']);
    QuizAttempt::factory()->for($quizB)->for($sofia, 'student')->create(['submitted_at' => '2026-11-12 09:00:00']);
    QuizAttempt::factory()->for($quizC)->for($sofia, 'student')->create(['submitted_at' => '2026-11-08 23:59:59']);
    QuizAttempt::factory()->inProgress()->for($quizC)->for($miguel, 'student')->create();
    QuizAttempt::factory()->for($quizC)->for($miguel, 'student')->create(['submitted_at' => '2026-11-13 08:00:00']);

    $sofia->rewards()->attach(Reward::factory()->create(), ['awarded_at' => '2026-11-10 12:00:00']);
    $sofia->rewards()->attach(Reward::factory()->create(), ['awarded_at' => '2026-11-06 12:00:00']);
    $miguel->rewards()->attach(Reward::factory()->create(), ['awarded_at' => '2026-11-12 18:00:00']);
    $miguel->rewards()->attach(Reward::factory()->create(), ['awarded_at' => '2026-11-13 07:00:00']);

    MissionProgress::factory()->completed()->for($miguel, 'student')->create(['completed_at' => '2026-11-11 10:00:00']);
    MissionProgress::factory()->completed()->for($miguel, 'student')->create(['completed_at' => '2026-11-07 10:00:00']);
    MissionProgress::factory()->inProgress()->for($sofia, 'student')->create();

    $response = $this->actingAs($parent)->get(route('parent.dashboard'));

    $response->assertOk()
        ->assertSeeText('Highlights since Monday, Nov 9.')
        ->assertSeeText('Miguel 1 · Sofia 2')
        ->assertSeeText('Miguel 2 · Sofia 1')
        ->assertSeeText('Miguel 1 · Sofia 0');
    $response->assertViewHas('highlights', fn (array $highlights): bool => $highlights['quizzes']->sortKeys()->all() === [$sofia->id => 2, $miguel->id => 1]
        && $highlights['rewards']->sortKeys()->all() === [$sofia->id => 1, $miguel->id => 2]
        && $highlights['missions']->all() === [$miguel->id => 1]);
});

it('tells the parent when nothing happened yet this week', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    $sofia = dashboardChild('Sofia Navarro', GradeLevel::Five);
    $parent = User::factory()->parent()->guardianOf($sofia)->create();
    QuizAttempt::factory()->for($sofia, 'student')->create(['submitted_at' => '2026-11-06 10:00:00']);

    $this->actingAs($parent)
        ->get(route('parent.dashboard'))
        ->assertOk()
        ->assertSeeText('Nothing yet this week');
});

it('lists the newest guardian-visible feedback and each child’s latest note', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    $sofia = dashboardChild('Sofia Navarro', GradeLevel::Five);
    $miguel = dashboardChild('Miguel Navarro', GradeLevel::Six);
    $stranger = dashboardChild('Paolo Reyes', GradeLevel::Five);
    $parent = User::factory()->parent()->guardianOf($sofia)->guardianOf($miguel)->create();
    $teacher = User::factory()->teacher()->create(['name' => 'Maricel Santos']);

    $older = LearnerFeedback::factory()->for($teacher, 'teacher')->for($sofia, 'student')->read()
        ->create(['message' => 'You remembered every Go food. Well done!', 'created_at' => now()->subDays(3)]);
    $newest = LearnerFeedback::factory()->for($teacher, 'teacher')->for($sofia, 'student')
        ->create(['message' => 'Please review the label lesson tonight.', 'created_at' => now()->subDay()]);
    LearnerFeedback::factory()->hiddenFromGuardians()->for($teacher, 'teacher')->for($sofia, 'student')
        ->create(['message' => 'Private note for the adviser only.', 'created_at' => now()->subHour()]);
    $miguelNote = LearnerFeedback::factory()->for($teacher, 'teacher')->for($miguel, 'student')
        ->create(['message' => 'Miguel improved a lot this week.', 'created_at' => now()->subDays(2)]);
    LearnerFeedback::factory()->for($teacher, 'teacher')->for($stranger, 'student')
        ->create(['message' => 'A note about another family.', 'created_at' => now()]);

    $response = $this->actingAs($parent)->get(route('parent.dashboard'));

    $response->assertOk()
        ->assertSeeText('2 new messages from teachers')
        ->assertSeeInOrder(['Please review the label lesson tonight.', 'Miguel improved a lot this week.', 'You remembered every Go food. Well done!'])
        ->assertSee('data-child-feedback="'.$newest->id.'"', false)
        ->assertSee('data-child-feedback="'.$miguelNote->id.'"', false)
        ->assertDontSee('data-child-feedback="'.$older->id.'"', false)
        ->assertDontSeeText('Private note for the adviser only.')
        ->assertDontSeeText('A note about another family.')
        ->assertSee('href="'.route('parent.children.show', [$sofia, 'tab' => 'feedback']).'"', false);
    $response->assertViewHas('recentFeedback', fn ($feedback): bool => $feedback->modelKeys() === [$newest->id, $miguelNote->id, $older->id]);
});

it('picks the higher ID as the latest note when two notes share a timestamp', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    $sofia = dashboardChild('Sofia Navarro', GradeLevel::Five);
    $parent = User::factory()->parent()->guardianOf($sofia)->create();
    $first = LearnerFeedback::factory()->for($sofia, 'student')->create(['created_at' => now()->subDay()]);
    $second = LearnerFeedback::factory()->for($sofia, 'student')->create(['created_at' => now()->subDay()]);

    $this->actingAs($parent)
        ->get(route('parent.dashboard'))
        ->assertOk()
        ->assertSee('data-child-feedback="'.$second->id.'"', false)
        ->assertDontSee('data-child-feedback="'.$first->id.'"', false);
});

it('hides teacher feedback from parents without the view teacher feedback permission', function () {
    $sofia = dashboardChild('Sofia Navarro', GradeLevel::Five);
    $parent = User::factory()->parent()->guardianOf($sofia)->create();
    LearnerFeedback::factory()->for($sofia, 'student')->create(['message' => 'Great effort on the plate quiz.']);
    app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewTeacherFeedback);

    $this->actingAs($parent)
        ->get(route('parent.dashboard'))
        ->assertOk()
        ->assertSeeText('Sofia Navarro')
        ->assertDontSeeText('Great effort on the plate quiz.')
        ->assertDontSee('data-dashboard-feedback', false)
        ->assertDontSeeText('new message from teachers');
});

it('shows the cards without progress when the school turned child progress off', function () {
    $sofia = dashboardChild('Sofia Navarro', GradeLevel::Five, ['total_points' => 1240]);
    $parent = User::factory()->parent()->guardianOf($sofia)->create();
    app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewChildProgress);

    $response = $this->actingAs($parent)->get(route('parent.dashboard'));

    $response->assertOk()
        ->assertSeeText('Sofia Navarro')
        ->assertSeeText('Progress details are turned off')
        ->assertDontSeeText('XP earned')
        ->assertDontSee('data-week-highlights', false)
        ->assertDontSee(route('parent.children.show', $sofia), false)
        ->assertDontSeeText('All children')
        ->assertDontSeeText('View progress');
    $response->assertViewHas('cards', fn (array $cards): bool => $cards[0]['stats'] === null);
});

it('lists the latest forum threads the parent may open', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    $parent = User::factory()->parent()->create();
    $teacher = User::factory()->teacher()->create(['name' => 'Maricel Santos']);
    $category = ForumCategory::factory()->create(['name' => 'Healthy recipes']);
    $hiddenCategory = ForumCategory::factory()->inactive()->create();

    $recipes = ForumThread::factory()->for($category, 'category')->for($teacher, 'author')
        ->create(['title' => 'Five-minute vegetable omelette', 'last_activity_at' => now()->subHour()]);
    ForumReply::factory()->count(2)->for($recipes, 'thread')->create();
    ForumReply::factory()->hidden()->for($recipes, 'thread')->create();
    $ownPending = ForumThread::factory()->pending()->for($category, 'category')->for($parent, 'author')
        ->create(['title' => 'How do I pack a balanced baon?', 'last_activity_at' => now()->subMinutes(5)]);
    ForumThread::factory()->pending()->for($category, 'category')->create(['title' => 'Someone else waiting for approval']);
    ForumThread::factory()->hidden()->for($category, 'category')->create(['title' => 'Hidden spam thread']);
    ForumThread::factory()->for($hiddenCategory, 'category')->create(['title' => 'Thread in a retired category']);

    $response = $this->actingAs($parent)->get(route('parent.dashboard'));

    $response->assertOk()
        ->assertSeeInOrder(['How do I pack a balanced baon?', 'Pending approval', 'Five-minute vegetable omelette', 'Maricel Santos', 'Teacher', '2 replies'])
        ->assertSee('href="'.route('forum.threads.show', $recipes).'"', false)
        ->assertSee('href="'.route('forum.index').'"', false)
        ->assertSee('href="'.route('forum.threads.create').'"', false)
        ->assertDontSeeText('Someone else waiting for approval')
        ->assertDontSeeText('Hidden spam thread')
        ->assertDontSeeText('Thread in a retired category');
    $response->assertViewHas('threads', fn ($threads): bool => $threads->modelKeys() === [$ownPending->id, $recipes->id]);
});

it('hides the forum section while the forum is turned off', function () {
    $parent = User::factory()->parent()->create();
    ForumThread::factory()->create(['title' => 'Five-minute vegetable omelette']);
    app(Settings::class)->set('forum_enabled', false);

    $this->actingAs($parent)
        ->get(route('parent.dashboard'))
        ->assertOk()
        ->assertDontSee('data-dashboard-forum', false)
        ->assertDontSeeText('Five-minute vegetable omelette')
        ->assertDontSee('href="'.route('forum.index').'"', false);
});

it('hides the forum section from parents without forum access', function () {
    $parent = User::factory()->parent()->create();
    ForumThread::factory()->create(['title' => 'Five-minute vegetable omelette']);
    app(RolePermissions::class)->revoke(UserRole::Parent, Permission::AccessForum);

    $this->actingAs($parent)
        ->get(route('parent.dashboard'))
        ->assertOk()
        ->assertDontSee('data-dashboard-forum', false)
        ->assertDontSeeText('Five-minute vegetable omelette')
        ->assertDontSee('href="'.route('forum.index').'"', false);
});

it('hides the new thread button from parents who may not post', function () {
    $parent = User::factory()->parent()->create();
    app(RolePermissions::class)->revoke(UserRole::Parent, Permission::PostInForum);

    $this->actingAs($parent)
        ->get(route('parent.dashboard'))
        ->assertOk()
        ->assertSee('data-dashboard-forum', false)
        ->assertDontSee('href="'.route('forum.threads.create').'"', false);
});

it('shows the school announcement', function () {
    app(Settings::class)->set('announcement', '  Parent–teacher conference on Nov 28.  ');
    $parent = User::factory()->parent()->create();

    $this->actingAs($parent)
        ->get(route('parent.dashboard'))
        ->assertOk()
        ->assertSee('data-dashboard-announcement', false)
        ->assertViewHas('schoolAnnouncement', 'Parent–teacher conference on Nov 28.');
});

it('leaves out the announcement block when there is none', function () {
    app(Settings::class)->set('announcement', '   ');

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('parent.dashboard'))
        ->assertOk()
        ->assertDontSee('data-dashboard-announcement', false)
        ->assertViewHas('schoolAnnouncement', null);
});

it('escapes feedback messages and thread titles', function () {
    $sofia = dashboardChild('Sofia Navarro', GradeLevel::Five);
    $parent = User::factory()->parent()->guardianOf($sofia)->create();
    LearnerFeedback::factory()->for($sofia, 'student')->create(['message' => '<script>alert("feedback")</script>']);
    ForumThread::factory()->create(['title' => '<img src=x onerror=alert(1)> recipes']);

    $this->actingAs($parent)
        ->get(route('parent.dashboard'))
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(&quot;feedback&quot;)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert("feedback")</script>', false)
        ->assertSee('&lt;img src=x onerror=alert(1)&gt; recipes', false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false);
});

it('runs the same number of queries however long the children’s history is', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    $sofia = dashboardChild('Sofia Navarro', GradeLevel::Five);
    $miguel = dashboardChild('Miguel Navarro', GradeLevel::Six);
    $parent = User::factory()->parent()->guardianOf($sofia)->guardianOf($miguel)->create();
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $classroom->students()->attach($sofia);

    $countQueries = function () use ($parent): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };
    $addHistory = function (int $times) use ($sofia, $miguel, $classroom): void {
        foreach ([$sofia, $miguel] as $child) {
            QuizAttempt::factory()->count($times)->for($child, 'student')->for($classroom)->create();
            LearnerFeedback::factory()->count($times)->for($child, 'student')->create();
            MissionProgress::factory()->completed()->count($times)->for($child, 'student')->create();
            $child->rewards()->attach(Reward::factory()->count($times)->create());
        }
        ForumThread::factory()->count($times)->create();
    };

    $addHistory(1);
    $before = $countQueries();
    $addHistory(4);

    expect($countQueries())->toBe($before);
});
