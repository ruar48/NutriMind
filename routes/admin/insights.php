<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /admin group (auth, role:admin, names "admin."). Owner: P2-Insights.
| Invokable controllers are registered as [Controller::class, '__invoke']: Route::get(uri, Controller::class) checks
| that the class exists while the routes load, and these controllers are written in Phase 2.
*/

Route::get('/', [DashboardController::class, '__invoke'])->name('dashboard');
Route::get('/analytics', [AnalyticsController::class, '__invoke'])->name('analytics.index');
