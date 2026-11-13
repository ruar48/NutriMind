<?php

use App\Http\Controllers\Admin\GuardianLinkController;
use App\Http\Controllers\Admin\ImportCredentialsController;
use App\Http\Controllers\Admin\ParentController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentExportController;
use App\Http\Controllers\Admin\StudentImportCommitController;
use App\Http\Controllers\Admin\StudentImportController;
use App\Http\Controllers\Admin\StudentImportTemplateController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Http\Controllers\Admin\UserStatusController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /admin group (auth, role:admin, names "admin."). Owner: P2-People.
| {teacher}, {student} and {guardian} resolve only users of that role (AppServiceProvider); other IDs are 404.
| The static /students/... segments are registered before Route::resource('students') so they are not
| captured by /students/{student}. Invokable controllers use [Controller::class, '__invoke'] (see insights.php).
*/

Route::resource('users', UserController::class)->except(['show']);
Route::put('/users/{user}/status', [UserStatusController::class, 'update'])->name('users.status.update');
Route::put('/users/{user}/password', [UserPasswordController::class, 'update'])->name('users.password.update');

Route::get('/teachers', [TeacherController::class, 'index'])->name('teachers.index');
Route::get('/teachers/{teacher}', [TeacherController::class, 'show'])->name('teachers.show');

Route::get('/students/export', [StudentExportController::class, '__invoke'])->name('students.export');
Route::get('/students/import-template', [StudentImportTemplateController::class, '__invoke'])->name('students.import.template');
Route::get('/students/import/credentials', [ImportCredentialsController::class, '__invoke'])->name('students.import.credentials');
Route::get('/students/import', [StudentImportController::class, 'create'])->name('students.import.create');
Route::post('/students/import', [StudentImportController::class, 'store'])->name('students.import.store');
Route::get('/students/import/{import}', [StudentImportController::class, 'show'])->name('students.import.show');
Route::post('/students/import/{import}/commit', [StudentImportCommitController::class, 'store'])->name('students.import.commit');
Route::resource('students', StudentController::class);

Route::get('/parents', [ParentController::class, 'index'])->name('parents.index');
Route::get('/parents/{guardian}', [ParentController::class, 'show'])->name('parents.show');
Route::post('/parents/{guardian}/children', [GuardianLinkController::class, 'store'])->name('parents.children.store');
Route::delete('/parents/{guardian}/children/{student}', [GuardianLinkController::class, 'destroy'])->name('parents.children.destroy');
