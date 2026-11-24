<?php

use App\Http\Controllers\Game\LoginController;
use App\Http\Controllers\Game\ProfileController;
use App\Http\Controllers\Game\ProgressSyncController;
use App\Http\Controllers\Game\QuizAttemptController;
use App\Http\Controllers\Game\QuizController;
use App\Models\Quiz;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Unity Game API (v1)
|--------------------------------------------------------------------------
|
| JSON endpoints the NutriMind Unity game calls, under /api/v1/game. The game signs a learner in with
| POST login and then sends "Authorization: Bearer <token>" and "Accept: application/json" on every
| other request. Only learner accounts may use these routes.
|
*/

/*
| {gameQuiz} resolves only quizzes the signed-in learner may take, so any other quiz answers 404 (the
| learner never learns that it exists). Authentication runs before route bindings, so the user is known.
*/
Route::bind('gameQuiz', fn (string $value): Quiz => Quiz::query()
    ->availableTo(request()->user())
    ->whereKey($value)
    ->firstOrFail());

Route::prefix('v1/game')->name('game.')->group(function () {
    Route::post('login', [LoginController::class, 'store'])->name('login');

    Route::middleware(['auth:sanctum', 'role:student'])->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('me', ProfileController::class)->name('profile');
        Route::get('quizzes', [QuizController::class, 'index'])->name('quizzes.index');
        Route::get('quizzes/{gameQuiz}', [QuizController::class, 'show'])->name('quizzes.show');
        Route::post('quizzes/{gameQuiz}/attempts', [QuizAttemptController::class, 'store'])->name('quizzes.attempts.store');
        Route::post('sync', ProgressSyncController::class)->name('sync');
    });
});
