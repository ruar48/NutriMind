<?php

use App\Enums\GuardianRelationship;
use App\Enums\UserRole;
use App\Models\ActivityProgress;
use App\Models\Classroom;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\LearnerFeedback;
use App\Models\LessonPlan;
use App\Models\MissionProgress;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Reward;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('confirms a subject is assigned to the teacher whether given a model or an id', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();

    expect($teacher->teachesSubject($subject))->toBeTrue();
    expect($teacher->teachesSubject($subject->id))->toBeTrue();
});

it('denies a subject that is not assigned to the teacher', function () {
    $teacher = User::factory()->teaching()->create();
    $otherSubject = Subject::factory()->create();

    expect($teacher->teachesSubject($otherSubject))->toBeFalse();
    expect($teacher->teachesSubject($otherSubject->id))->toBeFalse();
});

it('lists the ids of the subjects assigned to the teacher', function () {
    $subjects = Subject::factory()->count(2)->create();
    Subject::factory()->create();
    $teacher = User::factory()->teaching(...$subjects)->create();

    $subjectIds = $teacher->assignedSubjectIds();

    expect($subjectIds)->toEqualCanonicalizing($subjects->modelKeys())->each->toBeInt();
});

it('queries the subject assignments once per user instance', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();

    DB::enableQueryLog();
    $teacher->teachesSubject($subject);
    $teacher->teachesSubject($subject->id);
    $teacher->assignedSubjectIds();

    expect(DB::getQueryLog())->toHaveCount(1);
});

it('reads the subject assignments from an eager-loaded relation without querying', function () {
    $subject = Subject::factory()->create();
    $teacherId = User::factory()->teaching($subject)->create()->id;
    $teacher = User::query()->with('assignedSubjects')->findOrFail($teacherId);

    DB::enableQueryLog();
    $teachesSubject = $teacher->teachesSubject($subject);

    expect($teachesSubject)->toBeTrue();
    expect(DB::getQueryLog())->toBeEmpty();
});

it('reads the subject assignments again after the user is refreshed', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $teacher->teachesSubject($subject);
    $teacher->assignedSubjects()->detach($subject);

    $teachesSubject = $teacher->refresh()->teachesSubject($subject);

    expect($teachesSubject)->toBeFalse();
});

it('answers each role helper for the user role only', function (UserRole $role, array $answers) {
    $user = new User;
    $user->role = $role;

    expect([
        'admin' => $user->isAdmin(),
        'teacher' => $user->isTeacher(),
        'parent' => $user->isParent(),
        'student' => $user->isStudent(),
    ])->toBe($answers);
})->with([
    'admin' => [UserRole::Admin, ['admin' => true, 'teacher' => false, 'parent' => false, 'student' => false]],
    'teacher' => [UserRole::Teacher, ['admin' => false, 'teacher' => true, 'parent' => false, 'student' => false]],
    'parent' => [UserRole::Parent, ['admin' => false, 'teacher' => false, 'parent' => true, 'student' => false]],
    'student' => [UserRole::Student, ['admin' => false, 'teacher' => false, 'parent' => false, 'student' => true]],
]);

it('matches a user who has any of the given roles', function () {
    $user = new User;
    $user->role = UserRole::Teacher;

    expect($user->hasRole(UserRole::Admin, UserRole::Teacher))->toBeTrue();
    expect($user->hasRole(UserRole::Parent, UserRole::Student))->toBeFalse();
    expect($user->hasRole())->toBeFalse();
});

it('builds up to two uppercase initials from the name', function (string $name, string $initials) {
    $user = new User(['name' => $name]);

    expect($user->initials)->toBe($initials);
})->with([
    'first and last name' => ['Juan Dela Cruz', 'JC'],
    'single name' => ['Maria', 'M'],
    'extra spaces and lowercase' => ['  ana   reyes ', 'AR'],
    'generational suffix' => ['Jose Rizal Jr.', 'JR'],
    'roman numeral suffix' => ['Andres Bonifacio III', 'AB'],
    'accented letters' => ['ñiño Santos', 'ÑS'],
    'leading punctuation' => ['(Ms.) Liza Soberano', 'MS'],
    'no letters' => ['1234', '?'],
]);

it('ignores the role and the activation status in mass assignment', function () {
    $user = new User(['name' => 'Ana Reyes', 'role' => UserRole::Admin->value, 'is_active' => false]);

    expect($user->role)->toBeNull();
    expect($user->is_active)->toBeTrue();
});

it('hides the password and remember token from serialization', function () {
    $user = User::factory()->create();

    expect($user->toArray())->not->toHaveKeys(['password', 'remember_token']);
});

it('filters users by role', function () {
    $teacher = User::factory()->teacher()->create();
    User::factory()->parent()->create();
    User::factory()->admin()->create();

    $teacherIds = User::query()->ofRole(UserRole::Teacher)->pluck('id')->all();

    expect($teacherIds)->toBe([$teacher->id]);
});

