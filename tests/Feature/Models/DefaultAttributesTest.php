<?php

use App\Models\AcademicTerm;
use App\Models\ActivityProgress;
use App\Models\Classroom;
use App\Models\ClassroomQuiz;
use App\Models\ClassroomStudent;
use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\GuardianStudent;
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
use App\Models\RewardStudent;
use App\Models\SchoolYear;
use App\Models\Setting;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('starts every new model with the defaults the database would store', function (string $model) {
    $newModel = new $model;

    $databaseDefaults = collect(Schema::getColumns($newModel->getTable()))
        ->reject(fn (array $column): bool => $column['default'] === null || $column['auto_increment'])
        ->mapWithKeys(fn (array $column): array => [$column['name'] => trim($column['default'], "'")])
        ->sortKeys()
        ->all();

    $modelDefaults = collect($newModel->getAttributes())
        ->map(fn (mixed $value): string => is_bool($value) ? (string) (int) $value : (string) $value)
        ->sortKeys()
        ->all();

    expect($modelDefaults)->toBe($databaseDefaults);
})->with([
    'User' => User::class,
    'StudentProfile' => StudentProfile::class,
    'SchoolYear' => SchoolYear::class,
    'AcademicTerm' => AcademicTerm::class,
    'Subject' => Subject::class,
    'Classroom' => Classroom::class,
    'ClassroomStudent' => ClassroomStudent::class,
    'GuardianStudent' => GuardianStudent::class,
    'LearningModule' => LearningModule::class,
    'LearningActivity' => LearningActivity::class,
    'Mission' => Mission::class,
    'Reward' => Reward::class,
    'RewardStudent' => RewardStudent::class,
    'Quiz' => Quiz::class,
    'ClassroomQuiz' => ClassroomQuiz::class,
    'QuestionBankItem' => QuestionBankItem::class,
    'QuizQuestion' => QuizQuestion::class,
    'QuizAttempt' => QuizAttempt::class,
    'QuizAttemptAnswer' => QuizAttemptAnswer::class,
    'MissionProgress' => MissionProgress::class,
    'ActivityProgress' => ActivityProgress::class,
    'LessonPlan' => LessonPlan::class,
    'LearnerFeedback' => LearnerFeedback::class,
    'ForumCategory' => ForumCategory::class,
    'ForumThread' => ForumThread::class,
    'ForumReply' => ForumReply::class,
    'ForumReport' => ForumReport::class,
    'Setting' => Setting::class,
]);

it('exposes the defaults on a model right after it is created', function () {
    $teacher = User::factory()->create();

    $quiz = $teacher->quizzes()->create([
        'subject_id' => $teacher->assignedSubjects()->create(['name' => 'Health', 'code' => 'HEALTH-5'])->id,
        'grade_level' => 5,
        'title' => 'Food Groups Quiz',
    ]);

    expect($quiz->isDraft())->toBeTrue();
    expect($quiz->passing_score)->toBe(75);
    expect($quiz->show_correct_answers)->toBeTrue();
    expect($teacher->is_active)->toBeTrue();
});
