<?php

use App\Http\Controllers\Admin\ContentAlignmentController;
use App\Http\Controllers\Admin\LearningActivityController;
use App\Http\Controllers\Admin\LearningModuleController;
use App\Http\Controllers\Admin\LearningModulePublicationController;
use App\Http\Controllers\Admin\MissionController;
use App\Http\Controllers\Admin\RewardController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /admin group (auth, role:admin, names "admin."). Owner: P2-Content.
| Invokable controllers use [Controller::class, '__invoke'] (see insights.php).
*/

Route::resource('modules', LearningModuleController::class);
Route::put('/modules/{module}/publication', [LearningModulePublicationController::class, 'update'])->name('modules.publication.update');
Route::resource('modules.activities', LearningActivityController::class)
    ->shallow()
    ->except(['index', 'show']);

Route::resource('missions', MissionController::class)->except(['show']);
Route::resource('rewards', RewardController::class)->except(['show']);

Route::get('/content-alignment', [ContentAlignmentController::class, '__invoke'])->name('content-alignment.index');
