<?php

use App\Http\Controllers\Teacher\ImportCredentialsController;
use App\Http\Controllers\Teacher\StudentImportCommitController;
use App\Http\Controllers\Teacher\StudentImportController;
use App\Http\Controllers\Teacher\StudentImportTemplateController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /teacher group (auth, role:teacher, names "teacher."). Owner: P2-People.
| Invokable controllers use [Controller::class, '__invoke'] (see insights.php).
*/

Route::get('/classrooms/{classroom}/import', [StudentImportController::class, 'create'])->name('classrooms.import.create');
Route::post('/classrooms/{classroom}/import', [StudentImportController::class, 'store'])->name('classrooms.import.store');
Route::get('/classrooms/{classroom}/import/{import}', [StudentImportController::class, 'show'])->name('classrooms.import.show');
Route::post('/classrooms/{classroom}/import/{import}/commit', [StudentImportCommitController::class, 'store'])->name('classrooms.import.commit');

Route::get('/imports/template', [StudentImportTemplateController::class, '__invoke'])->name('imports.template');
Route::get('/imports/credentials', [ImportCredentialsController::class, '__invoke'])->name('imports.credentials');
