<?php

use App\Enums\GuardianRelationship;
use App\Models\AcademicTerm;
use App\Models\ActivityProgress;
use App\Models\Classroom;
use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\LearnerFeedback;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\Reward;
use App\Models\SchoolYear;
use App\Models\Setting;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

it('builds a teacher, classroom, learners, quiz, attempt and guardian that fit together', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create();
    $students = User::factory()->student($classroom->grade_level)->enrolledIn($classroom)->count(3)->create();
    $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->hasQuestions(5)->create();
    $classroom->quizzes()->attach($quiz, ['due_at' => now()->addWeek()]);
    $attempt = QuizAttempt::factory()->passed()->for($quiz)->for($students[0], 'student')->for($classroom)->create();
    $guardian = User::factory()->parent()->guardianOf($students[0], GuardianRelationship::Mother)->create();

    expect(Classroom::query()->accessibleBy($teacher)->pluck('id')->all())->toBe([$classroom->id]);
    expect($classroom->students()->count())->toBe(3);
    expect($students->every(fn (User $student): bool => $student->studentProfile->grade_level === $classroom->grade_level))->toBeTrue();
    expect($attempt->max_score)->toBe(5);
    expect($attempt->passed)->toBeTrue();
    expect($classroom->quizzes()->sole()->pivot->due_at)->not->toBeNull();
    expect($guardian->linkedStudents()->sole()->is($students[0]))->toBeTrue();
});

it('persists a valid model for every factory state', function (Factory $factory) {
    $models = $factory->count(2)->create();

    expect($models)->toHaveCount(2);
    $models->each(fn (Model $model) => $this->assertModelExists($model));
})->with([
    'user' => fn () => User::factory(),
    'user admin' => fn () => User::factory()->admin(),
    'user teacher' => fn () => User::factory()->teacher(),
    'user parent' => fn () => User::factory()->parent(),
    'user student' => fn () => User::factory()->student(),
    'user student without profile' => fn () => User::factory()->studentWithoutProfile(),
    'user inactive' => fn () => User::factory()->inactive(),
    'user unverified' => fn () => User::factory()->unverified(),
    'user teaching' => fn () => User::factory()->teaching(),
    'user enrolled in' => fn () => User::factory()->student()->enrolledIn(Classroom::factory()->create()),
    'user guardian of' => fn () => User::factory()->parent()->guardianOf(User::factory()->student()->create()),
    'student profile' => fn () => StudentProfile::factory(),
    'school year' => fn () => SchoolYear::factory(),
    'school year active' => fn () => SchoolYear::factory()->active(),
    'academic term' => fn () => AcademicTerm::factory(),
    'academic term active' => fn () => AcademicTerm::factory()->active(),
    'subject' => fn () => Subject::factory(),
    'subject inactive' => fn () => Subject::factory()->inactive(),
    'classroom' => fn () => Classroom::factory(),
    'classroom archived' => fn () => Classroom::factory()->archived(),
    'classroom revoked subject' => fn () => Classroom::factory()->revokedSubject(),
    'learning module' => fn () => LearningModule::factory(),
    'learning module unpublished' => fn () => LearningModule::factory()->unpublished(),
    'learning activity' => fn () => LearningActivity::factory(),
    'learning activity unpublished' => fn () => LearningActivity::factory()->unpublished(),
    'mission' => fn () => Mission::factory(),
    'mission inactive' => fn () => Mission::factory()->inactive(),
    'mission progress' => fn () => MissionProgress::factory(),
    'mission progress completed' => fn () => MissionProgress::factory()->completed(),
    'mission progress in progress' => fn () => MissionProgress::factory()->inProgress(),
    'activity progress' => fn () => ActivityProgress::factory(),
    'activity progress completed' => fn () => ActivityProgress::factory()->completed(),
    'reward' => fn () => Reward::factory(),
    'reward inactive' => fn () => Reward::factory()->inactive(),
    'lesson plan' => fn () => LessonPlan::factory(),
    'lesson plan revoked subject' => fn () => LessonPlan::factory()->revokedSubject(),
    'quiz' => fn () => Quiz::factory(),
    'quiz published' => fn () => Quiz::factory()->published(),
    'quiz closed' => fn () => Quiz::factory()->closed(),
    'quiz reviewed' => fn () => Quiz::factory()->reviewed(),
    'quiz revoked subject' => fn () => Quiz::factory()->revokedSubject(),
    'quiz question' => fn () => QuizQuestion::factory(),
    'quiz question multiple choice' => fn () => QuizQuestion::factory()->multipleChoice(),
    'quiz question true or false' => fn () => QuizQuestion::factory()->trueFalse(),
    'quiz question identification' => fn () => QuizQuestion::factory()->identification(),
    'question bank item' => fn () => QuestionBankItem::factory(),
    'question bank item shared' => fn () => QuestionBankItem::factory()->shared(),
    'question bank item reviewed' => fn () => QuestionBankItem::factory()->reviewed(),
    'question bank item multiple choice' => fn () => QuestionBankItem::factory()->multipleChoice(),
    'question bank item true or false' => fn () => QuestionBankItem::factory()->trueFalse(),
    'question bank item identification' => fn () => QuestionBankItem::factory()->identification(),
    'question bank item revoked subject' => fn () => QuestionBankItem::factory()->revokedSubject(),
    'quiz attempt' => fn () => QuizAttempt::factory(),
    'quiz attempt passed' => fn () => QuizAttempt::factory()->passed(),
    'quiz attempt failed' => fn () => QuizAttempt::factory()->failed(),
    'quiz attempt offline' => fn () => QuizAttempt::factory()->offline(),
    'quiz attempt in progress' => fn () => QuizAttempt::factory()->inProgress(),
    'quiz attempt in a classroom' => fn () => QuizAttempt::factory()->for(Classroom::factory()->create()),
    'quiz attempt answer' => fn () => QuizAttemptAnswer::factory(),
    'quiz attempt answer correct' => fn () => QuizAttemptAnswer::factory()->correct(),
    'quiz attempt answer incorrect' => fn () => QuizAttemptAnswer::factory()->incorrect(),
    'learner feedback' => fn () => LearnerFeedback::factory(),
    'learner feedback hidden from guardians' => fn () => LearnerFeedback::factory()->hiddenFromGuardians(),
    'learner feedback read' => fn () => LearnerFeedback::factory()->read(),
    'forum category' => fn () => ForumCategory::factory(),
    'forum category inactive' => fn () => ForumCategory::factory()->inactive(),
    'forum thread' => fn () => ForumThread::factory(),
    'forum thread pinned' => fn () => ForumThread::factory()->pinned(),
    'forum thread locked' => fn () => ForumThread::factory()->locked(),
    'forum thread hidden' => fn () => ForumThread::factory()->hidden(),
    'forum thread pending' => fn () => ForumThread::factory()->pending(),
    'forum reply' => fn () => ForumReply::factory(),
    'forum reply hidden' => fn () => ForumReply::factory()->hidden(),
    'forum report' => fn () => ForumReport::factory(),
    'forum report resolved' => fn () => ForumReport::factory()->resolved(),
    'forum report about a reply' => fn () => ForumReport::factory()->for(ForumReply::factory()->create(), 'reply'),
    'setting' => fn () => Setting::factory(),
]);
