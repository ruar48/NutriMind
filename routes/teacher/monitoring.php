<?php

use App\Http\Controllers\Teacher\FeedbackController;
use App\Http\Controllers\Teacher\MissionProgressController;
use App\Http\Controllers\Teacher\QuizAttemptController;
use App\Http\Controllers\Teacher\QuizResultController;
use App\Http\Controllers\Teacher\QuizResultExportController;
use App\Http\Controllers\Teacher\StudentController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /teacher group (auth, role:teacher, names "teacher."). Owner: P2-Monitoring.
| {student} resolves only learner accounts (AppServiceProvider). Invokable controllers use
| [Controller::class, '__invoke'] (see insights.php).
*/

Route::get('/students', [StudentController::class, 'index'])->name('students.index');
Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');

Route::get('/missions', [MissionProgressController::class, 'index'])->name('missions.index');
Route::get('/missions/{mission}', [MissionProgressController::class, 'show'])->name('missions.show');

Route::get('/quizzes/{quiz}/results', [QuizResultController::class, 'show'])->name('quizzes.results.show');
Route::get('/quizzes/{quiz}/results/export', [QuizResultExportController::class, '__invoke'])->name('quizzes.results.export');

Route::get('/attempts/{attempt}', [QuizAttemptController::class, 'show'])->name('attempts.show');

Route::resource('feedback', FeedbackController::class)->except(['show']);
