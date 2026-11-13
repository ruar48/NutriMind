<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
| The routing contract (CONTRACT.md section 7). Phase-2 controllers are written against these names, URIs,
| verbs and actions, so a change here breaks other areas: update the contract first.
*/

/**
 * Every contract route: name => [verb, URI, controller action relative to App\Http\Controllers].
 *
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
function contractRoutes(): array
{
    $routes = [
        'home' => ['GET', '/', 'HomeController@__invoke'],
        'login' => ['GET', 'login', 'Auth\PortalSelectionController@__invoke'],
        'login.portal' => ['GET', 'login/{portal}', 'Auth\LoginController@create'],
        'login.portal.store' => ['POST', 'login/{portal}', 'Auth\LoginController@store'],
        'logout' => ['POST', 'logout', 'Auth\LoginController@destroy'],
        'dashboard' => ['GET', 'dashboard', 'DashboardRedirectController@__invoke'],
        'account.edit' => ['GET', 'account', 'AccountController@edit'],
        'account.update' => ['PUT', 'account', 'AccountController@update'],
        'account.password.update' => ['PUT', 'account/password', 'AccountPasswordController@update'],

        'admin.dashboard' => ['GET', 'admin', 'Admin\DashboardController@__invoke'],
        'admin.analytics.index' => ['GET', 'admin/analytics', 'Admin\AnalyticsController@__invoke'],
        'admin.school-years.activation.update' => ['PUT', 'admin/school-years/{school_year}/activation', 'Admin\SchoolYearActivationController@update'],
        'admin.school-years.terms.create' => ['GET', 'admin/school-years/{school_year}/terms/create', 'Admin\AcademicTermController@create'],
        'admin.school-years.terms.store' => ['POST', 'admin/school-years/{school_year}/terms', 'Admin\AcademicTermController@store'],
        'admin.terms.edit' => ['GET', 'admin/terms/{term}/edit', 'Admin\AcademicTermController@edit'],
        'admin.terms.update' => ['PUT', 'admin/terms/{term}', 'Admin\AcademicTermController@update'],
        'admin.terms.destroy' => ['DELETE', 'admin/terms/{term}', 'Admin\AcademicTermController@destroy'],
        'admin.terms.activation.update' => ['PUT', 'admin/terms/{term}/activation', 'Admin\AcademicTermActivationController@update'],
        'admin.subject-assignments.index' => ['GET', 'admin/subject-assignments', 'Admin\SubjectAssignmentController@index'],
        'admin.subject-assignments.update' => ['PUT', 'admin/subject-assignments/{teacher}', 'Admin\SubjectAssignmentController@update'],
        'admin.settings.edit' => ['GET', 'admin/settings', 'Admin\SettingController@edit'],
        'admin.settings.update' => ['PUT', 'admin/settings', 'Admin\SettingController@update'],
        'admin.roles.index' => ['GET', 'admin/roles', 'Admin\RolePermissionController@index'],
        'admin.roles.update' => ['PUT', 'admin/roles', 'Admin\RolePermissionController@update'],
        'admin.users.status.update' => ['PUT', 'admin/users/{user}/status', 'Admin\UserStatusController@update'],
        'admin.users.password.update' => ['PUT', 'admin/users/{user}/password', 'Admin\UserPasswordController@update'],
        'admin.teachers.index' => ['GET', 'admin/teachers', 'Admin\TeacherController@index'],
        'admin.teachers.show' => ['GET', 'admin/teachers/{teacher}', 'Admin\TeacherController@show'],
        'admin.students.export' => ['GET', 'admin/students/export', 'Admin\StudentExportController@__invoke'],
        'admin.students.import.template' => ['GET', 'admin/students/import-template', 'Admin\StudentImportTemplateController@__invoke'],
        'admin.students.import.credentials' => ['GET', 'admin/students/import/credentials', 'Admin\ImportCredentialsController@__invoke'],
        'admin.students.import.create' => ['GET', 'admin/students/import', 'Admin\StudentImportController@create'],
        'admin.students.import.store' => ['POST', 'admin/students/import', 'Admin\StudentImportController@store'],
        'admin.students.import.show' => ['GET', 'admin/students/import/{import}', 'Admin\StudentImportController@show'],
        'admin.students.import.commit' => ['POST', 'admin/students/import/{import}/commit', 'Admin\StudentImportCommitController@store'],
        'admin.parents.index' => ['GET', 'admin/parents', 'Admin\ParentController@index'],
        'admin.parents.show' => ['GET', 'admin/parents/{guardian}', 'Admin\ParentController@show'],
        'admin.parents.children.store' => ['POST', 'admin/parents/{guardian}/children', 'Admin\GuardianLinkController@store'],
        'admin.parents.children.destroy' => ['DELETE', 'admin/parents/{guardian}/children/{student}', 'Admin\GuardianLinkController@destroy'],
        'admin.reports.index' => ['GET', 'admin/reports', 'Admin\ReportController@index'],
        'admin.reports.show' => ['GET', 'admin/reports/{report}', 'Admin\ReportController@show'],
        'admin.reports.export' => ['GET', 'admin/reports/{report}/export', 'Admin\ReportExportController@__invoke'],
        'admin.reports.print' => ['GET', 'admin/reports/{report}/print', 'Admin\ReportPrintController@__invoke'],
        'admin.modules.activities.create' => ['GET', 'admin/modules/{module}/activities/create', 'Admin\LearningActivityController@create'],
        'admin.modules.activities.store' => ['POST', 'admin/modules/{module}/activities', 'Admin\LearningActivityController@store'],
        'admin.activities.edit' => ['GET', 'admin/activities/{activity}/edit', 'Admin\LearningActivityController@edit'],
        'admin.activities.update' => ['PUT', 'admin/activities/{activity}', 'Admin\LearningActivityController@update'],
        'admin.activities.destroy' => ['DELETE', 'admin/activities/{activity}', 'Admin\LearningActivityController@destroy'],
        'admin.content-alignment.index' => ['GET', 'admin/content-alignment', 'Admin\ContentAlignmentController@__invoke'],
        'admin.forum.reports.index' => ['GET', 'admin/forum/reports', 'Admin\ForumReportController@index'],
        'admin.forum.reports.update' => ['PUT', 'admin/forum/reports/{forum_report}', 'Admin\ForumReportController@update'],
        'admin.forum.pending.index' => ['GET', 'admin/forum/pending', 'Admin\ForumApprovalController@index'],
        'admin.forum.pending.update' => ['PUT', 'admin/forum/pending/{thread}', 'Admin\ForumApprovalController@update'],

        'teacher.dashboard' => ['GET', 'teacher', 'Teacher\DashboardController@__invoke'],
        'teacher.analytics.index' => ['GET', 'teacher/analytics', 'Teacher\AnalyticsController@__invoke'],
        'teacher.subjects.index' => ['GET', 'teacher/subjects', 'Teacher\SubjectController@index'],
        'teacher.classrooms.archive.store' => ['POST', 'teacher/classrooms/{classroom}/archive', 'Teacher\ClassroomArchiveController@store'],
        'teacher.classrooms.archive.destroy' => ['DELETE', 'teacher/classrooms/{classroom}/archive', 'Teacher\ClassroomArchiveController@destroy'],
        'teacher.classrooms.students.create' => ['GET', 'teacher/classrooms/{classroom}/students/create', 'Teacher\ClassroomStudentController@create'],
        'teacher.classrooms.students.store' => ['POST', 'teacher/classrooms/{classroom}/students', 'Teacher\ClassroomStudentController@store'],
        'teacher.classrooms.students.destroy' => ['DELETE', 'teacher/classrooms/{classroom}/students/{student}', 'Teacher\ClassroomStudentController@destroy'],
        'teacher.classrooms.enrollments.store' => ['POST', 'teacher/classrooms/{classroom}/enrollments', 'Teacher\EnrollmentController@store'],
        'teacher.classrooms.students.password.update' => ['PUT', 'teacher/classrooms/{classroom}/students/{student}/password', 'Teacher\StudentPasswordController@update'],
        'teacher.classrooms.import.create' => ['GET', 'teacher/classrooms/{classroom}/import', 'Teacher\StudentImportController@create'],
        'teacher.classrooms.import.store' => ['POST', 'teacher/classrooms/{classroom}/import', 'Teacher\StudentImportController@store'],
        'teacher.classrooms.import.show' => ['GET', 'teacher/classrooms/{classroom}/import/{import}', 'Teacher\StudentImportController@show'],
        'teacher.classrooms.import.commit' => ['POST', 'teacher/classrooms/{classroom}/import/{import}/commit', 'Teacher\StudentImportCommitController@store'],
        'teacher.imports.template' => ['GET', 'teacher/imports/template', 'Teacher\StudentImportTemplateController@__invoke'],
        'teacher.imports.credentials' => ['GET', 'teacher/imports/credentials', 'Teacher\ImportCredentialsController@__invoke'],
        'teacher.quizzes.questions.create' => ['GET', 'teacher/quizzes/{quiz}/questions/create', 'Teacher\QuizQuestionController@create'],
        'teacher.quizzes.questions.store' => ['POST', 'teacher/quizzes/{quiz}/questions', 'Teacher\QuizQuestionController@store'],
        'teacher.quizzes.questions.edit' => ['GET', 'teacher/quizzes/{quiz}/questions/{question}/edit', 'Teacher\QuizQuestionController@edit'],
        'teacher.quizzes.questions.update' => ['PUT', 'teacher/quizzes/{quiz}/questions/{question}', 'Teacher\QuizQuestionController@update'],
        'teacher.quizzes.questions.destroy' => ['DELETE', 'teacher/quizzes/{quiz}/questions/{question}', 'Teacher\QuizQuestionController@destroy'],
        'teacher.quizzes.questions.position.update' => ['PUT', 'teacher/quizzes/{quiz}/questions/{question}/position', 'Teacher\QuizQuestionPositionController@update'],
        'teacher.quizzes.questions.bank.store' => ['POST', 'teacher/quizzes/{quiz}/questions/{question}/bank', 'Teacher\QuestionBankSaveController@store'],
        'teacher.quizzes.review.show' => ['GET', 'teacher/quizzes/{quiz}/review', 'Teacher\QuizReviewController@show'],
        'teacher.quizzes.review.store' => ['POST', 'teacher/quizzes/{quiz}/review', 'Teacher\QuizReviewController@store'],
        'teacher.quizzes.status.update' => ['PUT', 'teacher/quizzes/{quiz}/status', 'Teacher\QuizStatusController@update'],
        'teacher.quizzes.duplicate.store' => ['POST', 'teacher/quizzes/{quiz}/duplicate', 'Teacher\QuizDuplicateController@store'],
        'teacher.quizzes.bank-questions.create' => ['GET', 'teacher/quizzes/{quiz}/bank-questions', 'Teacher\QuizBankQuestionController@create'],
        'teacher.quizzes.bank-questions.store' => ['POST', 'teacher/quizzes/{quiz}/bank-questions', 'Teacher\QuizBankQuestionController@store'],
        'teacher.question-bank.duplicate.store' => ['POST', 'teacher/question-bank/{item}/duplicate', 'Teacher\QuestionBankDuplicateController@store'],
        'teacher.students.index' => ['GET', 'teacher/students', 'Teacher\StudentController@index'],
        'teacher.students.show' => ['GET', 'teacher/students/{student}', 'Teacher\StudentController@show'],
        'teacher.missions.index' => ['GET', 'teacher/missions', 'Teacher\MissionProgressController@index'],
        'teacher.missions.show' => ['GET', 'teacher/missions/{mission}', 'Teacher\MissionProgressController@show'],
        'teacher.quizzes.results.show' => ['GET', 'teacher/quizzes/{quiz}/results', 'Teacher\QuizResultController@show'],
        'teacher.quizzes.results.export' => ['GET', 'teacher/quizzes/{quiz}/results/export', 'Teacher\QuizResultExportController@__invoke'],
        'teacher.attempts.show' => ['GET', 'teacher/attempts/{attempt}', 'Teacher\QuizAttemptController@show'],
        'teacher.reports.index' => ['GET', 'teacher/reports', 'Teacher\ReportController@index'],
        'teacher.reports.show' => ['GET', 'teacher/reports/{report}', 'Teacher\ReportController@show'],
        'teacher.reports.export' => ['GET', 'teacher/reports/{report}/export', 'Teacher\ReportExportController@__invoke'],
        'teacher.reports.print' => ['GET', 'teacher/reports/{report}/print', 'Teacher\ReportPrintController@__invoke'],
        'teacher.lesson-plans.alignment.update' => ['PUT', 'teacher/lesson-plans/{lesson_plan}/alignment', 'Teacher\LessonPlanAlignmentController@update'],

        'parent.dashboard' => ['GET', 'parent', 'Guardian\DashboardController@__invoke'],
        'parent.children.index' => ['GET', 'parent/children', 'Guardian\ChildController@index'],
        'parent.children.show' => ['GET', 'parent/children/{student}', 'Guardian\ChildController@show'],
        'parent.children.attempts.show' => ['GET', 'parent/children/{student}/attempts/{attempt}', 'Guardian\ChildAttemptController@show'],

        'forum.index' => ['GET', 'forum', 'Forum\ForumController@index'],
        'forum.categories.show' => ['GET', 'forum/categories/{category}', 'Forum\CategoryController@show'],
        'forum.threads.create' => ['GET', 'forum/threads/create', 'Forum\ThreadController@create'],
        'forum.threads.store' => ['POST', 'forum/threads', 'Forum\ThreadController@store'],
        'forum.threads.show' => ['GET', 'forum/threads/{thread}', 'Forum\ThreadController@show'],
        'forum.threads.edit' => ['GET', 'forum/threads/{thread}/edit', 'Forum\ThreadController@edit'],
        'forum.threads.update' => ['PUT', 'forum/threads/{thread}', 'Forum\ThreadController@update'],
        'forum.threads.destroy' => ['DELETE', 'forum/threads/{thread}', 'Forum\ThreadController@destroy'],
        'forum.threads.replies.store' => ['POST', 'forum/threads/{thread}/replies', 'Forum\ReplyController@store'],
        'forum.replies.edit' => ['GET', 'forum/replies/{reply}/edit', 'Forum\ReplyController@edit'],
        'forum.replies.update' => ['PUT', 'forum/replies/{reply}', 'Forum\ReplyController@update'],
        'forum.replies.destroy' => ['DELETE', 'forum/replies/{reply}', 'Forum\ReplyController@destroy'],
        'forum.reports.store' => ['POST', 'forum/reports', 'Forum\ReportController@store'],
        'forum.threads.moderation.update' => ['PUT', 'forum/threads/{thread}/moderation', 'Forum\ThreadModerationController@update'],
        'forum.replies.moderation.update' => ['PUT', 'forum/replies/{reply}/moderation', 'Forum\ReplyModerationController@update'],
    ];

    $resources = [
        ['admin.school-years', 'admin/school-years', 'school_year', 'Admin\SchoolYearController', ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']],
        ['admin.subjects', 'admin/subjects', 'subject', 'Admin\SubjectController', ['index', 'create', 'store', 'edit', 'update', 'destroy']],
        ['admin.users', 'admin/users', 'user', 'Admin\UserController', ['index', 'create', 'store', 'edit', 'update', 'destroy']],
        ['admin.students', 'admin/students', 'student', 'Admin\StudentController', ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']],
        ['admin.modules', 'admin/modules', 'module', 'Admin\LearningModuleController', ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']],
        ['admin.missions', 'admin/missions', 'mission', 'Admin\MissionController', ['index', 'create', 'store', 'edit', 'update', 'destroy']],
        ['admin.rewards', 'admin/rewards', 'reward', 'Admin\RewardController', ['index', 'create', 'store', 'edit', 'update', 'destroy']],
        ['admin.forum-categories', 'admin/forum-categories', 'forum_category', 'Admin\ForumCategoryController', ['index', 'create', 'store', 'edit', 'update', 'destroy']],
        ['teacher.classrooms', 'teacher/classrooms', 'classroom', 'Teacher\ClassroomController', ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']],
        ['teacher.quizzes', 'teacher/quizzes', 'quiz', 'Teacher\QuizController', ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']],
        ['teacher.question-bank', 'teacher/question-bank', 'item', 'Teacher\QuestionBankController', ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']],
        ['teacher.feedback', 'teacher/feedback', 'feedback', 'Teacher\FeedbackController', ['index', 'create', 'store', 'edit', 'update', 'destroy']],
        ['teacher.lesson-plans', 'teacher/lesson-plans', 'lesson_plan', 'Teacher\LessonPlanController', ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']],
    ];

    foreach ($resources as [$name, $uri, $parameter, $controller, $actions]) {
        $resourceRoutes = [
            'index' => ['GET', $uri],
            'create' => ['GET', $uri.'/create'],
            'store' => ['POST', $uri],
            'show' => ['GET', $uri.'/{'.$parameter.'}'],
            'edit' => ['GET', $uri.'/{'.$parameter.'}/edit'],
            'update' => ['PUT', $uri.'/{'.$parameter.'}'],
            'destroy' => ['DELETE', $uri.'/{'.$parameter.'}'],
        ];

        foreach ($actions as $action) {
            $routes[$name.'.'.$action] = [...$resourceRoutes[$action], $controller.'@'.$action];
        }
    }

    return $routes;
}

dataset('contract routes', fn (): array => collect(contractRoutes())
    ->map(fn (array $route, string $name): array => [$name, ...$route])
    ->all());

it('registers the contract route :dataset', function (string $name, string $verb, string $uri, string $action) {
    expect(Route::has($name))->toBeTrue();

    $route = Route::getRoutes()->getByName($name);

    expect($route->methods())->toContain($verb)
        ->and($route->uri())->toBe($uri)
        ->and($route->getActionName())->toBe('App\\Http\\Controllers\\'.$action);
})->with('contract routes');

it('omits the resource actions the contract leaves out', function (string $name) {
    expect(Route::has($name))->toBeFalse();
})->with([
    'admin.subjects.show', 'admin.users.show', 'admin.missions.show', 'admin.rewards.show', 'admin.forum-categories.show',
    'admin.school-years.terms.index', 'admin.school-years.terms.show', 'admin.modules.activities.index', 'admin.activities.show',
    'teacher.quizzes.questions.index', 'teacher.quizzes.questions.show', 'teacher.feedback.show', 'forum.threads.index',
]);

it('wraps every route of an area in its authentication and role middleware', function (string $prefix, array $middleware) {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => Str::startsWith((string) $route->getName(), $prefix));

    expect($routes)->not->toBeEmpty();
    $routes->each(fn (RoutingRoute $route) => expect($route->middleware())->toContain(...$middleware));
})->with([
    'administrator area' => ['admin.', ['web', 'auth', 'role:admin']],
    'teacher area' => ['teacher.', ['web', 'auth', 'role:teacher']],
    'parent area' => ['parent.', ['web', 'auth', 'role:parent']],
    'forum' => ['forum.', ['web', 'auth', 'role:admin,teacher,parent']],
    'account settings' => ['account.', ['web', 'auth', 'role:admin,teacher,parent']],
    'sign-in pages' => ['login', ['web', 'guest']],
]);

it('keeps the sign-out and dashboard redirect behind authentication only', function (string $name) {
    expect(Route::getRoutes()->getByName($name)->middleware())->toBe(['web', 'auth']);
})->with(['logout', 'dashboard']);

it('scopes every nested quiz question route to its quiz', function (string $name) {
    expect(Route::getRoutes()->getByName($name)->enforcesScopedBindings())->toBeTrue();
})->with([
    'teacher.quizzes.questions.create', 'teacher.quizzes.questions.store', 'teacher.quizzes.questions.edit',
    'teacher.quizzes.questions.update', 'teacher.quizzes.questions.destroy', 'teacher.quizzes.questions.position.update',
    'teacher.quizzes.questions.bank.store',
]);

it('binds forum categories by slug', function () {
    expect(Route::getRoutes()->getByName('forum.categories.show')->bindingFieldFor('category'))->toBe('slug');
});

it('keeps the health check', function () {
    $this->get('/up')->assertOk();
});
