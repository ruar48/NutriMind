<?php

use App\Http\Controllers\Guardian\ChildAttemptController;
use App\Http\Controllers\Guardian\ChildController;
use App\Http\Controllers\Guardian\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /parent group (auth, role:parent, names "parent."). Owner: P2-Community.
| {student} resolves only learner accounts (AppServiceProvider); whether the parent is linked to the learner and
| whether {attempt} belongs to {student} are checked by the controller or policy, not by the binding.
| Invokable controllers use [Controller::class, '__invoke']: Route::get(uri, Controller::class) checks that the
| class exists while the routes load, and these controllers are written in Phase 2.
*/

Route::get('/', [DashboardController::class, '__invoke'])->name('dashboard');
Route::get('/children', [ChildController::class, 'index'])->name('children.index');
Route::get('/children/{student}', [ChildController::class, 'show'])->name('children.show');
Route::get('/children/{student}/attempts/{attempt}', [ChildAttemptController::class, 'show'])->name('children.attempts.show');