it('filters active users', function () {
    $activeUser = User::factory()->create();
    User::factory()->inactive()->create();

    $activeIds = User::query()->active()->pluck('id')->all();

    expect($activeIds)->toBe([$activeUser->id]);
});

it('matches the search term against the name, email and username', function (string $term) {
    $match = User::factory()->create(['name' => 'Maria Clara Santos', 'email' => 'mclara@example.test', 'username' => 'mc.santos']);
    User::factory()->create(['name' => 'Jose Rizal', 'email' => 'jose@example.test', 'username' => 'jrizal']);

    $matchingIds = User::query()->search($term)->pluck('id')->all();

    expect($matchingIds)->toBe([$match->id]);
})->with([
    'name' => 'Clara',
    'email' => 'mclara@',
    'username' => 'c.santos',
    'different letter case' => 'cLaRa',
    'surrounding spaces' => '  Clara  ',
]);

it('leaves the query unchanged when the search term is blank', function (?string $term) {
    User::factory()->count(2)->create();

    $count = User::query()->search($term)->count();

    expect($count)->toBe(2);
})->with([
    'null' => null,
    'empty' => '',
    'whitespace' => '   ',
]);

it('keeps the other constraints of the query when searching', function () {
    $teacher = User::factory()->teacher()->create(['name' => 'Ana Teacher']);
    User::factory()->parent()->create(['name' => 'Ana Parent']);

    $matchingIds = User::query()->ofRole(UserRole::Teacher)->search('Ana')->pluck('id')->all();

    expect($matchingIds)->toBe([$teacher->id]);
});

it('treats the search term as data rather than SQL', function () {
    User::factory()->create(['name' => 'Maria Clara']);

    $count = User::query()->search("' OR 1=1 --")->count();

    expect($count)->toBe(0);
});

it('links guardians and learners in both directions with the typed relationship', function () {
    $student = User::factory()->student()->create();
    $guardian = User::factory()->parent()->guardianOf($student, GuardianRelationship::Mother)->create();

    $linkedStudent = $guardian->linkedStudents()->sole();
    $linkedGuardian = $student->guardians()->sole();

    expect($linkedStudent->is($student))->toBeTrue();
    expect($linkedStudent->pivot->relationship)->toBe(GuardianRelationship::Mother);
    expect($linkedGuardian->is($guardian))->toBeTrue();
});

it('records a guardian link as a guardian when no relationship is given', function () {
    $student = User::factory()->student()->create();
    $guardian = User::factory()->parent()->create();

    $guardian->linkedStudents()->attach($student);

    expect($guardian->linkedStudents()->sole()->pivot->relationship)->toBe(GuardianRelationship::Guardian);
});

it('reaches the learner profile and earned rewards of a learner', function () {
    $student = User::factory()->student()->create();
    $reward = Reward::factory()->create();
    $student->rewards()->attach($reward);

    expect($student->studentProfile)->toBeInstanceOf(StudentProfile::class);
    expect($student->rewards()->sole()->is($reward))->toBeTrue();
});

it('reaches the records a user owns through each has-many relation', function (string $relation, Closure $createOwnedRecord) {
    $user = User::factory()->create();
    $ownedRecord = $createOwnedRecord($user);
    $createOwnedRecord(User::factory()->create());

    $relatedRecord = $user->{$relation}()->sole();

    expect($relatedRecord->is($ownedRecord))->toBeTrue();
})->with([
    'classrooms' => ['classrooms', fn (User $user) => Classroom::factory()->for($user, 'teacher')->create()],
    'quizzes' => ['quizzes', fn (User $user) => Quiz::factory()->for($user, 'teacher')->create()],
    'question bank items' => ['questionBankItems', fn (User $user) => QuestionBankItem::factory()->for($user, 'teacher')->create()],
    'lesson plans' => ['lessonPlans', fn (User $user) => LessonPlan::factory()->for($user, 'teacher')->create()],
    'quiz attempts' => ['quizAttempts', fn (User $user) => QuizAttempt::factory()->for($user, 'student')->create()],
    'mission progress' => ['missionProgress', fn (User $user) => MissionProgress::factory()->for($user, 'student')->create()],
    'activity progress' => ['activityProgress', fn (User $user) => ActivityProgress::factory()->for($user, 'student')->create()],
    'feedback received' => ['feedbackReceived', fn (User $user) => LearnerFeedback::factory()->for($user, 'student')->create()],
    'feedback given' => ['feedbackGiven', fn (User $user) => LearnerFeedback::factory()->for($user, 'teacher')->create()],
    'forum threads' => ['forumThreads', fn (User $user) => ForumThread::factory()->for($user, 'author')->create()],
    'forum replies' => ['forumReplies', fn (User $user) => ForumReply::factory()->for($user, 'author')->create()],
    'forum reports' => ['forumReports', fn (User $user) => ForumReport::factory()->for($user, 'reporter')->create()],
]);
