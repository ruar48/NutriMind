<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\User;
use App\Services\Analytics\AnalyticsScope;
use App\Services\RolePermissions;
use Carbon\CarbonImmutable;
use Tests\Feature\Services\Analytics\AnalyticsSchoolFixture;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse(AnalyticsSchoolFixture::NOW));
});

it('sends guests to the sign-in page', function () {
    $this->get(route('teacher.analytics.index'))->assertRedirectToRoute('login');
});

it('forbids the other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('teacher.analytics.index'))
        ->assertForbidden();
})->with([
    'administrator' => UserRole::Admin,
    'parent' => UserRole::Parent,
    'learner' => UserRole::Student,
]);

it('forbids teachers whose analytics permission was revoked', function () {
    $teacher = User::factory()->teaching()->create();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ViewAnalytics);

    $this->actingAs($teacher)
        ->get(route('teacher.analytics.index', ['classroom' => 'forged']))
        ->assertForbidden()
        ->assertSeeText('You do not have permission to view learning analytics.')
        ->assertSessionHasNoErrors();
});

it('invites a teacher without classrooms to set one up', function () {
    $this->actingAs(User::factory()->teaching()->create())
        ->get(route('teacher.analytics.index'))
        ->assertOk()
        ->assertViewIs('teacher.analytics.index')
        ->assertSeeText('No classrooms to analyze yet')
        ->assertSee(route('teacher.classrooms.index'), false)
        ->assertDontSee('data-insights-kpis', false);
});

it('shows empty states when the teacher’s learners have no activity in the period', function () {
    $classroom = Classroom::factory()->create();
    User::factory()->student()->enrolledIn($classroom)->create();

    $this->actingAs($classroom->teacher)
        ->get(route('teacher.analytics.index'))
        ->assertOk()
        ->assertSee('data-insights-kpis', false)
        ->assertSeeText('No learning activity in this view')
        ->assertSeeText('No quiz results in this period yet.')
        ->assertSeeText('No tricky questions yet');
});

it('analyzes only the teacher’s own classrooms', function () {
    $school = AnalyticsSchoolFixture::create();

    $this->actingAs($school->teresa)
        ->get(route('teacher.analytics.index'))
        ->assertOk()
        ->assertViewHas('scope', fn (AnalyticsScope $scope): bool => $scope->classroomIds === [$school->health5->id, $school->science5->id])
        ->assertViewHas('overview', fn (array $overview): bool => $overview['averageScore'] === 72.5 && $overview['learners'] === 3 && $overview['averageScoreDelta'] === 32.5)
        ->assertViewHas('bands', fn (array $bands): bool => $bands['very_satisfactory'] === 1 && $bands['did_not_meet_expectations'] === 1 && $bands['no_data'] === 1)
        ->assertSeeText('Which food is a GO food?')
        ->assertSee(route('teacher.quizzes.results.show', $school->plateQuiz), false)
        ->assertSee(route('teacher.students.show', $school->ben), false)
        ->assertSee(route('teacher.classrooms.show', $school->health5), false)
        ->assertSeeText('The Healthy Plate')
        ->assertDontSeeText('Smart Snacks')
        ->assertDontSeeText($school->carlo->name)
        ->assertDontSeeText($school->dina->name);
});

it('shows another teacher only their own learners', function () {
    $school = AnalyticsSchoolFixture::create();

    $this->actingAs($school->ramon)
        ->get(route('teacher.analytics.index'))
        ->assertOk()
        ->assertViewHas('overview', fn (array $overview): bool => $overview['averageScore'] === 85.0 && $overview['learners'] === 1)
        ->assertSeeText($school->carlo->name)
        ->assertDontSeeText($school->ana->name)
        ->assertDontSeeText('Which food is a GO food?');
});

it('narrows the analytics to one of the teacher’s classrooms or subjects', function (string $filter) {
    $school = AnalyticsSchoolFixture::create();
    $query = $filter === 'classroom' ? ['classroom' => $school->science5->id] : ['subject' => $school->science->id];

    $this->actingAs($school->teresa)
        ->get(route('teacher.analytics.index', $query))
        ->assertOk()
        ->assertViewHas('scope', fn (AnalyticsScope $scope): bool => $scope->classroomIds === [$school->science5->id])
        ->assertViewHas('overview', fn (array $overview): bool => $overview['averageScore'] === 65.0)
        ->assertDontSeeText('Which food is a GO food?');
})->with(['classroom', 'subject']);

