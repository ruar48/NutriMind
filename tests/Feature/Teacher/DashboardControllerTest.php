<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Policies\ClassroomPolicy;
use App\Services\RolePermissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Services\Analytics\AnalyticsSchoolFixture;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(AnalyticsSchoolFixture::NOW));
});

it('sends guests to the sign-in page', function () {
    $this->get(route('teacher.dashboard'))->assertRedirectToRoute('login');
});

it('forbids the other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('teacher.dashboard'))
        ->assertForbidden();
})->with([
    'administrator' => UserRole::Admin,
    'parent' => UserRole::Parent,
    'learner' => UserRole::Student,
]);

it('welcomes a teacher without subjects or classrooms with empty states', function () {
    $teacher = User::factory()->teacher()->create(['name' => 'Joy Villanueva']);

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertViewIs('teacher.dashboard.index')
        ->assertSeeText('Good morning, Joy Villanueva')
        ->assertSeeText(ClassroomPolicy::NO_SUBJECT_MESSAGE)
        ->assertSeeText('No classrooms yet')
        ->assertSeeText('Nothing due this week')
        ->assertSeeText('Everyone is on track')
        ->assertSeeText('No attempts yet')
        ->assertSeeText('Points appear here once learners play the game.')
        ->assertSee(route('teacher.subjects.index'), false)
        ->assertDontSee(route('teacher.classrooms.create'), false)
        ->assertDontSee(route('teacher.quizzes.create'), false);
});

it('shows only the teacher’s own classrooms, learners and results', function () {
    $school = AnalyticsSchoolFixture::create();

    $this->actingAs($school->teresa)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertViewHas('classrooms', fn ($classrooms): bool => $classrooms->modelKeys() === [$school->health5->id, $school->science5->id])
        ->assertViewHas('monthOverview', fn (array $overview): bool => $overview['learners'] === 3 && $overview['averageScore'] === 72.5 && $overview['averageScoreDelta'] === 32.5)
        ->assertViewHas('weekOverview', fn (array $overview): bool => $overview['missionsCompleted'] === 1)
        ->assertViewHas('atRisk', fn (array $atRisk): bool => array_map(fn (array $entry): int => $entry['student']->id, $atRisk['learners']) === [$school->ben->id, $school->ella->id])
        ->assertViewHas('leaderboard', fn (array $leaderboard): bool => array_map(fn (array $entry): int => $entry['student']->id, $leaderboard) === [$school->ana->id, $school->ben->id])
        ->assertViewHas('recentSubmissions', fn ($attempts): bool => $attempts->pluck('student_id')->unique()->sort()->values()->all() === collect([$school->ana->id, $school->ben->id])->sort()->values()->all())
        ->assertSee('data-insights-classroom="'.$school->health5->id.'"', false)
        ->assertSee(route('teacher.classrooms.show', $school->health5), false)
        ->assertSee(route('teacher.students.show', $school->ben), false)
        ->assertSeeText('Healthy Plate Check')
        ->assertSeeText('72.5%')
        ->assertDontSeeText($school->carlo->name)
        ->assertDontSeeText($school->health6->name)
        ->assertDontSeeText($school->dina->name);
});

it('links only the attempts at the teacher’s own quizzes', function () {
    $school = AnalyticsSchoolFixture::create();
    $own = QuizAttempt::query()->whereBelongsTo($school->plateQuiz)->whereBelongsTo($school->ben, 'student')->sole();
    $foreignQuiz = Quiz::factory()->published()->for($school->ramon, 'teacher')->for($school->health)->create(['title' => 'Guest Quiz']);
    $foreign = QuizAttempt::factory()->for($foreignQuiz)->for($school->ben, 'student')->for($school->health5)->create(['submitted_at' => now()->subMinute()]);

    $this->actingAs($school->teresa)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSeeText('Guest Quiz')
        ->assertSee(route('teacher.attempts.show', $own), false)
        ->assertDontSee(route('teacher.attempts.show', $foreign), false);
});

it('shows the quizzes due this week and soon with their submissions', function () {
    $school = AnalyticsSchoolFixture::create();
    $school->health5->quizzes()->updateExistingPivot($school->plateQuiz->id, ['due_at' => '2026-11-15 17:00:00']);

    $this->actingAs($school->teresa)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertSee('data-insights-due-this-week="1"', false)
        ->assertSeeText('1 quiz due this week')
        ->assertSee('data-insights-due="'.$school->plateQuiz->id.'"', false)
        ->assertSeeText('Sun, Nov 15')
        ->assertSeeText('2 of 3');
});

it('offers only the quick actions the teacher may use', function () {
    $school = AnalyticsSchoolFixture::create();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes, Permission::ViewAnalytics);

    $this->actingAs($school->teresa)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertDontSee(route('teacher.quizzes.create'), false)
        ->assertDontSee(route('teacher.analytics.index'), false)
        ->assertSee(route('teacher.classrooms.create'), false)
        ->assertSee(route('teacher.question-bank.index'), false);
});

it('runs the same number of queries however many classrooms the teacher has', function () {
    $school = AnalyticsSchoolFixture::create();
    $school->health5->quizzes()->updateExistingPivot($school->plateQuiz->id, ['due_at' => now()->addDay()]);

    $countQueries = function () use ($school): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($school->teresa)->get(route('teacher.dashboard'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $countQueries();
    $before = $countQueries();

    foreach (range(1, 3) as $extra) {
        $classroom = Classroom::factory()->for($school->teresa, 'teacher')->for($school->health)->create();
        $learner = User::factory()->student()->enrolledIn($classroom)->create();
        $classroom->quizzes()->attach($school->plateQuiz, ['due_at' => now()->addDays(2)]);
        QuizAttempt::factory()->for($school->plateQuiz)->for($learner, 'student')->for($classroom)->create(['submitted_at' => now()->subHour()]);
    }
    $school->teresa->refresh();

    expect($countQueries())->toBe($before);
});
