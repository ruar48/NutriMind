<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use App\Policies\QuizPolicy;
use App\Services\CsvExport;
use App\Services\RolePermissions;
use Carbon\CarbonImmutable;

/**
 * A published quiz assigned to two classrooms, with one attempt in each and an unsubmitted one.
 *
 * @return array{teacher: User, subject: Subject, quiz: Quiz, sampaguita: Classroom, ilangIlang: Classroom}
 */
function quizExportSetup(): array
{
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $sampaguita = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'name' => 'Health 5 – Sampaguita']);
    $ilangIlang = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'name' => 'Health 5 – Ilang-Ilang']);
    $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['title' => 'Go Grow Glow Quiz', 'passing_score' => 75]);

    $ana = User::factory()->student(GradeLevel::Five)->enrolledIn($sampaguita)->create(['name' => 'Ana Cruz', 'username' => 'ana_cruz']);
    $ana->studentProfile->forceFill(['lrn' => '136742210001'])->save();
    $ben = User::factory()->student(GradeLevel::Five)->enrolledIn($ilangIlang)->create(['name' => '=HYPERLINK("x")', 'username' => 'ben_reyes']);
    $ben->studentProfile->forceFill(['lrn' => null])->save();

    QuizAttempt::factory()->for($quiz)->for($ana, 'student')->for($sampaguita)->create([
        'attempt_number' => 1,
        'score' => 9,
        'max_score' => 10,
        'percentage' => 90,
        'passed' => true,
        'duration_seconds' => 305,
        'started_at' => '2026-11-10 08:00:00',
        'submitted_at' => '2026-11-10 08:05:05',
        'is_offline' => true,
        'synced_at' => '2026-11-11 07:30:00',
    ]);
    QuizAttempt::factory()->for($quiz)->for($ben, 'student')->for($ilangIlang)->create([
        'attempt_number' => 1,
        'score' => 6,
        'max_score' => 10,
        'percentage' => 60,
        'passed' => false,
        'duration_seconds' => 400,
        'started_at' => '2026-11-12 09:00:00',
        'submitted_at' => '2026-11-12 09:06:40',
        'is_offline' => false,
        'synced_at' => '2026-11-12 09:06:40',
    ]);
    QuizAttempt::factory()->inProgress()->for($quiz)->for($ben, 'student')->for($ilangIlang)->create(['attempt_number' => 2]);

    return compact('teacher', 'subject', 'quiz', 'sampaguita', 'ilangIlang');
}

it('sends guests to the sign-in page', function () {
    $this->get(route('teacher.quizzes.results.export', Quiz::factory()->create()))->assertRedirectToRoute('login');
});

it('forbids other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('teacher.quizzes.results.export', Quiz::factory()->create()))
        ->assertForbidden();
})->with([
    'administrator' => UserRole::Admin,
    'parent' => UserRole::Parent,
]);

it('reports another teacher’s quiz as missing', function () {
    $data = quizExportSetup();

    $this->actingAs(User::factory()->teaching($data['subject'])->create())
        ->get(route('teacher.quizzes.results.export', $data['quiz']))
        ->assertNotFound();
});

it('refuses a quiz whose subject was revoked', function () {
    $quiz = Quiz::factory()->published()->revokedSubject()->create();

    $this->actingAs($quiz->teacher)
        ->get(route('teacher.quizzes.results.export', $quiz))
        ->assertForbidden()
        ->assertSeeText(QuizPolicy::SUBJECT_REVOKED_MESSAGE);
});

it('refuses teachers without the export reports permission', function () {
    $data = quizExportSetup();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ExportReports);

    $this->actingAs($data['teacher'])
        ->get(route('teacher.quizzes.results.export', $data['quiz']))
        ->assertForbidden()
        ->assertSeeText(RolePermissions::deniedMessage(Permission::ExportReports));
});

it('downloads the submitted attempts as CSV, oldest first', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    $data = quizExportSetup();

    $response = $this->actingAs($data['teacher'])->get(route('teacher.quizzes.results.export', $data['quiz']));

    $response->assertOk()->assertDownload('go-grow-glow-quiz-results-2026-11-13.csv');
    expect($response->streamedContent())->toBe(CsvExport::BYTE_ORDER_MARK.implode("\r\n", [
        'Learner,Username,LRN,Classroom,Attempt,Score,"Max score",Percentage,"Performance band",Passed,"Duration (m:ss)","Duration (seconds)",Started,Submitted,"Played offline",Synced',
        '"Ana Cruz",ana_cruz,136742210001,"Health 5 – Sampaguita",1,9,10,90,Outstanding,Yes,5:05,305,"2026-11-10 08:00:00","2026-11-10 08:05:05",Yes,"2026-11-11 07:30:00"',
        '"\'=HYPERLINK(""x"")",ben_reyes,,"Health 5 – Ilang-Ilang",1,6,10,60,"Did Not Meet Expectations",No,6:40,400,"2026-11-12 09:00:00","2026-11-12 09:06:40",No,"2026-11-12 09:06:40"',
        '',
    ]));
});

it('exports only the chosen classroom’s attempts', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    $data = quizExportSetup();

    $response = $this->actingAs($data['teacher'])->get(route('teacher.quizzes.results.export', [$data['quiz'], 'classroom' => $data['sampaguita']->id]));

    $response->assertOk()->assertDownload('go-grow-glow-quiz-results-health-5-sampaguita-2026-11-13.csv');
    $content = $response->streamedContent();
    expect($content)->toContain('ana_cruz')->not->toContain('ben_reyes');
});

it('reports a classroom the quiz is not assigned to as missing', function () {
    $data = quizExportSetup();

    $this->actingAs($data['teacher'])
        ->get(route('teacher.quizzes.results.export', [$data['quiz'], 'classroom' => Classroom::factory()->create()->id]))
        ->assertNotFound();
});
