<?php

use App\Http\Controllers\Teacher\AnalyticsController;
use App\Http\Controllers\Teacher\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /teacher group (auth, role:teacher, names "teacher."). Owner: P2-Insights.
| Invokable controllers are registered as [Controller::class, '__invoke']: Route::get(uri, Controller::class) checks
| that the class exists while the routes load, and these controllers are written in Phase 2.
*/

Route::get('/', [DashboardController::class, '__invoke'])->name('dashboard');
Route::get('/analytics', [AnalyticsController::class, '__invoke'])->name('analytics.index');
