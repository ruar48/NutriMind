<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\User;
use App\Policies\QuizPolicy;
use App\Services\RolePermissions;

/**
 * A published Health quiz (passing score 75) assigned to Sampaguita (Ana, Ben, Carlo) and Ilang-Ilang (Dina).
 * Ana scored 60 (offline) then 90, Ben 70 and Dina 100; Carlo has not attempted. One multiple choice question:
 * Ana's first attempt and Ben chose "Glow", the others chose the correct "Grow".
 *
 * @return array{teacher: User, subject: Subject, quiz: Quiz, sampaguita: Classroom, ilangIlang: Classroom, question: QuizQuestion, attempts: array<string, QuizAttempt>, learners: array<string, User>}
 */
function quizResultsPageSetup(): array
{
    $subject = Subject::factory()->create(['name' => 'Health']);
    $teacher = User::factory()->teaching($subject)->create();
    $sampaguita = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'name' => 'Health 5 – Sampaguita']);
    $ilangIlang = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'name' => 'Health 5 – Ilang-Ilang']);
    $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['title' => 'Go Grow Glow Quiz', 'grade_level' => GradeLevel::Five, 'passing_score' => 75]);
    $quiz->classrooms()->attach([$sampaguita->id, $ilangIlang->id]);
    $question = QuizQuestion::factory()->for($quiz)->create([
        'type' => QuestionType::MultipleChoice,
        'prompt' => 'Which food group helps you grow?',
        'choices' => ['Go', 'Grow', 'Glow', 'Fats'],
        'correct_answer' => '1',
        'points' => 1,
    ]);

    $learners = [];
    foreach (['Ana Cruz', 'Ben Reyes', 'Carlo Diaz'] as $name) {
        $learners[$name] = User::factory()->student(GradeLevel::Five)->enrolledIn($sampaguita)->create(['name' => $name]);
    }
    $learners['Dina Lim'] = User::factory()->student(GradeLevel::Five)->enrolledIn($ilangIlang)->create(['name' => 'Dina Lim']);

    $attempt = function (string $name, Classroom $classroom, float $percentage, string $answer, array $attributes = []) use ($quiz, $question, $learners): QuizAttempt {
        $attempt = QuizAttempt::factory()->for($quiz)->for($learners[$name], 'student')->for($classroom)->create([
            'score' => (int) round($percentage),
            'max_score' => 100,
            'percentage' => $percentage,
            'passed' => $percentage >= 75,
            'duration_seconds' => 300,
        ] + $attributes);
        QuizAttemptAnswer::factory()->for($attempt, 'attempt')->for($question, 'question')->create([
            'answer' => $answer,
            'is_correct' => $answer === '1',
            'points_awarded' => $answer === '1' ? 1 : 0,
        ]);

        return $attempt;
    };

    $attempts = [
        'ana-first' => $attempt('Ana Cruz', $sampaguita, 60, '2', ['is_offline' => true, 'submitted_at' => now()->subDays(3), 'synced_at' => now()->subDays(2)]),
        'ana-second' => $attempt('Ana Cruz', $sampaguita, 90, '1', ['attempt_number' => 2, 'submitted_at' => now()->subDays(2)]),
        'ben' => $attempt('Ben Reyes', $sampaguita, 70, '2', ['submitted_at' => now()->subDay()]),
        'dina' => $attempt('Dina Lim', $ilangIlang, 100, '1', ['submitted_at' => now()->subHour()]),
    ];

    return compact('teacher', 'subject', 'quiz', 'sampaguita', 'ilangIlang', 'question', 'attempts', 'learners');
}

it('sends guests to the sign-in page', function () {
    $this->get(route('teacher.quizzes.results.show', Quiz::factory()->create()))->assertRedirectToRoute('login');
});

it('forbids other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('teacher.quizzes.results.show', Quiz::factory()->create()))
        ->assertForbidden();
})->with([
    'administrator' => UserRole::Admin,
    'parent' => UserRole::Parent,
]);

it('reports another teacher’s quiz as missing', function () {
    $data = quizResultsPageSetup();

    $this->actingAs(User::factory()->teaching($data['subject'])->create())
        ->get(route('teacher.quizzes.results.show', $data['quiz']))
        ->assertNotFound();
});

it('refuses the results of a quiz whose subject was revoked', function () {
    $quiz = Quiz::factory()->published()->revokedSubject()->create();

    $this->actingAs($quiz->teacher)
        ->get(route('teacher.quizzes.results.show', $quiz))
        ->assertForbidden()
        ->assertSeeText(QuizPolicy::SUBJECT_REVOKED_MESSAGE);
});