it('analyzes one of the teacher’s archived classrooms when it is chosen', function () {
    $classroom = Classroom::factory()->archived()->create(['name' => 'Health 5 – Old']);
    $student = User::factory()->student()->enrolledIn($classroom)->create();
    $quiz = Quiz::factory()->published()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create();
    QuizAttempt::factory()->for($quiz)->for($student, 'student')->for($classroom)->create(['percentage' => 88, 'passed' => true, 'submitted_at' => now()->subDay()]);

    $this->actingAs($classroom->teacher)
        ->get(route('teacher.analytics.index', ['classroom' => $classroom->id]))
        ->assertOk()
        ->assertViewHas('overview', fn (array $overview): bool => $overview['averageScore'] === 88.0)
        ->assertSeeText('Health 5 – Old (archived)');
});

it('refuses classrooms and subjects outside the teacher’s access', function (string $field, Closure $value, string $message) {
    $school = AnalyticsSchoolFixture::create();

    $this->actingAs($school->teresa)
        ->get(route('teacher.analytics.index', [$field => $value($school)]))
        ->assertRedirectToRoute('teacher.analytics.index')
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'another teacher’s classroom' => ['classroom', fn (AnalyticsSchoolFixture $school): int => $school->health6->id, 'Choose one of your classrooms.'],
    'an own classroom of a revoked subject' => ['classroom', fn (AnalyticsSchoolFixture $school): int => Classroom::factory()->for($school->teresa, 'teacher')->revokedSubject()->create()->id, 'Choose one of your classrooms.'],
    'a classroom that is not a number' => ['classroom', fn (): string => 'all', 'Choose one of your classrooms.'],
    'a subject that is not assigned' => ['subject', fn (): int => Subject::factory()->create()->id, 'Choose one of your subjects.'],
    'a subject that is not a number' => ['subject', fn (): string => 'health', 'Choose one of your subjects.'],
    'an unknown period' => ['period', fn (): string => 'forever', 'Choose a period from the list.'],
]);

it('never reveals the forged classroom’s data on the page it falls back to', function () {
    $school = AnalyticsSchoolFixture::create();

    $this->actingAs($school->teresa)
        ->followingRedirects()
        ->get(route('teacher.analytics.index', ['classroom' => $school->health6->id]))
        ->assertOk()
        ->assertSeeText('Some filters were not valid, so the default view is shown')
        ->assertSeeText('Choose one of your classrooms.')
        ->assertDontSeeText($school->carlo->name)
        ->assertDontSeeText('Smart Snacks');
});

it('links the item analysis only for the teacher’s own quizzes', function () {
    $school = AnalyticsSchoolFixture::create();
    $guestQuiz = Quiz::factory()->published()->for($school->ramon, 'teacher')->for($school->health)->create(['title' => 'Guest Quiz']);
    $question = QuizQuestion::factory()->for($guestQuiz)->create(['prompt' => 'Which drink has the most sugar?', 'choices' => ['Water', 'Soda'], 'correct_answer' => '1']);

    foreach ([$school->ana, $school->ben, $school->ella] as $learner) {
        $attempt = QuizAttempt::factory()->for($guestQuiz)->for($learner, 'student')->for($school->health5)->create(['submitted_at' => now()->subDay()]);
        QuizAttemptAnswer::factory()->for($attempt, 'attempt')->for($question, 'question')->create(['answer' => '0', 'is_correct' => false]);
    }

    $this->actingAs($school->teresa)
        ->get(route('teacher.analytics.index'))
        ->assertOk()
        ->assertSeeText('Which drink has the most sugar?')
        ->assertSee(route('teacher.quizzes.results.show', $school->plateQuiz), false)
        ->assertDontSee(route('teacher.quizzes.results.show', $guestQuiz), false);
});