it('shows the headline figures, distribution, attempts, missing learners and item analysis', function () {
    $data = quizResultsPageSetup();

    $response = $this->actingAs($data['teacher'])->get(route('teacher.quizzes.results.show', $data['quiz']));

    $response->assertOk()->assertViewIs('teacher.quizzes.results.show');
    $summary = $response->viewData('summary');
    expect($summary->assignedLearners)->toBe(4)
        ->and($summary->attemptedLearners)->toBe(3)
        ->and($summary->completionRate)->toBe(75.0)
        ->and($summary->averageScore)->toBe(86.7)
        ->and($summary->passRate)->toBe(66.7)
        ->and($summary->offlineSubmissions)->toBe(1)
        ->and($summary->notAttempted->pluck('name')->all())->toBe(['Carlo Diaz']);
    $response->assertSeeText('86.7%')
        ->assertSeeText('66.7%')
        ->assertSeeText('5:00')
        ->assertSee('data-chart="histogram"', false)
        ->assertSeeText('Carlo Diaz')
        ->assertSeeText('Offline · synced')
        ->assertSee('id="item-analysis"', false)
        ->assertSee('id="question-'.$data['question']->id.'"', false)
        ->assertSeeText('C. Glow')
        ->assertSeeText('Chosen in 2 attempts')
        ->assertSeeText('Moderate')
        ->assertSee(route('teacher.attempts.show', $data['attempts']['dina']), false)
        ->assertSee(route('teacher.quizzes.results.export', $data['quiz']), false);
});

it('limits every figure to the chosen classroom', function () {
    $data = quizResultsPageSetup();

    $response = $this->actingAs($data['teacher'])->get(route('teacher.quizzes.results.show', [$data['quiz'], 'classroom' => $data['ilangIlang']->id]));

    $summary = $response->viewData('summary');
    expect($summary->assignedLearners)->toBe(1)
        ->and($summary->averageScore)->toBe(100.0)
        ->and($summary->notAttempted)->toBeEmpty()
        ->and($response->viewData('attempts')->pluck('id')->all())->toBe([$data['attempts']['dina']->id]);
    $response->assertSeeText('Everyone has submitted')
        ->assertSee(route('teacher.quizzes.results.export', [$data['quiz'], 'classroom' => $data['ilangIlang']->id]), false);
});

it('reports a classroom the quiz is not assigned to as missing', function () {
    $data = quizResultsPageSetup();
    $unassigned = Classroom::factory()->for($data['teacher'], 'teacher')->for($data['subject'])->create();

    $this->actingAs($data['teacher'])
        ->get(route('teacher.quizzes.results.show', [$data['quiz'], 'classroom' => $unassigned->id]))
        ->assertNotFound();
});

it('sorts the attempts by date or score', function (array $query, array $expected) {
    $data = quizResultsPageSetup();

    $response = $this->actingAs($data['teacher'])->get(route('teacher.quizzes.results.show', [$data['quiz'], ...$query]));

    expect($response->viewData('attempts')->map(fn (QuizAttempt $attempt): string => array_search($attempt->id, array_map(fn (QuizAttempt $a): int => $a->id, $data['attempts']), true))->all())
        ->toBe($expected);
})->with([
    'newest first by default' => [[], ['dina', 'ben', 'ana-second', 'ana-first']],
    'oldest first' => [['sort' => 'date', 'direction' => 'asc'], ['ana-first', 'ana-second', 'ben', 'dina']],
    'highest score first' => [['sort' => 'score', 'direction' => 'desc'], ['dina', 'ana-second', 'ben', 'ana-first']],
    'lowest score first' => [['sort' => 'score', 'direction' => 'asc'], ['ana-first', 'ben', 'ana-second', 'dina']],
]);

it('paginates the attempts 20 per page', function () {
    $data = quizResultsPageSetup();
    QuizAttempt::factory()->count(17)->for($data['quiz'])->for($data['sampaguita'])->create(['submitted_at' => now()->subWeek()]);

    $response = $this->actingAs($data['teacher'])->get(route('teacher.quizzes.results.show', [$data['quiz'], 'page' => 2]));

    expect($response->viewData('attempts')->items())->toHaveCount(1)
        ->and($response->viewData('attempts')->total())->toBe(21);
});

it('links only learners the teacher can still open', function () {
    $data = quizResultsPageSetup();
    $data['sampaguita']->students()->detach($data['learners']['Ben Reyes']);

    $response = $this->actingAs($data['teacher'])->get(route('teacher.quizzes.results.show', $data['quiz']));

    $response->assertSee(route('teacher.students.show', $data['learners']['Dina Lim']), false)
        ->assertDontSee(route('teacher.students.show', $data['learners']['Ben Reyes']), false)
        ->assertSeeText('Ben Reyes');
});

it('hides the export button without the export reports permission', function () {
    $data = quizResultsPageSetup();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ExportReports);

    $this->actingAs($data['teacher'])
        ->get(route('teacher.quizzes.results.show', $data['quiz']))
        ->assertOk()
        ->assertDontSee(route('teacher.quizzes.results.export', $data['quiz']), false);
});

it('explains a quiz without classrooms, attempts or questions', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $quiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create();

    $this->actingAs($teacher)
        ->get(route('teacher.quizzes.results.show', $quiz))
        ->assertOk()
        ->assertSeeText('Not assigned to a classroom')
        ->assertSeeText('No submissions yet')
        ->assertSeeText('No assigned learners')
        ->assertSeeText('No questions yet')
        ->assertSee('data-chart-empty', false);
});
